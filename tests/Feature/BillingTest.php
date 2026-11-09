<?php

use App\Mail\BillingReminderMail;
use App\Models\Organization;
use App\Models\PaymentAttempt;
use App\Models\Plan;
use App\Models\User;
use App\Services\PaystackClient;
use App\Services\SmsClient;
use App\Services\TenantService;
use Botnetdobbs\Mpesa\Contracts\Client;
use Botnetdobbs\Mpesa\Contracts\Response;
use Database\Seeders\PlansSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    (new RolesAndPermissionsSeeder)->run();
    (new PlansSeeder)->run();
});

function createBillingOrganization(User $owner, string $planSlug = 'starter'): Organization
{
    return app(TenantService::class)->createOrganization(
        owner: $owner,
        name: 'Billing Estates',
        plan: Plan::where('slug', $planSlug)->firstOrFail(),
    );
}

function billingTenantUrl(Organization $organization, string $path = ''): string
{
    $domain = $organization->tenant->domains()->value('domain');

    return "http://{$domain}{$path}";
}

class FakeMpesaResponse implements Response
{
    public function __construct(
        private bool $ok,
        private object $data,
        private string $description = '',
    ) {}

    public function getData(): object
    {
        return $this->data;
    }

    public function isSuccessful(): bool
    {
        return $this->ok;
    }

    public function getResponseCode(): int
    {
        return $this->ok ? 0 : 1;
    }

    public function getResponseDescription(): string
    {
        return $this->description;
    }

    public function getResultCode(): int
    {
        return $this->ok ? 0 : 1;
    }

    public function getResultDescription(): string
    {
        return $this->description;
    }
}

class FakeMpesaClient implements Client
{
    public ?array $lastPush = null;

    public function __construct(private FakeMpesaResponse $pushResponse) {}

    public function stkPush(array $data): Response
    {
        $this->lastPush = $data;

        return $this->pushResponse;
    }

    public function stkQuery(array $data): Response
    {
        throw new RuntimeException('unused in tests');
    }

    public function b2c(array $data): Response
    {
        throw new RuntimeException('unused in tests');
    }

    public function b2b(array $data): Response
    {
        throw new RuntimeException('unused in tests');
    }

    public function c2bRegister(array $data): Response
    {
        throw new RuntimeException('unused in tests');
    }

    public function c2bSimulate(array $data): Response
    {
        throw new RuntimeException('unused in tests');
    }

    public function accountBalance(array $data): Response
    {
        throw new RuntimeException('unused in tests');
    }

    public function transactionStatus(array $data): Response
    {
        throw new RuntimeException('unused in tests');
    }

    public function reversal(array $data): Response
    {
        throw new RuntimeException('unused in tests');
    }
}

class FakePaystackClient extends PaystackClient
{
    public ?array $lastInitialize = null;

    public ?object $initializeResponse = null;

    public ?object $verifyResponse = null;

    public function __construct() {}

    public function initialize(array $params): object
    {
        $this->lastInitialize = $params;

        return $this->initializeResponse;
    }

    public function verify(string $reference): object
    {
        return $this->verifyResponse;
    }
}

function bindMpesaSuccess(string $checkoutId = 'ws_CO_123'): FakeMpesaClient
{
    $fake = new FakeMpesaClient(new FakeMpesaResponse(true, (object) [
        'CheckoutRequestID' => $checkoutId,
        'MerchantRequestID' => 'm1',
        'CustomerMessage' => 'Success. Request accepted for processing',
    ]));

    app()->bind(Client::class, fn () => $fake);

    return $fake;
}

