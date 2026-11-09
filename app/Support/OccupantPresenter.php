<?php

namespace App\Support;

use App\Models\AgreementTemplate;
use App\Models\Lease;
use App\Models\Occupant;
use App\Models\Property;

/**
 * Read-models for the occupant screens.
 *
 * The controller routes; shaping the rows, forms and sheets it renders is a
 * separate job, and it was the bulk of OccupantController's line count. Pure
 * functions of model + property — no request, no session — so they are trivial
 * to unit-test and reusable wherever an occupant row is shown.
 */
final class OccupantPresenter
{
    /**
     * @return array{id: int, name: string, slug: string}
     */
    public static function property(Property $property): array
    {
        return $property->only('id', 'name', 'slug');
    }

    /**
     * The occupant's active lease in this property together with the
     * uploaded agreement document (if any) for the edit form.
     */
    public static function activeLease(Occupant $occupant, Property $property): ?array
    {
        $lease = Lease::query()
            ->where('occupant_id', $occupant->id)
            ->where('property_id', $property->id)
            ->where('status', 'active')
            ->first();

        if (! $lease) {
            return null;
        }

        $document = $lease->getFirstMedia('agreement');

        return [
            'starts_at' => $lease->starts_at?->toDateString(),
            'rent_amount' => $lease->rent_amount,
            'rent_frequency' => $lease->rent_frequency,
            'rent_due_day' => $lease->rent_due_day,
            'deposit' => $lease->deposit,
            'currency' => $lease->currency,
            'agreement_text' => $lease->agreement_text,
            'agreement_document_name' => $document?->file_name,
            'agreement_document_url' => $document?->getUrl(),
        ];
    }

    /**
     * The lease as the read-only page shows it.
     *
     * `agreement_text` is dropped: it is the full rendered contract and already
     * has its own page. Shipping it here would put a wall of HTML in every show
     * response for a field the page never renders.
     *
     * @return array<string, mixed>|null
     */
    public static function leaseSummary(Occupant $occupant, Property $property): ?array
    {
        $lease = self::activeLease($occupant, $property);

        if ($lease === null) {
            return null;
        }

        unset($lease['agreement_text']);

        return $lease;
    }

    /**
     * The agreement templates offered on the occupant form, grouped by
     * scope: this property's templates first, then the organization-wide
     * library.
     *
     * @return array{property: array<int, array{id: int, name: string, body_html: string}>, organization: array<int, array{id: int, name: string, body_html: string}>}
     */
    public static function agreementTemplates(Property $property): array
    {
        return [
            'property' => AgreementTemplate::query()
                ->where('organization_id', $property->organization_id)
                ->where('property_id', $property->id)
                ->orderBy('name')
                ->get(['id', 'name', 'body_html'])
                ->map(fn (AgreementTemplate $template) => [
                    'id' => $template->id,
                    'name' => $template->name,
                    'body_html' => $template->body_html,
                ])
                ->all(),
            'organization' => AgreementTemplate::query()
                ->where('organization_id', $property->organization_id)
                ->whereNull('property_id')
                ->orderBy('name')
                ->get(['id', 'name', 'body_html'])
                ->map(fn (AgreementTemplate $template) => [
                    'id' => $template->id,
                    'name' => $template->name,
                    'body_html' => $template->body_html,
                ])
                ->all(),
        ];
    }

    /**
     * @return array<int, array{id: int, name: string, type: string|null, status: string}>
     */
    public static function units(Property $property): array
    {
        return $property->units()
            ->orderBy('name')
            ->get(['id', 'name', 'type', 'status'])
            ->map(fn ($unit) => [
                'id' => $unit->id,
                'name' => $unit->name,
                'type' => $unit->type,
                'status' => $unit->status,
            ])
            ->all();
    }

    /**
     * The occupant addressed by `?occupant=`, carrying the fields the view
     * sheet shows and list rows do not need: gender, the date added, and the
     * active lease.
     *
     * Scoped to the property exactly like the index query, so an id belonging to
     * another property — or another organization — resolves to null and the
     * sheet does not open, instead of leaking a record through the URL.
     *
     * @return array<string, mixed>|null
     */
    public static function focusedOccupant(?int $id, Property $property): ?array
    {
        if ($id === null || $id <= 0) {
            return null;
        }

        $occupant = Occupant::query()
            ->where('organization_id', $property->organization_id)
            ->whereKey($id)
            ->whereHas('units', fn ($query) => $query->where('units.property_id', $property->id))
            ->with(['person', 'units' => fn ($query) => $query->where('units.property_id', $property->id)])
            ->first();

        if (! $occupant) {
            return null;
        }

        return [
            ...self::present($occupant, $property),
            'gender' => $occupant->person->gender,
            'added_at' => $occupant->created_at?->toDateString(),
            'lease' => self::leaseSummary($occupant, $property),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function present(Occupant $occupant, Property $property): array
    {
        return [
            'id' => $occupant->id,
            'first_name' => $occupant->person->first_name,
            'last_name' => $occupant->person->last_name,
            'email' => $occupant->person->email,
            'phone' => $occupant->person->phone,
            'national_id' => $occupant->person->national_id,
            'status' => $occupant->status,
            'units' => $occupant->units
                ->map(fn ($unit) => [
                    'id' => $unit->id,
                    'name' => $unit->name,
                    'type' => $unit->type,
                    'status' => $unit->status,
                ])
                ->all(),
        ];
    }
}
