<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\SaveAgreementTemplateRequest;
use App\Http\Requests\Tenant\TemplatePickerRequest;
use App\Models\AgreementTemplate;
use App\Models\Property;
use App\Services\AgreementTemplateService;
use App\Services\StaffService;
use App\Support\LeaseAgreementTemplate;
use App\Support\TenancyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Lease agreement template management. Templates are scoped:
 * property_id NULL = shared organization-wide, set = one property.
 * Org-level pages manage the shared library; each property has its own.
 */
class AgreementTemplateController extends Controller
{
    public function __construct(private AgreementTemplateService $templates) {}
    // ── Organization scope ────────────────────────────────────────────────

    public function orgIndex(): Response
    {
        return Inertia::render('organisation/agreement-templates/index', [
            'scope' => 'organization',
            'templates' => $this->templates(TenancyContext::organization()?->id, null),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('organisation/agreement-templates/form', [
            'scope' => 'organization',
            'template' => null,
            'availableTokens' => LeaseAgreementTemplate::availableTokens(),
        ]);
    }

    public function store(SaveAgreementTemplateRequest $request): RedirectResponse
    {
        $organizationId = TenancyContext::organization()?->id;
        $validated = $request->validated();

        $template = AgreementTemplate::create([
            'organization_id' => $organizationId,
            'property_id' => null,
            'name' => $validated['name'],
            'body_html' => LeaseAgreementTemplate::sanitize($validated['body_html']),
            'created_by' => $request->user()->id,
        ]);

        $this->syncDocument($request, $template);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Template created.']);

        return redirect()->route('tenant.agreement-templates.org-index');
    }

    public function edit(AgreementTemplate $template): Response
    {
        abort_if($template->organization_id !== TenancyContext::organization()?->id, 403);
        abort_if($template->property_id !== null, 403);

        $document = $template->getFirstMedia('document');

        return Inertia::render('organisation/agreement-templates/form', [
            'scope' => 'organization',
            'template' => array_merge($template->only('id', 'name', 'body_html'), [
                'document_name' => $document?->file_name,
                'document_url' => $document?->getUrl(),
            ]),
            'availableTokens' => LeaseAgreementTemplate::availableTokens(),
        ]);
    }

    public function update(SaveAgreementTemplateRequest $request, AgreementTemplate $template): RedirectResponse
    {
        abort_if($template->organization_id !== TenancyContext::organization()?->id, 403);
        abort_if($template->property_id !== null, 403);

        $validated = $request->validated();

        $template->update([
            'name' => $validated['name'],
            'body_html' => LeaseAgreementTemplate::sanitize($validated['body_html']),
        ]);

        $this->syncDocument($request, $template);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Template updated.']);

        return redirect()->route('tenant.agreement-templates.org-index');
    }

    public function destroy(AgreementTemplate $template): RedirectResponse
    {
        abort_if($template->organization_id !== TenancyContext::organization()?->id, 403);
        abort_if($template->property_id !== null, 403);

        $template->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Template deleted.']);

        return redirect()->route('tenant.agreement-templates.org-index');
    }

    // ── Property scope ────────────────────────────────────────────────────

    public function propertyIndex(Property $property): Response
    {
        return Inertia::render('organisation/agreement-templates/index', [
            'scope' => 'property',
            'propertySlug' => $property->slug,
            'templates' => $this->templates($property->organization_id, $property->id),
        ]);
    }

    public function createForProperty(Property $property): Response
    {
        return Inertia::render('organisation/agreement-templates/form', [
            'scope' => 'property',
            'propertySlug' => $property->slug,
            'template' => null,
            'availableTokens' => LeaseAgreementTemplate::availableTokens(),
        ]);
    }

    public function storeForProperty(SaveAgreementTemplateRequest $request, Property $property): RedirectResponse
    {
        $validated = $request->validated();

        $template = $this->templates->create(
            $request->user(),
            $property->organization_id,
            $property->id,
            $validated['name'],
            $validated['body_html'],
        );

        $this->syncDocument($request, $template);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Template created.']);

        return redirect()->route('tenant.agreement-templates.property-index', $property);
    }

    public function editForProperty(Property $property, AgreementTemplate $template): Response
    {
        abort_if($template->organization_id !== $property->organization_id, 403);
        abort_if($template->property_id !== $property->id, 403);

        $document = $template->getFirstMedia('document');

        return Inertia::render('organisation/agreement-templates/form', [
            'scope' => 'property',
            'propertySlug' => $property->slug,
            'template' => array_merge($template->only('id', 'name', 'body_html'), [
                'document_name' => $document?->file_name,
                'document_url' => $document?->getUrl(),
            ]),
            'availableTokens' => LeaseAgreementTemplate::availableTokens(),
        ]);
    }

    public function updateForProperty(SaveAgreementTemplateRequest $request, Property $property, AgreementTemplate $template): RedirectResponse
    {
        abort_if($template->organization_id !== $property->organization_id, 403);
        abort_if($template->property_id !== $property->id, 403);

        $validated = $request->validated();

        $this->templates->update($template, $request->user(), $validated['name'], $validated['body_html']);

        $this->syncDocument($request, $template);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Template updated.']);

        return redirect()->route('tenant.agreement-templates.property-index', $property);
    }

    public function destroyForProperty(Property $property, AgreementTemplate $template): RedirectResponse
    {
        abort_if($template->organization_id !== $property->organization_id, 403);
        abort_if($template->property_id !== $property->id, 403);

        $template->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Template deleted.']);

        return redirect()->route('tenant.agreement-templates.property-index', $property);
    }

    // ── Shared helpers ────────────────────────────────────────────────────

    /**
     * Grouped templates for the occupant form picker: the property's own
     * templates first, then the organization-wide library.
     *
     * @return array{property: array<int, array{id: int, name: string, body_html: string}>, organization: array<int, array{id: int, name: string, body_html: string}>}
     */
    public function picker(TemplatePickerRequest $request, Property $property): JsonResponse
    {
        $this->authorizePropertyAccess($request, $property);

        return response()->json([
            'property' => $this->templates($property->organization_id, $property->id),
            'organization' => $this->templates($property->organization_id, null),
        ]);
    }

    private function authorizePropertyAccess(Request $request, Property $property): void
    {
        $user = $request->user();

        if ($user->isOwnerOf($property->organization)) {
            return;
        }

        $membership = $user->membershipFor($property->organization);

        if ($membership && in_array($property->id, app(StaffService::class)->delegatedPropertyIds($membership))) {
            return;
        }

        abort(403);
    }

    /**
     * @return array<int, array{id: int, name: string, body_html: string}>
     */
    private function templates(?int $organizationId, ?int $propertyId): array
    {
        return AgreementTemplate::query()
            ->where('organization_id', $organizationId)
            ->where('property_id', $propertyId)
            ->orderBy('name')
            ->get(['id', 'name', 'body_html'])
            ->map(fn (AgreementTemplate $template) => [
                'id' => $template->id,
                'name' => $template->name,
                'body_html' => $template->body_html,
            ])
            ->all();
    }

    /**
     * Attach (or remove) the optional ready-made template document.
     * singleFile() semantics mean a fresh upload replaces the previous one.
     */
    private function syncDocument(Request $request, AgreementTemplate $template): void
    {
        if ($request->boolean('remove_agreement_document')) {
            $template->clearMediaCollection('document');

            return;
        }

        if ($request->hasFile('agreement_document')) {
            $template
                ->addMediaFromRequest('agreement_document')
                ->toMediaCollection('document');
        }
    }
}
