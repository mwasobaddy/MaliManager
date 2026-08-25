<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\AgreementTemplate;
use App\Models\Property;
use App\Services\StaffService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Inline management of organization agreement templates, used from the
 * occupant form: pick a template to pre-fill the editor, or save the
 * current editor content as a new template.
 */
class AgreementTemplateController extends Controller
{
    public function index(Request $request, Property $property): JsonResponse
    {
        $this->authorizePropertyAccess($request, $property);

        return response()->json([
            'templates' => $this->templatesFor($property),
        ]);
    }

    public function store(Request $request, Property $property): JsonResponse
    {
        $this->authorizePropertyAccess($request, $property);

        $validated = $request->validate([
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('agreement_templates', 'name')
                    ->where('organization_id', $property->organization_id),
            ],
            'body_html' => ['required', 'string'],
        ]);

        $template = AgreementTemplate::create([
            'organization_id' => $property->organization_id,
            'name' => $validated['name'],
            // Same allowlist as the lease agreement text.
            'body_html' => strip_tags(
                $validated['body_html'],
                '<p><br><b><strong><i><em><u><s><ul><ol><li><h1><h2><h3><blockquote><code>',
            ),
            'created_by' => $request->user()->id,
        ]);

        return response()->json(['template' => [
            'id' => $template->id,
            'name' => $template->name,
            'body_html' => $template->body_html,
        ]], 201);
    }

    public function destroy(Request $request, Property $property, AgreementTemplate $template): JsonResponse
    {
        $this->authorizePropertyAccess($request, $property);

        abort_if($template->organization_id !== $property->organization_id, 403);

        $template->delete();

        return response()->json(['deleted' => true]);
    }

    /**
     * @return array<int, array{id: int, name: string, body_html: string}>
     */
    private function templatesFor(Property $property): array
    {
        return AgreementTemplate::query()
            ->where('organization_id', $property->organization_id)
            ->orderBy('name')
            ->get(['id', 'name', 'body_html'])
            ->map(fn (AgreementTemplate $template) => [
                'id' => $template->id,
                'name' => $template->name,
                'body_html' => $template->body_html,
            ])
            ->all();
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
}
