<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Audit;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditService
{
    /**
     * Allowlisted so a crafted `?sort=` can never reach the ORDER BY.
     *
     * `subject_type` is a class name, which is a useless thing to sort a log by
     * (it groups by namespace, not by what happened). The label a person reads
     * is the short type, and ordering by that needs a CASE the allowlist does
     * not want to carry.
     *
     * @var array<string, string>
     */
    private const SORTABLE = [
        'created_at' => 'created_at',
        'event' => 'event',
        'log_name' => 'log_name',
    ];

    /**
     * @param  array<string, mixed>  $filters
     * @param  array{column: string|null, direction: string}  $sort
     */
    public function query(array $filters, ?string $tenantId, array $sort = ['column' => null, 'direction' => 'desc']): Builder
    {
        $query = Audit::query()->with('causer')->forTenant($tenantId);

        if ($actor = $filters['actor'] ?? null) {
            $query->whereHasMorph('causer', [User::class], function (Builder $q) use ($actor) {
                $q->where('name', 'like', "%{$actor}%")
                    ->orWhere('email', 'like', "%{$actor}%");
            });
        }

        if ($event = $filters['event'] ?? null) {
            $query->where('event', $event);
        }

        if ($logName = $filters['log_name'] ?? null) {
            $query->where('log_name', $logName);
        }

        if ($subjectType = $filters['subject_type'] ?? null) {
            $query->where('subject_type', $subjectType);
        }

        if ($tenantIdFilter = $filters['tenant_id'] ?? null) {
            $query->where('tenant_id', $tenantIdFilter);
        }

        if ($search = $filters['search'] ?? null) {
            $query->where('description', 'like', "%{$search}%");
        }

        if ($dateFrom = $filters['date_from'] ?? null) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo = $filters['date_to'] ?? null) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $column = self::SORTABLE[$sort['column'] ?? ''] ?? null;
        $direction = ($sort['direction'] ?? null) === 'asc' ? 'asc' : 'desc';

        // Newest first is the only useful default for a log, and it is also
        // what every existing caller of this method relied on, so the unsorted
        // branch has to keep it.
        return $column !== null
            ? $query->orderBy($column, $direction)->orderBy('id')
            : $query->latest('created_at');
    }

    /**
     * The requested sort, normalised so the controller does not have to repeat
     * the allowlist. Returns a null column for anything not on the list.
     *
     * @return array{column: string|null, direction: string}
     */
    public function sortFromRequest(Request $request): array
    {
        $column = $request->string('sort')->toString();

        return [
            'column' => isset(self::SORTABLE[$column]) ? $column : null,
            'direction' => $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc',
        ];
    }

    /**
     * @return array<int, mixed>
     */
    public function filtersFromRequest(Request $request): array
    {
        return $request->only([
            'actor', 'event', 'log_name', 'subject_type', 'search', 'date_from', 'date_to', 'tenant_id',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function transform(Audit $audit): array
    {
        return [
            'id' => $audit->id,
            'created_at' => $audit->created_at?->toIso8601String(),
            'event' => $audit->event,
            'log_name' => $audit->log_name,
            'description' => $audit->description,
            'ip_address' => $audit->ip_address,
            'causer' => $audit->causer ? [
                'id' => $audit->causer->getKey(),
                'name' => $audit->causer->name,
                'email' => $audit->causer->email,
            ] : null,
            'subject' => $audit->subject_type ? [
                'type' => $this->shortType($audit->subject_type),
                'id' => $audit->subject_id,
            ] : null,
        ];
    }

    /**
     * @param  iterable<Audit>  $audits
     */
    public function toCsv(iterable $audits, string $filename = 'audit-log.csv'): StreamedResponse
    {
        $callback = function () use ($audits): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['Time', 'Actor', 'Event', 'Category', 'Subject', 'Description', 'IP address']);

            foreach ($audits as $audit) {
                $row = $this->transform($audit);

                fputcsv($handle, [
                    $row['created_at'],
                    $row['causer']['name'] ?? ($row['causer']['email'] ?? 'System'),
                    $row['event'] ?? '',
                    $row['log_name'] ?? '',
                    $row['subject'] ? $row['subject']['type'].' #'.$row['subject']['id'] : '',
                    $row['description'] ?? '',
                    $row['ip_address'] ?? '',
                ]);
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function distinctEvents(?string $tenantId): array
    {
        return Audit::query()->forTenant($tenantId)
            ->whereNotNull('event')
            ->distinct()
            ->orderBy('event')
            ->pluck('event')
            ->all();
    }

    public function distinctSubjectTypes(?string $tenantId): array
    {
        return Audit::query()->forTenant($tenantId)
            ->whereNotNull('subject_type')
            ->distinct()
            ->orderBy('subject_type')
            ->pluck('subject_type')
            ->mapWithKeys(fn ($type) => [$type => $this->shortType($type)])
            ->all();
    }

    public function shortType(string $type): string
    {
        return Str::headline(class_basename($type));
    }
}
