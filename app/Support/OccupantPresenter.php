<?php

namespace App\Support;

use App\Enums\MoveOutNoticeStatus;
use App\Models\AgreementTemplate;
use App\Models\DepositRefund;
use App\Models\Lease;
use App\Models\MoveOutNotice;
use App\Models\Occupant;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Property;
use App\Models\User;
use App\Services\RentArrearsService;

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

        return self::presentAgreementLease($lease);
    }

    /**
     * The lease the occupant view sheet links for the agreement: the
     * active one when it carries the agreement, else the pending booking,
     * else the most recent ended lease that does. Ended tenancies keep
     * printable history, so the button must not die with the lease. The
     * agreement page resolves through this same rule, so the link never
     * 404s on an empty current lease while an older one prints.
     */
    public static function resolveAgreementLease(Occupant $occupant, Property $property): ?Lease
    {
        $current = Lease::query()
            ->where('occupant_id', $occupant->id)
            ->where('property_id', $property->id)
            ->whereIn('status', ['active', 'pending'])
            ->with(['unit:id,name', 'property:id,name,organization_id', 'property.organization:id,name'])
            ->orderByRaw("CASE status WHEN 'active' THEN 0 ELSE 1 END")
            ->orderByDesc('id')
            ->first();

        if ($current instanceof Lease && self::leaseHasAgreement($current)) {
            return $current;
        }

        $ended = Lease::query()
            ->where('occupant_id', $occupant->id)
            ->where('property_id', $property->id)
            ->where('status', 'ended')
            ->with(['unit:id,name', 'property:id,name,organization_id', 'property.organization:id,name'])
            ->orderByDesc('id')
            ->first();

        if ($ended instanceof Lease && self::leaseHasAgreement($ended)) {
            return $ended;
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function latestLeaseWithAgreement(Occupant $occupant, Property $property): ?array
    {
        $lease = self::resolveAgreementLease($occupant, $property);

        if (! $lease instanceof Lease) {
            return null;
        }

        return self::presentAgreementLease($lease);
    }

    /**
     * An authored agreement survives as text or an uploaded document.
     * An untouched editor posts `<p></p>`, which counts as no agreement.
     */
    private static function leaseHasAgreement(Lease $lease): bool
    {
        $text = trim((string) $lease->agreement_text);

        if ($text !== '' && $text !== '<p></p>') {
            return true;
        }

        return $lease->getFirstMedia('agreement') !== null;
    }

    /**
     * @return array<string, mixed>
     */
    private static function presentAgreementLease(Lease $lease): array
    {
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
     * The occupant's story in one chronological list, newest first:
     * tenancies started and ended, rent received, refunds filed, and
     * move-out notices. Every event carries its unit so the sheet can
     * filter one tenancy's story out of a multi-unit history. Capped:
     * this is a sheet, not a report.
     *
     * @return list<array{date: string|null, kind: string, title: string, detail: string|null, amount: string|null, currency: string|null, status: string|null, unit_id: int|null, unit_name: string|null}>
     */
    public static function history(Occupant $occupant, Property $property): array
    {
        $leaseIds = Lease::query()
            ->where('person_id', $occupant->person_id)
            ->where('property_id', $property->id)
            ->pluck('id');

        if ($leaseIds->isEmpty()) {
            return [];
        }

        $events = [];

        $leases = Lease::query()
            ->whereKey($leaseIds)
            ->with('unit:id,name')
            ->orderByDesc('starts_at')
            ->get();

        $unitsByLease = $leases->mapWithKeys(fn (Lease $lease) => [
            $lease->id => ['id' => $lease->unit_id, 'name' => $lease->unit?->name],
        ])->all();

        foreach ($leases as $lease) {
            $unit = $lease->unit?->name ?? 'a unit';
            $rate = $lease->rent_amount !== null
                ? number_format((float) $lease->rent_amount, 2).' '.($lease->currency ?? '')
                : null;

            $events[] = [
                'date' => $lease->starts_at?->toDateString(),
                'kind' => 'lease_started',
                'title' => "Moved into {$unit}",
                'detail' => $rate,
                'amount' => null,
                'currency' => null,
                'status' => null,
                'unit_id' => $lease->unit_id,
                'unit_name' => $lease->unit?->name,
            ];

            if ($lease->status === 'ended') {
                $events[] = [
                    'date' => $lease->ends_at?->toDateString(),
                    'kind' => 'lease_ended',
                    'title' => "Left {$unit}",
                    'detail' => $rate,
                    'amount' => null,
                    'currency' => null,
                    'status' => 'ended',
                    'unit_id' => $lease->unit_id,
                    'unit_name' => $lease->unit?->name,
                ];
            }

            foreach (MoveOutNotice::query()->where('lease_id', $lease->id)->orderByDesc('move_out_date')->get() as $notice) {
                $events[] = [
                    'date' => $notice->move_out_date?->toDateString(),
                    'kind' => 'move_out_notice',
                    'title' => "Notice to leave {$unit}",
                    'detail' => $notice->note,
                    'amount' => null,
                    'currency' => null,
                    'status' => $notice->status instanceof MoveOutNoticeStatus ? $notice->status->value : (string) $notice->status,
                    'unit_id' => $lease->unit_id,
                    'unit_name' => $lease->unit?->name,
                ];
            }

            foreach (DepositRefund::query()->where('lease_id', $lease->id)->orderByDesc('id')->get() as $refund) {
                $events[] = [
                    'date' => $refund->created_at?->toDateString(),
                    'kind' => 'refund',
                    'title' => "Deposit refund {$refund->status->value}",
                    'detail' => "for {$unit}",
                    'amount' => number_format($refund->amount_cents / 100, 2, '.', ''),
                    'currency' => $refund->currency,
                    'status' => $refund->status->value,
                    'unit_id' => $lease->unit_id,
                    'unit_name' => $lease->unit?->name,
                ];
            }
        }

        foreach (Payment::query()->whereIn('lease_id', $leaseIds)->whereIn('status', ['received', 'refunded'])->orderByDesc('paid_on')->get() as $payment) {
            $unit = $unitsByLease[$payment->lease_id] ?? ['id' => null, 'name' => null];

            $events[] = [
                'date' => $payment->paid_on?->toDateString(),
                'kind' => 'payment',
                'title' => $payment->status === 'refunded' ? 'Payment refunded' : ucfirst((string) $payment->type).' received',
                'detail' => $payment->method ? "via {$payment->method}" : null,
                'amount' => (string) $payment->amount,
                'currency' => $payment->currency,
                'status' => $payment->status,
                'unit_id' => $unit['id'],
                'unit_name' => $unit['name'],
            ];
        }

        usort($events, fn (array $a, array $b): int => strcmp((string) ($b['date'] ?? ''), (string) ($a['date'] ?? '')));

        return array_slice(array_values($events), 0, 30);
    }

    /**
     * Every tenancy in this property, active first, for the sheet's lease
     * switcher. Each carries its live balance so the sheet can remind and
     * link without another request.
     *
     * @return list<array{id: int, unit_id: int|null, unit_name: string|null, status: string, starts_at: string|null, ends_at: string|null, rent_amount: string|float|null, currency: string|null, outstanding: string, is_overdue: bool, has_agreement: bool, agreement_document_name: string|null, agreement_document_url: string|null}>
     */
    public static function leasesForSheet(Occupant $occupant, Property $property): array
    {
        $leases = Lease::query()
            ->where('occupant_id', $occupant->id)
            ->where('property_id', $property->id)
            ->with('unit:id,name')
            ->with(['media' => fn ($query) => $query->where('collection_name', 'agreement')])
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 WHEN status = 'pending' THEN 1 ELSE 2 END")
            ->orderByDesc('starts_at')
            ->get();

        if ($leases->isEmpty()) {
            return [];
        }

        $balances = app(RentArrearsService::class)->balancesFor($leases);

        return $leases
            ->map(function (Lease $lease) use ($balances) {
                $document = $lease->getRelation('media')->first();

                return [
                    'id' => $lease->id,
                    'unit_id' => $lease->unit_id,
                    'unit_name' => $lease->unit?->name,
                    'status' => $lease->status,
                    'starts_at' => $lease->starts_at?->toDateString(),
                    'ends_at' => $lease->ends_at?->toDateString(),
                    'rent_amount' => $lease->rent_amount,
                    'currency' => $lease->currency,
                    'outstanding' => $balances[$lease->id]['outstanding'] ?? '0.00',
                    'is_overdue' => $balances[$lease->id]['overdue'] ?? false,
                    'has_agreement' => self::leaseHasAgreement($lease),
                    'agreement_document_name' => $document?->file_name,
                    'agreement_document_url' => $document?->getUrl(),
                ];
            })
            ->all();
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
     * @return array<int, array{id: int, name: string, type: string|null, status: string, rent_amount: string|null, rent_frequency: string|null}>
     */
    public static function units(Property $property): array
    {
        return $property->units()
            ->orderBy('name')
            ->get(['id', 'name', 'type', 'status', 'rent_amount', 'rent_frequency'])
            ->map(fn ($unit) => [
                'id' => $unit->id,
                'name' => $unit->name,
                'type' => $unit->type,
                'status' => $unit->status,
                'rent_amount' => $unit->rent_amount,
                'rent_frequency' => $unit->rent_frequency,
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
    public static function focusedOccupant(?int $id, Property $property, ?User $actor = null, ?Organization $organization = null): ?array
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
            ...self::present($occupant, $property, $actor, $organization),
            'gender' => $occupant->person->gender,
            'added_at' => $occupant->created_at?->toDateString(),
            // Every unit this person holds in the property, across all of
            // their rows: the edit sheet's unit picker works on the whole
            // set (unchecking vacates that row, adding creates a new one).
            'all_unit_ids' => $occupant->person->occupants()
                ->where('occupants.organization_id', $property->organization_id)
                ->with(['units' => fn ($query) => $query->where('units.property_id', $property->id)->select('units.id')])
                ->get()
                ->flatMap(fn (Occupant $row) => $row->units->pluck('id'))
                ->unique()
                ->values()
                ->all(),
            // The full lease including agreement_text: the edit sheet loads
            // it into the editor, and the view sheet simply ignores the extra
            // fields.
            'lease' => self::activeLease($occupant, $property),
            // Whatever carries the printable agreement (active or ended):
            // the view sheet's Agreement button reads only this.
            'agreement_lease' => self::latestLeaseWithAgreement($occupant, $property),
            // Bookings waiting on move-in: the view sheet offers the move-in
            // step from these, so they ride along only on the focused record.
            'pending_leases' => self::pendingLeases($occupant, $property),
            // Every tenancy for the sheet's lease switcher, active first.
            'leases' => self::leasesForSheet($occupant, $property),
            // The story so far, newest first.
            'history' => self::history($occupant, $property),
        ];
    }

    /**
     * Leases booked but not yet started in this property, with the figures
     * the move-in step collects: rent terms and the deposit due.
     *
     * @return list<array{lease_id: int, unit_id: int|null, unit_name: string|null, rent_amount: string|null, currency: string|null, deposit: string|null}>
     */
    public static function pendingLeases(Occupant $occupant, Property $property): array
    {
        return Lease::query()
            ->where('person_id', $occupant->person_id)
            ->where('property_id', $property->id)
            ->where('status', 'pending')
            ->whereNull('ends_at')
            ->with('unit:id,name')
            ->orderBy('unit_id')
            ->get()
            ->map(fn (Lease $lease): array => [
                'lease_id' => $lease->id,
                'unit_id' => $lease->unit_id,
                'unit_name' => $lease->unit?->name,
                'rent_amount' => $lease->rent_amount,
                'currency' => $lease->currency,
                'deposit' => $lease->deposit,
            ])
            ->all();
    }

    /**
     * @param  array{attention: array{lease_id: int, outstanding: string, currency: string|null}|null, active_lease_id: int|null}|null  $handles
     * @return array<string, mixed>
     */
    public static function present(Occupant $occupant, Property $property, ?User $actor = null, ?Organization $organization = null, ?array $handles = null): array
    {
        $org = $organization ?? $property->organization;

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
                    'rent_amount' => $unit->rent_amount,
                ])
                ->all(),
            'deletable' => $actor !== null ? DeletionTier::allows($actor, $org, $occupant) : true,
            // The row overflow offers the agreement link without a second
            // request; the sheet resolves the full lease itself.
            'has_agreement' => (bool) ($occupant->has_agreement ?? false),
            // The row's one-tap remind: a single overdue lease and its
            // balance; anything else defers to the sheet.
            'attention' => $handles['attention'] ?? null,
            // The row's live tenancy for the overflow's End lease deep
            // link; null once nothing is active.
            'active_lease_id' => $handles['active_lease_id'] ?? null,
        ];
    }
}
