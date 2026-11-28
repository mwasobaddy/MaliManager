<?php

use App\Actions\AgreementTemplates\CreateDefaultAgreementTemplate;
use App\Models\AgreementTemplate;
use App\Models\Lease;
use App\Models\Occupant;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Property;
use App\Models\User;
use App\Services\OccupantService;
use App\Services\TenantService;
use App\Support\LeaseAgreementTemplate;
use Database\Seeders\PlansSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    (new RolesAndPermissionsSeeder)->run();
    (new PlansSeeder)->run();
});

function agreementOrg(User $owner): Organization
{
    return app(TenantService::class)->createOrganization(
        owner: $owner,
        name: 'Agreement Estates',
        plan: Plan::where('slug', 'free')->firstOrFail(),
    );
}

function agreementProperty(Organization $organization, User $owner): Property
{
    return $organization->properties()->create([
        'name' => 'Agreement Court',
        'slug' => 'agreement-court',
        'status' => 'active',
        'created_by' => $owner->id,
    ]);
}

function agreementLease(Organization $organization, User $owner, Property $property, string $unitName, array $leaseData = []): Lease
{
    $unit = $property->units()->create(['name' => $unitName, 'status' => 'vacant', 'created_by' => $owner->id]);
    $occupant = app(OccupantService::class)->create($organization, $property, $owner, [
        'first_name' => 'Jane',
        'last_name' => "Lease-{$unitName}",
        'email' => "jane-lease-{$unitName}@x.test",
        'status' => 'active',
        'unit_ids' => [$unit->id],
        'lease' => array_merge([
            'starts_at' => '2026-01-01',
            'rent_amount' => '10000',
            'rent_frequency' => 'monthly',
            'rent_due_day' => 5,
            'currency' => 'KES',
        ], $leaseData),
    ]);

    return Lease::where('unit_id', $unit->id)->firstOrFail();
}

test('a new organization ships the default agreement template', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = agreementOrg($owner);

    $template = AgreementTemplate::where('organization_id', $organization->id)
        ->whereNull('property_id')
        ->firstOrFail();

    expect($template->name)->toBe(CreateDefaultAgreementTemplate::NAME)
        ->and($template->created_by)->toBe($owner->id)
        ->and($template->body_html)->toContain('{{occupant_name}}')
        ->and($template->body_html)->toContain('{{rent_amount}}')
        ->and($template->body_html)->not->toContain('{{end_date}}');
});

test('the default is idempotent and respects an existing org library', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = agreementOrg($owner);

    // Creation already shipped one; ensuring again adds nothing.
    app(CreateDefaultAgreementTemplate::class)->ensureForOrg($organization, $owner);

    expect(AgreementTemplate::where('organization_id', $organization->id)->whereNull('property_id')->count())->toBe(1);

    // An org with its own template keeps it as the default — no shipped copy.
    $owner2 = User::factory()->create(['onboarded_at' => now()]);
    $organization2 = agreementOrg($owner2);
    AgreementTemplate::where('organization_id', $organization2->id)->whereNull('property_id')->delete();
    $custom = AgreementTemplate::create([
        'organization_id' => $organization2->id,
        'name' => 'Custom Terms',
        'body_html' => '<p>Custom</p>',
        'created_by' => $owner2->id,
    ]);

    $resolved = app(CreateDefaultAgreementTemplate::class)->ensureForOrg($organization2, $owner2);

    expect($resolved->id)->toBe($custom->id)
        ->and(AgreementTemplate::where('organization_id', $organization2->id)->whereNull('property_id')->count())->toBe(1);
});

test('quick add attaches the default agreement with zero template decisions', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = agreementOrg($owner);
    $property = agreementProperty($organization, $owner);
    $unitId = $property->units()->create(['name' => 'A1', 'status' => 'vacant', 'created_by' => $owner->id])->id;

    app(OccupantService::class)->create($organization, $property, $owner, [
        'first_name' => 'John',
        'phone' => '0712000009',
        'status' => 'active',
        'unit_ids' => [$unitId],
        'move_in_now' => true,
        'lease' => ['rent_amount' => 5000, 'rent_frequency' => 'monthly', 'currency' => 'KES'],
    ]);

    $lease = Lease::where('unit_id', $unitId)->firstOrFail();
    $default = AgreementTemplate::where('organization_id', $organization->id)->whereNull('property_id')->firstOrFail();

    expect($lease->agreement_text)->toBe($default->body_html);

    // The merged text prints with every token resolved.
    $rendered = LeaseAgreementTemplate::render($lease->agreement_text, $lease);

    expect($rendered)->toContain('John')
        ->and($rendered)->toContain('5,000.00')
        ->and($rendered)->not->toContain('{{');
});