function stkCallbackPayload(string $checkoutId, int $resultCode = 0): array
{
    $payload = [
        'Body' => [
            'stkCallback' => [
                'MerchantRequestID' => 'm1',
                'CheckoutRequestID' => $checkoutId,
                'ResultCode' => $resultCode,
                'ResultDesc' => $resultCode === 0
                    ? 'The service request is processed successfully.'
                    : 'The initiator information is invalid.',
            ],
        ],
    ];

    if ($resultCode === 0) {
        $payload['Body']['stkCallback']['CallbackMetadata'] = [
            'Item' => [
                ['Name' => 'Amount', 'Value' => 2500],
                ['Name' => 'MpesaReceiptNumber', 'Value' => 'RCPT123ABC'],
                ['Name' => 'TransactionDate', 'Value' => 20261108120000],
                ['Name' => 'PhoneNumber', 'Value' => 254712345678],
            ],
        ];
    }

    return $payload;
}

test('a paid plan starts a one-month free trial, a free plan starts none', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createBillingOrganization($owner, 'starter');

    expect($organization->trial_ends_at)->not->toBeNull()
        ->and($organization->trial_ends_at->greaterThan(now()->addDays(27)))->toBeTrue()
        ->and($organization->trial_ends_at->lessThan(now()->addDays(32)))->toBeTrue()
        ->and($organization->onTrial())->toBeTrue()
        ->and($organization->effectivePlan()->slug)->toBe('starter');

    $freeOwner = User::factory()->create(['onboarded_at' => now()]);
    $freeOrganization = createBillingOrganization($freeOwner, 'free');

    expect($freeOrganization->trial_ends_at)->toBeNull()
        ->and($freeOrganization->onTrial())->toBeFalse()
        ->and($freeOrganization->effectivePlan()->slug)->toBe('free');
});

test('limits fall back to free once the trial lapses', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createBillingOrganization($owner, 'starter');

    // Inside the trial the paid limits apply: a second property is fine
    // even though free allows only one.
    $organization->properties()->create([
        'name' => 'First Heights', 'slug' => 'first-heights', 'status' => 'active', 'created_by' => $owner->id,
    ]);

    $this->actingAs($owner)
        ->post('http://billing-estates.malimanager.test/properties', [
            'name' => 'Second Heights',
        ])->assertRedirect();

    expect(Organization::find($organization->id)->properties()->count())->toBe(2);

    // After the trial the same create is refused on the free limit.
    $this->travelTo($organization->trial_ends_at->copy()->addDay());

    $this->actingAs($owner)
        ->post('http://billing-estates.malimanager.test/properties', [
            'name' => 'Third Heights',
        ])->assertRedirect();

    expect(Organization::find($organization->id)->properties()->count())->toBe(2);
    expect(Organization::find($organization->id)->effectivePlan()->slug)->toBe('free');
});

test('an active subscription keeps paid limits after the trial', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createBillingOrganization($owner, 'starter');
    $organization->update(['subscription_ends_at' => now()->addMonths(2)]);

    $this->travelTo($organization->trial_ends_at->copy()->addDay());

    $fresh = Organization::find($organization->id);

    expect($fresh->hasActiveSubscription())->toBeTrue()
        ->and($fresh->effectivePlan()->slug)->toBe('starter');
});

test('an owner can start an M-Pesa payment for the plan price', function () {
    config()->set('services.mpesa.shortcode', '174379');
    $fake = bindMpesaSuccess('ws_CO_123');

    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createBillingOrganization($owner, 'starter');

    $this->actingAs($owner)
        ->post(billingTenantUrl($organization, '/billing/mpesa'), [
            'phone' => '0712345678',
        ])->assertRedirect();

    expect($fake->lastPush)->not->toBeNull()
        ->and($fake->lastPush['PhoneNumber'])->toBe('254712345678')
        ->and($fake->lastPush['Amount'])->toBe(2500)
        ->and($fake->lastPush['BusinessShortCode'])->toBe('174379')
        ->and($fake->lastPush['CallBackURL'])->toBe(billingTenantUrl($organization, '/billing/mpesa/callback'));

    $this->assertDatabaseHas('payment_attempts', [
        'organization_id' => $organization->id,
        'gateway' => 'mpesa',
        'status' => 'pending',
        'gateway_reference' => 'ws_CO_123',
    ]);
});

