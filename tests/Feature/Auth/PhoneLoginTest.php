<?php

use App\Models\Audit;
use App\Models\LoginOtp;
use App\Models\User;
use App\Services\SmsClient;
use Illuminate\Support\Facades\Mail;

class FakeLoginSmsClient extends SmsClient
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

function bindPhoneFakeSms(): FakeLoginSmsClient
{
    $fake = new FakeLoginSmsClient;

    config()->set('services.africastalking.key', 'test-key');
    app()->bind(SmsClient::class, fn () => $fake);

    return $fake;
}

test('a tenant can log in with their phone number', function () {
    Mail::fake();
    $fake = bindPhoneFakeSms();

    $user = User::factory()->create(['phone' => '0712000101', 'onboarded_at' => now()]);

    $this->post(route('login.store'), ['identifier' => '0712000101'])
        ->assertRedirect(route('login.otp'));

    expect($fake->sent)->toHaveCount(1)
        ->and($fake->sent[0]['to'])->toBe('+254712000101');

    Mail::assertNothingSent();

    session(['login.identifier' => '+254712000101', 'login.channel' => 'sms']);

    $code = LoginOtp::where('user_id', $user->id)->latest('id')->value('code');

    $this->post(route('login.otp.verify'), ['code' => $code])
        ->assertRedirect(route('guest.home'));

    $this->assertAuthenticatedAs($user);
});

test('phone lookup matches differently formatted stored numbers', function () {
    Mail::fake();
    bindPhoneFakeSms();

    $user = User::factory()->create(['phone' => '+254 712 000 102', 'onboarded_at' => now()]);

    $this->post(route('login.store'), ['identifier' => '254712000102'])
        ->assertRedirect(route('login.otp'));

    expect(LoginOtp::where('user_id', $user->id)->count())->toBe(1);
});

test('an unknown phone number creates a phone-first account', function () {
    Mail::fake();
    $fake = bindPhoneFakeSms();

    $this->post(route('login.store'), ['identifier' => '+254700000103'])
        ->assertRedirect(route('login.otp'));

    $user = User::where('phone', '+254700000103')->firstOrFail();
    expect($user->email)->toBe('254700000103@phone.invalid')
        ->and($user->notification_channel)->toBe('sms')
        ->and($fake->sent)->toHaveCount(1);

    Mail::assertNothingSent();
});

test('phone login rejects an invalid identifier', function () {
    $this->post(route('login.store'), ['identifier' => 'not-a-phone'])
        ->assertSessionHasErrors('identifier');

    $this->assertGuest();
});

test('suspended phone accounts are blocked and the attempt is logged', function () {
    $user = User::factory()->create(['phone' => '+254700000104', 'status' => 'suspended']);

    $this->post(route('login.store'), ['identifier' => '+254700000104'])
        ->assertSessionHasErrors('identifier');

    $this->assertGuest();

    expect(Audit::where('event', 'login.suspended')
        ->where('subject_type', User::class)
        ->where('subject_id', $user->id)
        ->exists())->toBeTrue();
});

test('otp page shows the masked phone number for sms logins', function () {
    session(['login.identifier' => '+254712000105', 'login.channel' => 'sms']);

    $this->get(route('login.otp'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('auth/otp')
            ->where('identifier', '+254712000105')
            ->where('channel', 'sms')
            ->where('email', null));
});

test('resending over sms notifies the same number', function () {
    Mail::fake();
    $fake = bindPhoneFakeSms();

    $user = User::factory()->create(['phone' => '+254712000106']);

    session(['login.identifier' => '+254712000106', 'login.channel' => 'sms']);

    $this->post(route('login.otp.resend'))->assertRedirect();

    expect($fake->sent)->toHaveCount(1)
        ->and($fake->sent[0]['to'])->toBe('+254712000106');

    expect(LoginOtp::where('user_id', $user->id)->count())->toBe(1);
});