test('an untouched full-form editor gets the default but authored text wins', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = agreementOrg($owner);
    $property = agreementProperty($organization, $owner);
    $default = AgreementTemplate::where('organization_id', $organization->id)->whereNull('property_id')->firstOrFail();

    $blank = agreementLease($organization, $owner, $property, 'A1', ['agreement_text' => '<p></p>']);
    expect($blank->agreement_text)->toBe($default->body_html);

    $authored = agreementLease($organization, $owner, $property, 'A2', ['agreement_text' => '<p>Negotiated terms.</p>']);
    expect($authored->agreement_text)->toBe('<p>Negotiated terms.</p>');
});

test('a lease always carries a currency, falling back to the org default', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = agreementOrg($owner);
    $organization->settings = array_merge($organization->settings ?? [], ['currency' => 'TZS']);
    $organization->save();
    $property = agreementProperty($organization, $owner);

    $lease = agreementLease($organization, $owner, $property, 'A1', ['currency' => null]);

    expect($lease->currency)->toBe('TZS');
});

test('amending rent can schedule an increment with a date-Y preview', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = agreementOrg($owner);
    $property = agreementProperty($organization, $owner);
    $lease = agreementLease($organization, $owner, $property, 'A1');
    $domain = $organization->tenant->domains()->value('domain');

    $this->actingAs($owner)
        ->post("http://{$domain}/{$property->slug}/leases/{$lease->id}/amend-rent", [
            'rent_amount' => '10000',
            'effective_date' => '2026-06-01',
            'increment_percent' => '5',
            'increment_effective_date' => '2027-01-01',
        ])
        ->assertRedirect();

    $lease = $lease->fresh();

    expect((float) $lease->rent_increment_schedule['annual_percent'])->toBe(5.0)
        ->and($lease->rent_increment_schedule['effective_date'])->toBe('2027-01-01')
        ->and($lease->incrementPreview())->toBe(['amount' => '10500.00', 'effective_date' => '2027-01-01', 'annual_percent' => 5.0]);
});

test('emptying the percent clears the schedule, and a percent needs its date', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = agreementOrg($owner);
    $property = agreementProperty($organization, $owner);
    $lease = agreementLease($organization, $owner, $property, 'A1');
    $domain = $organization->tenant->domains()->value('domain');
    $url = "http://{$domain}/{$property->slug}/leases/{$lease->id}/amend-rent";

    $this->actingAs($owner)->post($url, [
        'rent_amount' => '10000',
        'effective_date' => '2026-06-01',
        'increment_percent' => '10',
        'increment_effective_date' => '2027-01-01',
    ])->assertRedirect();
    expect($lease->fresh()->rent_increment_schedule)->not->toBeNull();

    // Plain amendment (nulls from the emptied inputs) clears it.
    $this->actingAs($owner)->post($url, [
        'rent_amount' => '10000',
        'effective_date' => '2026-07-01',
        'increment_percent' => null,
        'increment_effective_date' => null,
    ])->assertRedirect();
    expect($lease->fresh()->rent_increment_schedule)->toBeNull()
        ->and($lease->fresh()->incrementPreview())->toBeNull();

    // Percent without a date is rejected and stores nothing.
    $this->actingAs($owner)
        ->from("http://{$domain}/{$property->slug}/leases")
        ->post($url, [
            'rent_amount' => '10000',
            'effective_date' => '2026-08-01',
            'increment_percent' => '10',
            'increment_effective_date' => null,
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('increment_effective_date');
    expect($lease->fresh()->rent_increment_schedule)->toBeNull();
});

test('the view sheet links the ended agreement when the active lease has none', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = agreementOrg($owner);
    $property = agreementProperty($organization, $owner);
    $domain = $organization->tenant->domains()->value('domain');

    // One row, two sequential tenancies on the same unit: the ended one
    // carries an agreement, the current one does not.
    $lease = agreementLease($organization, $owner, $property, 'A1');
    $occupant = Occupant::where('organization_id', $organization->id)->firstOrFail();
    $ended = $lease->replicate();
    $ended->fill([
        'status' => 'ended',
        'ends_at' => '2026-02-01',
        'agreement_text' => '<p>Old terms for Re.</p>',
    ])->save();
    $lease->fill(['agreement_text' => null])->save();

    // The view sheet offers the ended lease for its Agreement button…
    $this->actingAs($owner)
        ->get("http://{$domain}/{$property->slug}/occupants?mode=view&occupant={$occupant->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('focusedOccupant.agreement_lease.agreement_text', $ended->fresh()->agreement_text)
            ->where('focusedOccupant.lease.agreement_text', null));

    // …and the agreement page still prints the merged contract.
    $this->actingAs($owner)
        ->get("http://{$domain}/{$property->slug}/occupants/{$occupant->id}/agreement")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('organisation/occupants/agreement')
            ->where('occupantName', 'Jane Lease-A1'));
});

