<?php

namespace App\Actions\Occupants\Concerns;

use App\Enums\PlatformRole;
use App\Models\AgreementTemplate;
use App\Models\Lease;
use App\Models\Occupant;
use App\Models\Organization;
use App\Models\Person;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use App\Services\PaymentService;
use App\Support\PhoneNumber;
use App\Support\TenancyContext;

trait OccupantHelpers
{
    /**
     * End any active lease for this person on the given unit.
     */
    private function endLease(Occupant $occupant, int $unitId): void
    {
        Lease::where('person_id', $occupant->person_id)
            ->where('unit_id', $unitId)
            ->where('status', 'active')
            ->whereNull('ends_at')
            ->update([
                'ends_at' => now(),
                'status' => 'ended',
            ]);

        $this->refreshUnitStatus($unitId);
    }

    /**
     * Drop bookings that never started: pending leases for this person on
     * the given unit, in the given property, or — without a property —
     * everywhere in the occupant's organization. Never touches other
     * organizations: the person identity is global.
     */
    private function cancelPendingLeases(Occupant $occupant, ?Property $property = null, ?int $unitId = null): void
    {
        $query = Lease::where('person_id', $occupant->person_id)
            ->where('status', 'pending');

        if ($property !== null) {
            $query->where('property_id', $property->id);
        } else {
            $query->where('organization_id', $occupant->organization_id);
        }

        if ($unitId !== null) {
            $query->where('unit_id', $unitId);
        }

        $unitIds = $query->pluck('unit_id')->all();
        $query->delete();

        foreach (array_unique(array_filter($unitIds)) as $id) {
            $this->refreshUnitStatus((int) $id);
        }
    }

    /**
     * Keep the occupant flag truthful: active while the person holds any
     * active lease, inactive otherwise. `moved_out` is terminal and is
     * never recomputed — only an explicit move-out sets it.
     */
    /**
     * Still running: occupants whose leases are all pending are waiting on
     * move-in; anything with a live lease is active. Row-scoped: each
     * tenancy row carries its own status.
     */
    private function refreshOccupantStatus(Occupant $occupant): void
    {
        if ($occupant->status === 'moved_out') {
            return;
        }

        $hasActive = Lease::where('occupant_id', $occupant->id)
            ->where('status', 'active')
            ->whereNull('ends_at')
            ->exists();

        $occupant->fill(['status' => $hasActive ? 'active' : 'inactive'])->save();
    }

    /**
     * Keep the unit's occupancy flag truthful: occupied while any active
     * open-ended lease points at it, vacant otherwise. Units in maintenance
     * are left alone — a repair state is set by staff, not by leasing.
     */
    private function refreshUnitStatus(int $unitId): void
    {
        $unit = Unit::find($unitId);

        if (! $unit || ! in_array($unit->status, ['vacant', 'occupied'], true)) {
            return;
        }

        $occupied = Lease::where('unit_id', $unitId)
            ->where('status', 'active')
            ->whereNull('ends_at')
            ->exists();

        $unit->fill(['status' => $occupied ? 'occupied' : 'vacant'])->save();
    }

    /**
     * The occupant row that should carry a unit assignment: the active row
     * already holding it (idempotent re-submits), else a unit-less row of
     * the same person in this property (a prospect becoming a tenancy),
     * else a fresh row. Moved-out rows never come back — a re-let is a
     * new tenancy with its own history.
     */
    private function resolveRowForUnit(Organization $organization, Person $person, Property $property, int $unitId, User $actor, bool $moveIn): Occupant
    {
        $row = Occupant::query()
            ->where('organization_id', $organization->id)
            ->where('person_id', $person->id)
            ->where('status', 'active')
            ->whereHas('units', fn ($query) => $query->where('units.id', $unitId))
            ->first();

        if ($row instanceof Occupant) {
            $row->fill(['status' => $moveIn ? 'active' : 'inactive']);

            return $row;
        }

        $prospect = Occupant::query()
            ->where('organization_id', $organization->id)
            ->where('person_id', $person->id)
            ->where('status', '!=', 'moved_out')
            ->whereDoesntHave('units', fn ($query) => $query->where('units.property_id', $property->id))
            ->orderBy('id')
            ->first();

        if ($prospect instanceof Occupant) {
            $prospect->fill([
                'status' => $moveIn ? 'active' : 'inactive',
                'created_by' => $actor->id,
            ]);
            $prospect->deleted_at = null;

            return $prospect;
        }

        return new Occupant([
            'organization_id' => $organization->id,
            'person_id' => $person->id,
            'status' => $moveIn ? 'active' : 'inactive',
            'created_by' => $actor->id,
        ]);
    }