test('M-Pesa initiation rejects a non-Kenyan phone number', function () {
    config()->set('services.mpesa.shortcode', '174379');
    bindMpesaSuccess();

    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createBillingOrganization($owner, 'starter');

    $this->actingAs($owner)
        ->post(billingTenantUrl($organization, '/billing/mpesa'), [
            'phone' => 'not-a-phone',
        ])->assertInvalid('phone');

    $this->assertDatabaseCount('payment_attempts', 0);
});

test('M-Pesa initiation fails loudly when unconfigured', function () {
    config()->set('services.mpesa.shortcode', '');
    bindMpesaSuccess();

    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createBillingOrganization($owner, 'starter');

    $this->actingAs($owner)
        ->post(billingTenantUrl($organization, '/billing/mpesa'), [
            'phone' => '0712345678',
        ])->assertRedirect();

    $this->assertDatabaseCount('payment_attempts', 0);
});

test('a free plan has nothing to pay for', function () {
    config()->set('services.mpesa.shortcode', '174379');
    bindMpesaSuccess();

    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createBillingOrganization($owner, 'free');

    $this->actingAs($owner)
        ->post(billingTenantUrl($organization, '/billing/mpesa'), [
            'phone' => '0712345678',
        ])->assertRedirect();

    $this->assertDatabaseCount('payment_attempts', 0);
});

test('a successful M-Pesa callback records payment and extends the subscription', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createBillingOrganization($owner, 'starter');

    PaymentAttempt::create([
        'organization_id' => $organization->id,
        'plan_id' => $organization->plan_id,
        'amount' => 2500,
        'currency' => 'KES',
        'gateway' => 'mpesa',
        'status' => 'pending',
        'gateway_reference' => 'ws_CO_9',
    ]);

    // No session and no CSRF token: proving the callback is exempt.
    $this->post(billingTenantUrl($organization, '/billing/mpesa/callback'), stkCallbackPayload('ws_CO_9'))
        ->assertOk()
        ->assertJsonPath('ResultCode', 0);

    $this->assertDatabaseHas('payment_attempts', [
        'gateway_reference' => 'ws_CO_9',
        'status' => 'succeeded',
    ]);

    $this->assertDatabaseHas('subscription_payments', [
        'organization_id' => $organization->id,
        'method' => 'mpesa',
        'reference' => 'RCPT123ABC',
    ]);

    expect($organization->refresh()->subscription_ends_at)->not->toBeNull()
        ->and($organization->hasActiveSubscription())->toBeTrue();
});

test('a cancelled M-Pesa push fails the attempt without recording payment', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createBillingOrganization($owner, 'starter');

    PaymentAttempt::create([
        'organization_id' => $organization->id,
        'plan_id' => $organization->plan_id,
        'amount' => 2500,
        'currency' => 'KES',
        'gateway' => 'mpesa',
        'status' => 'pending',
        'gateway_reference' => 'ws_CO_cancelled',
    ]);

    $this->post(
        billingTenantUrl($organization, '/billing/mpesa/callback'),
        stkCallbackPayload('ws_CO_cancelled', 1032),
    )->assertOk()->assertJsonPath('ResultCode', 0);

    $this->assertDatabaseHas('payment_attempts', [
        'gateway_reference' => 'ws_CO_cancelled',
        'status' => 'failed',
    ]);

    $this->assertDatabaseCount('subscription_payments', 0);
});

test('an unknown M-Pesa checkout is acknowledged without side effects', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createBillingOrganization($owner, 'starter');

    $this->post(
        billingTenantUrl($organization, '/billing/mpesa/callback'),
        stkCallbackPayload('ws_CO_ghost'),
    )->assertOk()->assertJsonPath('ResultCode', 0);

    $this->assertDatabaseCount('payment_attempts', 0);
    $this->assertDatabaseCount('subscription_payments', 0);
});

