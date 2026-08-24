<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Audit;
use App\Models\User;
use App\Services\AuditService;
use App\Support\TenancyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditController extends Controller
{
    public function __construct(private AuditService $service)
    {
    }

    public function index(Request $request): Response
    {
        $tenantId = tenancy()->tenantId();
        $organization = TenancyContext::organization();

        $filters = $this->service->filtersFromRequest($request);

        $audits = $this->service->query($filters, $tenantId)
            ->paginate(15)
            ->withQueryString();

        $audits->getCollection()->transform(fn (Audit $audit) => $this->service->transform($audit));

        return Inertia::render('tenant/audit/index', [
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

    public function show(Request $request, Audit $activity): Response
    {
        if ($activity->tenant_id !== tenancy()->tenantId()) {
            abort(404);
        }

        return Inertia::render('tenant/audit/show', [
            'audit' => $this->service->transform($activity) + [
                'properties' => $activity->properties,
                'subject_type' => $activity->subject_type,
                'subject_id' => $activity->subject_id,
                'user_agent' => $activity->user_agent,
            ],
        ]);
    }

    public function export(Request $request)
    {
        $tenantId = tenancy()->tenantId();

        $audits = $this->service->query($this->service->filtersFromRequest($request), $tenantId)
            ->get();

        return $this->service->toCsv($audits, 'audit-log-'.tenancy()->tenantId().'.csv');
    }
}
