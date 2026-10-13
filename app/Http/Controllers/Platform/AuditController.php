<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Audit;
use App\Models\Organization;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditController extends Controller
{
    public function __construct(private AuditService $service) {}

    public function index(Request $request): Response
    {
        $filters = $this->service->filtersFromRequest($request);
        $sort = $this->service->sortFromRequest($request);

        $audits = $this->service->query($filters, null, $sort)
            ->paginate(15)
            ->withQueryString();

        $audits->getCollection()->transform(fn (Audit $audit) => $this->service->transform($audit));

        // The row overlay is addressed by URL, so it must work from a shared
        // link and not only from a tap on a visible row.
        $focusedId = $request->integer('audit') ?: null;

        $focused = null;

        if ($focusedId !== null && ! $audits->getCollection()->contains('id', $focusedId)) {
            $found = Audit::query()->with('causer')->forTenant(null)->find($focusedId);

            $focused = $found ? $this->service->transform($found) : null;
        }

        return Inertia::render('platform/audit/index', [
            'audits' => $audits,
            'focusedAudit' => $focused,
            'filters' => $filters,
            'sort' => $sort,
            'canExport' => $request->user()?->can('exportAudit') ?? false,
            'options' => [
                // Both are small distinct lists over the log's own vocabulary,
                // so they are cheap enough to send eagerly.
                'events' => $this->service->distinctEvents(null),
                'subjectTypes' => $this->service->distinctSubjectTypes(null),
                // Optional rather than eager: one row per organization and
                // unbounded, while only the filters overlay reads it. Sending it
                // eagerly re-queries and re-serialises it on every debounced
                // keystroke, sort and page change for a sheet that is usually
                // closed.
                'organizations' => Inertia::optional(fn () => Organization::query()
                    ->orderBy('name')
                    ->get(['id', 'name', 'tenant_id'])
                    ->map(fn (Organization $org) => ['id' => $org->tenant_id, 'name' => $org->name])),
            ],
        ]);
    }

    public function export(Request $request)
    {
        $audits = $this->service
            ->query(
                $this->service->filtersFromRequest($request),
                null,
                $this->service->sortFromRequest($request),
            )
            ->get();

        return $this->service->toCsv($audits, 'audit-log.csv');
    }
}