test('an occupant with no agreement anywhere gets no link and a 404', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = agreementOrg($owner);
    $property = agreementProperty($organization, $owner);
    $domain = $organization->tenant->domains()->value('domain');

    $unit = $property->units()->create(['name' => 'A1', 'status' => 'vacant', 'created_by' => $owner->id]);
    $occupant = app(OccupantService::class)->create($organization, $property, $owner, [
        'first_name' => 'Bare',
        'last_name' => 'Tenant',
        'email' => 'bare-tenant@x.test',
        'status' => 'active',
        'unit_ids' => [$unit->id],
        'lease' => ['starts_at' => '2026-01-01', 'rent_amount' => '8000', 'rent_frequency' => 'monthly', 'currency' => 'KES'],
    ]);

    Lease::where('unit_id', $unit->id)->update(['agreement_text' => null]);

    $this->actingAs($owner)
        ->get("http://{$domain}/{$property->slug}/occupants?mode=view&occupant={$occupant->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('focusedOccupant.agreement_lease', null));

    $this->actingAs($owner)
        ->get("http://{$domain}/{$property->slug}/occupants/{$occupant->id}/agreement")
        ->assertNotFound();
});

test('list rows carry the agreement flag for the overflow menu', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = agreementOrg($owner);
    $property = agreementProperty($organization, $owner);
    $domain = $organization->tenant->domains()->value('domain');

    $unit = $property->units()->create(['name' => 'A1', 'status' => 'vacant', 'created_by' => $owner->id]);
    $occupant = app(OccupantService::class)->create($organization, $property, $owner, [
        'first_name' => 'Flag',
        'last_name' => 'Tenant',
        'email' => 'flag-tenant@x.test',
        'status' => 'active',
        'unit_ids' => [$unit->id],
        'lease' => ['starts_at' => '2026-01-01', 'rent_amount' => '8000', 'rent_frequency' => 'monthly', 'currency' => 'KES'],
    ]);

    $rows = fn () => $this->actingAs($owner)
        ->get("http://{$domain}/{$property->slug}/occupants")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('organisation/occupants/index'));

    $rows()->assertInertia(fn ($page) => $page
        ->where('occupants.data', fn ($data) => count($data) === 1 && $data[0]['has_agreement'] === true));

    Lease::where('unit_id', $unit->id)->update(['agreement_text' => null]);

    $rows()->assertInertia(fn ($page) => $page
        ->where('occupants.data', fn ($data) => count($data) === 1 && $data[0]['has_agreement'] === false));
});

test('the increment preview stays null without a schedule or rent', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = agreementOrg($owner);
    $property = agreementProperty($organization, $owner);
    $lease = agreementLease($organization, $owner, $property, 'A1');

    expect($lease->incrementPreview())->toBeNull();

    $lease->rent_amount = null;
    $lease->rent_increment_schedule = ['annual_percent' => 5, 'effective_date' => '2027-01-01'];

    expect($lease->incrementPreview())->toBeNull();
});
