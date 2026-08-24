<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Audit;
use App\Models\Organization;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminAuditController extends Controller
{
    public function __construct(private AuditService $service)
    {
    }

    public function index(Request $request): Response
    {
        $filters = $this->service->filtersFromRequest($request);

        $audits = $this->service->query($filters, null)
            ->paginate(15)
            ->withQueryString();

        $audits->getCollection()->transform(fn (Audit $audit) => $this->service->transform($audit));

        return Inertia::render('admin/audit/index', [
            'audits' => $audits,
            'filters' => $filters,
            'canExport' => true,
            'options' => [
                'events' => $this->service->distinctEvents(null),
                'subjectTypes' => $this->service->distinctSubjectTypes(null),
                'organizations' => Organization::query()
                    ->orderBy('name')
                    ->get(['id', 'name', 'tenant_id'])
                    ->map(fn (Organization $org) => ['id' => $org->tenant_id, 'name' => $org->name]),
            ],
        ]);
    }

    public function export(Request $request)
    {
        $audits = $this->service->query($this->service->filtersFromRequest($request), null)->get();

        return $this->service->toCsv($audits, 'audit-log-all.csv');
    }
}
