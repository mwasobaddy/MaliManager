<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Services\Reporting\ReportService;
use App\Support\TenancyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Organization-level reports: four core datasets, each filterable by date
 * range, exportable as CSV and printable via a chrome-free page.
 *
 * Access: organization owners always; staff need at least one of the
 * module manage keys the reports draw from.
 */
class ReportController extends Controller
{
    private const MANAGE_KEYS = [
        'expense.manage',
        'maintenance.manage',
        'lease.manage',
        'occupant.manage',
    ];

    public function index(Request $request): Response
    {
        $this->authorize($request);

        return Inertia::render('tenant/reports/index', [
            'types' => ReportService::TYPES,
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorize($request);

        [$type, $from, $to] = $this->validatedRange($request);

        $report = app(ReportService::class, ['organizationId' => TenancyContext::organization()?->id])
            ->build($type, $from, $to);

        return response()->json(['type' => $type] + $report);
    }

    public function csv(Request $request): StreamedResponse
    {
        $this->authorize($request);

        [$type, $from, $to] = $this->validatedRange($request);

        $report = app(ReportService::class, ['organizationId' => TenancyContext::organization()?->id])
            ->build($type, $from, $to);

        $filename = str_replace(' ', '-', strtolower($report['title']))
            .'-'.now()->toDateString().'.csv';

        return response()->streamDownload(function () use ($report): void {
            $out = fopen('php://output', 'w');

            fputcsv($out, array_column($report['columns'], 'label'));

            foreach ($report['rows'] as $row) {
                fputcsv($out, array_values(array_intersect_key(
                    $row,
                    array_flip(array_column($report['columns'], 'key')),
                )));
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function print(Request $request): Response
    {
        $this->authorize($request);

        [$type, $from, $to] = $this->validatedRange($request);

        $report = app(ReportService::class, ['organizationId' => TenancyContext::organization()?->id])
            ->build($type, $from, $to);

        return Inertia::render('tenant/reports/print', [
            'report' => $report,
            'organizationName' => TenancyContext::organization()?->name,
            'rangeLabel' => trim(($from ?? 'Beginning').' → '.($to ?? now()->toDateString())),
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    /**
     * @return array{0: string, 1: ?string, 2: ?string}
     */
    private function validatedRange(Request $request): array
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(ReportService::TYPES)],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        return [
            $validated['type'],
            $validated['from'] ?? null,
            $validated['to'] ?? null,
        ];
    }

    private function authorize(Request $request): void
    {
        $user = $request->user();
        $organization = TenancyContext::organization();

        abort_unless($user && $organization, 403);

        if ($user->isOwnerOf($organization)) {
            return;
        }

        $membership = $user->membershipFor($organization);

        if (! $membership?->sub_role_id) {
            abort(403);
        }

        $held = $membership->subRole
            ->subPermissions()
            ->pluck('key')
            ->intersect(self::MANAGE_KEYS)
            ->isNotEmpty();

        abort_unless($held, 403);
    }
}