test('a repeated M-Pesa callback records payment only once', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createBillingOrganization($owner, 'starter');

    PaymentAttempt::create([
        'organization_id' => $organization->id,
        'plan_id' => $organization->plan_id,
        'amount' => 2500,
        'currency' => 'KES',
        'gateway' => 'mpesa',
        'status' => 'pending',
        'gateway_reference' => 'ws_CO_twice',
    ]);

    $url = billingTenantUrl($organization, '/billing/mpesa/callback');

    $this->post($url, stkCallbackPayload('ws_CO_twice'))->assertOk();
    $this->post($url, stkCallbackPayload('ws_CO_twice'))->assertOk();

    $this->assertDatabaseCount('subscription_payments', 1);
});

test('an owner can start a card checkout and is redirected to Paystack', function () {
    $fake = new FakePaystackClient;
    $fake->initializeResponse = (object) [
        'status' => true,
        'data' => (object) [
            'authorization_url' => 'https://checkout.paystack.com/abc',
            'access_code' => 'abc',
            'reference' => 'MM-TESTREF1234',
        ],
    ];
    app()->bind(PaystackClient::class, fn () => $fake);

    $owner = User::factory()->create(['onboarded_at' => now(), 'email' => 'owner@billing.test']);
    $organization = createBillingOrganization($owner, 'starter');

    $this->actingAs($owner)
        ->withHeader('X-Inertia', 'true')
        ->withHeader('X-Inertia-Version', '1')
        ->post(billingTenantUrl($organization, '/billing/card'))
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', 'https://checkout.paystack.com/abc');

    expect($fake->lastInitialize['email'])->toBe('owner@billing.test')
        ->and($fake->lastInitialize['amount'])->toBe(250000)
        ->and($fake->lastInitialize['currency'])->toBe('KES')
        ->and($fake->lastInitialize['channels'])->toBe(['card']);

    $this->assertDatabaseHas('payment_attempts', [
        'organization_id' => $organization->id,
        'gateway' => 'paystack',
        'status' => 'pending',
    ]);
});

test('a verified card payment records payment and extends the subscription', function () {
    $fake = new FakePaystackClient;
    $fake->verifyResponse = (object) [
        'status' => true,
        'data' => (object) [
            'status' => 'success',
            'amount' => 250000,
            'currency' => 'KES',
            'reference' => 'MM-CARDREF1234',
        ],
    ];
    app()->bind(PaystackClient::class, fn () => $fake);

    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createBillingOrganization($owner, 'starter');

    PaymentAttempt::create([
        'organization_id' => $organization->id,
        'plan_id' => $organization->plan_id,
        'amount' => 2500,
        'currency' => 'KES',
        'gateway' => 'paystack',
        'status' => 'pending',
        'gateway_reference' => 'MM-CARDREF1234',
    ]);

    $this->actingAs($owner)
        ->get(billingTenantUrl($organization, '/billing/card/callback?reference=MM-CARDREF1234'))
        ->assertRedirect(billingTenantUrl($organization, '/billing'));

    $this->assertDatabaseHas('payment_attempts', [
        'gateway_reference' => 'MM-CARDREF1234',
        'status' => 'succeeded',
    ]);

    $this->assertDatabaseHas('subscription_payments', [
        'organization_id' => $organization->id,
        'method' => 'card',
        'reference' => 'MM-CARDREF1234',
    ]);

    expect($organization->refresh()->hasActiveSubscription())->toBeTrue();
});

