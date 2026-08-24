<?php

use App\Models\Organization;
use App\Models\Tenant;
use App\Support\TenancyContext;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
});

afterEach(function () {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
});

test('tenant factory produces a registry row', function () {
    expect($this->tenant->exists)->toBeTrue()
        ->and($this->tenant->getKey())->toBeString();
});

test('domain identification initializes tenancy for the matching tenant', function () {
    $this->tenant->domains()->create(['domain' => 'acme.malimanager.test']);

    $this->get('http://acme.malimanager.test/tenant/status')
        ->assertOk()
        ->assertJson([
            'initialized' => true,
            'tenant_id' => $this->tenant->getKey(),
        ]);
});

test('central domain is blocked from tenant routes', function () {
    $this->get('http://malimanager.test/tenant/status')
        ->assertStatus(404);
});

test('unknown organization subdomain redirects to login with an error toast', function () {
    $centralLogin = 'http://'.config('tenancy.subdomain_base').parse_url(route('login'), PHP_URL_PATH);

    $response = $this->get('http://does-not-exist.malimanager.test/tenant/status');
    $response->assertRedirect($centralLogin);
    assertErrorToast();

    $response = $this->get('http://does-not-exist.malimanager.test/lucius-joyce/occupants');
    $response->assertRedirect($centralLogin);
    assertErrorToast();
});

test('tenancy context resolves the tenant organization', function () {
    $organization = Organization::factory()->for($this->tenant, 'tenant')->create();

    tenancy()->initialize($this->tenant);

    expect(TenancyContext::tenantId())->toBe($this->tenant->getKey())
        ->and(TenancyContext::organization()->id)->toBe($organization->id);
});

test('belongs-to-tenant trait scopes queries when tenancy is initialized', function () {
    $this->markTestSkipped('Tenant-scoped domain models arrive in Phase 2.');
});