    /**
     * Create a lease for a newly assigned unit, unless one is already active.
     * Bookings arrive as `pending` (no accrual, no occupancy) and flip to
     * `active` at move-in; an active occupant's new units start active.
     *
     * @param  array<string, mixed>  $leaseData
     */
    private function ensureLease(Occupant $occupant, Property $property, int $unitId, User $actor, array $leaseData, string $status = 'active'): ?Lease
    {
        $exists = Lease::where('person_id', $occupant->person_id)
            ->where('unit_id', $unitId)
            ->whereIn('status', ['active', 'pending'])
            ->whereNull('ends_at')
            ->exists();

        if ($exists) {
            return null;
        }

        $lease = new Lease([
            'person_id' => $occupant->person_id,
            'organization_id' => $property->organization_id,
            'tenant_id' => TenancyContext::tenantId(),
            'property_id' => $property->id,
            'unit_id' => $unitId,
            'occupant_id' => $occupant->id,
            'starts_at' => $leaseData['starts_at'] ?? now(),
            'rent_amount' => $leaseData['rent_amount'] ?? null,
            'rent_frequency' => $leaseData['rent_frequency'] ?? null,
            'rent_due_day' => $leaseData['rent_due_day'] ?? null,
            'rent_due_weekday' => $leaseData['rent_due_weekday'] ?? null,
            'rent_due_month' => $leaseData['rent_due_month'] ?? null,
            // A lease always carries a currency: the form's pick wins, else
            // the org default, else KES — nothing downstream may assume null.
            'currency' => $leaseData['currency'] ?? $property->organization->settings['currency'] ?? 'KES',
            'deposit' => $leaseData['deposit'] ?? null,
            // Quick add (and an untouched full-form editor) posts no
            // agreement: attach the org default so the tenant prints with
            // zero template decisions. An authored text is left alone.
            'agreement_text' => $this->agreementText($property, $leaseData['agreement_text'] ?? null),
            'status' => $status,
            'created_by' => $actor->id,
        ]);

        $lease->save();

        // Seed the inception row so every billed period has exactly one
        // rate, even before the first amendment. Rent-less bookings seed
        // 0.00, matching what arrears charges them today.
        $lease->rentChanges()->create([
            'organization_id' => $property->organization_id,
            'effective_date' => $lease->starts_at?->toDateString() ?? now()->toDateString(),
            'rent_amount' => $lease->rent_amount ?? 0,
            'created_by' => $actor->id,
        ]);

        $this->refreshUnitStatus($unitId);

        return $lease;
    }

    /**
     * The agreement a new lease carries: authored text wins, otherwise the
     * org default (its oldest org-wide template — the copy shipped at
     * creation). An untouched TipTap editor posts `<p></p>`, which counts
     * as blank here.
     */
    private function agreementText(Property $property, ?string $authored): ?string
    {
        if ($authored !== null && trim($authored) !== '' && trim($authored) !== '<p></p>') {
            return $authored;
        }

        return AgreementTemplate::query()
            ->where('organization_id', $property->organization_id)
            ->whereNull('property_id')
            ->orderBy('id')
            ->value('body_html');
    }

    /**
     * Find (by email, else by phone) or create the global Person record.
     * Phone-matched people keep their email empty until someone provides
     * one; the account link is made then, not now.
     */
    private function findOrCreatePerson(array $data, ?User $user, User $actor): Person
    {
        $person = null;

        if (! empty($data['email'])) {
            $person = Person::where('email', $data['email'])->first();
        }

        if ($person === null && ! empty($data['phone'])) {
            $phone = PhoneNumber::normalize((string) $data['phone']) ?? (string) $data['phone'];
            $person = Person::whereNull('email')->where('phone', $phone)->first();
        }

        if (! $person) {
            $person = new Person([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'] ?? null,
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'national_id' => $data['national_id'] ?? null,
                'gender' => $data['gender'] ?? null,
                'status' => 'active',
                'created_by' => $actor->id,
            ]);

            $person = $this->save($person);
        } else {
            $person->fill([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'] ?? $person->last_name,
                'email' => $data['email'] ?? $person->email,
                'phone' => $data['phone'] ?? $person->phone,
                'national_id' => $data['national_id'] ?? $person->national_id,
                'gender' => $data['gender'] ?? $person->gender,
            ])->save();
        }

        if ($user !== null && ! $user->person_id) {
            $user->fill(['person_id' => $person->id])->save();
        }

        return $person;
    }

    /**
     * Find the user account for an occupant, creating it if needed. Email
     * accounts dedupe globally by address; phone-only accounts dedupe by
     * number and get a non-routable placeholder address until a real email
     * is known, with SMS as the channel so reminders still reach them.
     */
    private function findOrCreateUser(array $data): User
    {
        if (! empty($data['email'])) {
            return $this->findOrCreateEmailUser($data);
        }

        $phone = PhoneNumber::normalize((string) ($data['phone'] ?? ''));

        if ($phone !== null && ($existing = User::where('phone', $phone)->first()) !== null) {
            $existing->assignRole(PlatformRole::Occupant->value);

            return $existing;
        }

        $user = new User([
            'name' => trim(($data['first_name'] ?? '').' '.($data['last_name'] ?? '')) ?: ($phone ?? 'Tenant'),
            'email' => $phone !== null ? ltrim($phone, '+').'@phone.invalid' : null,
            'phone' => $phone,
            'notification_channel' => 'sms',
            'onboarded_at' => now(),
            'status' => 'active',
        ]);

        $user = $this->save($user);
        $user->assignRole(PlatformRole::Occupant->value);

        return $user;
    }