test('a card verification with a tampered amount fails without recording', function () {
    $fake = new FakePaystackClient;
    $fake->verifyResponse = (object) [
        'status' => true,
        'data' => (object) [
            'status' => 'success',
            'amount' => 100,
            'currency' => 'KES',
            'reference' => 'MM-CARDREF9999',
        ],
    ];
    app()->bind(PaystackClient::class, fn () => $fake);

    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createBillingOrganization($owner, 'starter');

    PaymentAttempt::create([
        'organization_id' => $organization->id,
        'plan_id' => $organization->plan_id,
        'amount' => 2500,
        'currency' => 'KES',
        'gateway' => 'paystack',
        'status' => 'pending',
        'gateway_reference' => 'MM-CARDREF9999',
    ]);

    $this->actingAs($owner)
        ->get(billingTenantUrl($organization, '/billing/card/callback?reference=MM-CARDREF9999'))
        ->assertRedirect(billingTenantUrl($organization, '/billing'));

    $this->assertDatabaseHas('payment_attempts', [
        'gateway_reference' => 'MM-CARDREF9999',
        'status' => 'failed',
    ]);

    $this->assertDatabaseCount('subscription_payments', 0);
});

test('the billing page shows status to owners and refuses staff', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createBillingOrganization($owner, 'starter');

    $this->actingAs($owner)
        ->get(billingTenantUrl($organization, '/billing'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('organisation/billing/show')
            ->where('billing.plan.name', 'Starter')
            ->where('billing.on_trial', true)
            ->where('billing.paid_access', true)
        );

    $staff = User::factory()->create(['onboarded_at' => now()]);
    $organization->users()->attach($staff->id, [
        'is_owner' => false,
        'sub_role_id' => $organization->subRoles()->where('slug', 'caretaker')->first()->id,
        'status' => 'active',
    ]);

    $this->actingAs($staff)
        ->get(billingTenantUrl($organization, '/billing'))
        ->assertRedirect(billingTenantUrl($organization, '/properties'));
});

class FakeSmsClient extends SmsClient
{
    /** @var array<int, array{to: string, message: string}> */
    public array $sent = [];

    public function __construct() {}

    public function send(string $to, string $message): array
    {
        $this->sent[] = ['to' => $to, 'message' => $message];

        return ['status' => 'success', 'data' => null];
    }
}

function bindFakeSms(): FakeSmsClient
{
    $fake = new FakeSmsClient;

    app()->bind(SmsClient::class, fn () => $fake);

    return $fake;
}

test('billing reminders email owners whose trial is ending', function () {
    Mail::fake();
    bindFakeSms();

    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createBillingOrganization($owner, 'starter');
    $organization->update(['trial_ends_at' => now()->addDays(2)]);

    Artisan::call('billing:remind');

    Mail::assertSent(BillingReminderMail::class, fn ($mail) => $mail->hasTo($owner->email));
});

test('billing reminders text owners who prefer sms', function () {
    Mail::fake();
    $fake = bindFakeSms();

    $owner = User::factory()->create([
        'onboarded_at' => now(),
        'phone' => '+254712345678',
        'notification_channel' => 'sms',
    ]);
    $organization = createBillingOrganization($owner, 'starter');
    $organization->update(['trial_ends_at' => now()->subDay()]);

    Artisan::call('billing:remind');

    expect($fake->sent)->toHaveCount(1)
        ->and($fake->sent[0]['to'])->toBe('+254712345678');
    Mail::assertNotSent(BillingReminderMail::class);
});

test('billing reminders skip subscribed, free and distant-trial organizations', function () {
    Mail::fake();
    $fake = bindFakeSms();

    $subscribed = User::factory()->create(['onboarded_at' => now()]);
    $subscribedOrg = createBillingOrganization($subscribed, 'starter');
    $subscribedOrg->update(['subscription_ends_at' => now()->addMonth()]);

    $free = User::factory()->create(['onboarded_at' => now()]);
    createBillingOrganization($free, 'free');

    $early = User::factory()->create(['onboarded_at' => now()]);
    $earlyOrg = createBillingOrganization($early, 'starter');
    $earlyOrg->update(['trial_ends_at' => now()->addDays(20)]);

    Artisan::call('billing:remind');

    Mail::assertNotSent(BillingReminderMail::class);
    expect($fake->sent)->toBeEmpty();
});
