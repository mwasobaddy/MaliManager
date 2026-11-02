<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Actions\Audit\ExportAuditLog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\AuditIndexRequest;
use App\Http\Requests\Tenant\AuditShowRequest;
use App\Http\Requests\Tenant\ExportAuditRequest;
use App\Models\Audit;
use App\Models\User;
use App\Services\AuditService;
use App\Support\TenancyContext;
use Inertia\Inertia;
use Inertia\Response;

class AuditController extends Controller
{
    public function __construct(private AuditService $service) {}

    public function index(AuditIndexRequest $request): Response
    {
        $tenantId = TenancyContext::tenantId();
        $organization = TenancyContext::organization();

        $filters = $this->service->filtersFromRequest($request);

        $audits = $this->service->query($filters, $tenantId)
            ->paginate(15)
            ->withQueryString();

        $audits->getCollection()->transform(fn (Audit $audit) => $this->service->transform($audit));

        return Inertia::render('organisation/audit/index', [
            'audits' => $audits,
            'filters' => $filters,
            'canExport' => true,
            'options' => [
                'events' => $this->service->distinctEvents($tenantId),
                'subjectTypes' => $this->service->distinctSubjectTypes($tenantId),
                'actors' => User::query()
                    ->whereHas('organizations', fn ($q) => $q->whereKey($organization->id))
                    ->orderBy('name')
                    ->get(['id', 'name', 'email'])
                    ->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email]),
            ],
        ]);
    }

    public function show(AuditShowRequest $request, Audit $activity): Response
    {
        if ($activity->tenant_id !== TenancyContext::tenantId()) {
            abort(404);
        }

        return Inertia::render('organisation/audit/show', [
            'audit' => $this->service->transform($activity) + [
                'properties' => $activity->properties,
                'subject_type' => $activity->subject_type,
                'subject_id' => $activity->subject_id,
                'user_agent' => $activity->user_agent,
            ],
        ]);
    }

    public function export(ExportAuditRequest $request, ExportAuditLog $export): \Symfony\Component\HttpFoundation\Response
    {
        $csv = $export->handle($request->toData());

        return response()->streamDownload(
            function () use ($csv): void {
                echo $csv->content;
            },
            $csv->filename,
            ['Content-Type' => 'text/csv'],
        );
    }
}