    private function findOrCreateEmailUser(array $data): User
    {
        $user = User::where('email', $data['email'])->first();

        if ($user) {
            $user->fill([
                'name' => trim(($data['first_name'] ?? '').' '.($data['last_name'] ?? '')),
                'phone' => $data['phone'] ?? $user->phone,
                'onboarded_at' => $user->onboarded_at ?? now(),
            ])->save();

            $user->assignRole(PlatformRole::Occupant->value);

            return $user;
        }

        $user = new User([
            'name' => trim(($data['first_name'] ?? '').' '.($data['last_name'] ?? '')),
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'onboarded_at' => now(),
            'status' => 'active',
        ]);

        $user = $this->save($user);
        $user->assignRole(PlatformRole::Occupant->value);

        return $user;
    }

    /**
     * The move-in consideration for freshly activated leases: the deposit
     * plus the first period's rent on each lease. The payment method
     * defaults to cash so unattended callers keep the previous one-step
     * behaviour; the sheet always sends an explicit method.
     *
     * @param  list<Lease>  $leases
     * @param  array<string, mixed>  $data
     */
    private function recordMoveInPayments(Occupant $occupant, Property $property, User $actor, array $leases, array $data): void
    {
        $moveInDate = $data['move_in_date'] ?? now()->toDateString();

        foreach ($leases as $lease) {
            abort_unless($lease->rent_amount !== null, 422, "Unit {$lease->unit?->name} has no rent set — move-in needs a first period amount.");

            if ((float) ($lease->deposit ?? 0) > 0) {
                $this->createMoveInPayment($occupant, $property, $actor, $lease, (string) $lease->deposit, 'deposit', $moveInDate, $data);
            }

            $this->createMoveInPayment($occupant, $property, $actor, $lease, (string) $lease->rent_amount, 'rent', $moveInDate, $data);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createMoveInPayment(Occupant $occupant, Property $property, User $actor, Lease $lease, string $amount, string $type, string $moveInDate, array $data): void
    {
        app(PaymentService::class)->create($actor, (int) $property->organization_id, [
            'lease_id' => $lease->id,
            'amount' => $amount,
            'currency' => $lease->currency ?? 'KES',
            'paid_on' => $moveInDate,
            'period_start' => $moveInDate,
            'method' => $data['payment_method'] ?? 'cash',
            'reference' => $data['payment_reference'] ?? null,
            'type' => $type,
        ]);
    }

    /**
     * If the person has no active lease anywhere, remove the `occupant`
     * platform role from any linked user accounts.
     */
    private function stripOccupantRoleIfNoActiveLeases(Person $person): void
    {
        $hasActive = Lease::where('person_id', $person->id)
            ->where('status', 'active')
            ->whereNull('ends_at')
            ->exists();

        if ($hasActive) {
            return;
        }

        User::where('person_id', $person->id)
            ->whereHas('roles', fn ($query) => $query->where('name', PlatformRole::Occupant->value))
            ->get()
            ->each(fn (User $user) => $user->removeRole(PlatformRole::Occupant->value));
    }

    /**
     * Sync an occupant's unit assignments to the units selected,
     * constrained to the given property. Creates a Lease for each newly
     * assigned unit and ends the Lease for any unit the occupant leaves.
     * Leases that never started are cancelled instead of ended. When the
     * person no longer holds any active lease, their `occupant` platform
     * role is stripped (they revert to a `searcher`) and a non-terminal
     * occupant status falls back to inactive.
     *
     * New leases arrive with the given status: `pending` for bookings,
     * `active` for an occupant who is already moved in.
     *
     * @param  array<string, mixed>  $leaseData
     * @return list<Lease> the leases created by this sync
     */
    private function syncUnitAssignments(Occupant $occupant, Property $property, array $unitIds, User $actor, array $leaseData = [], string $leaseStatus = 'active'): array
    {
        $unitIds = array_map('intval', array_unique($unitIds));

        $validIds = Unit::where('property_id', $property->id)
            ->whereIn('id', $unitIds)
            ->pluck('id')
            ->all();

        $currentIds = $occupant->units()
            ->where('units.property_id', $property->id)
            ->pluck('units.id')
            ->all();

        foreach (array_diff($currentIds, $validIds) as $detachedId) {
            $this->endLease($occupant, $detachedId);
            $this->cancelPendingLeases($occupant, $property, $detachedId);
        }

        $occupant->units()
            ->where('units.property_id', $property->id)
            ->detach();

        $created = [];

        foreach ($validIds as $unitId) {
            $occupant->units()->attach($unitId, ['created_by' => $actor->id]);
            $lease = $this->ensureLease($occupant, $property, $unitId, $actor, $leaseData, $leaseStatus);

            if ($lease !== null) {
                $created[] = $lease;
            }
        }

        $this->stripOccupantRoleIfNoActiveLeases($occupant->person);
        $this->refreshOccupantStatus($occupant->refresh());

        return $created;
    }
}
