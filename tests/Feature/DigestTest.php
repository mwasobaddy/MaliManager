<?php

use App\Mail\OrgDigestMail;
use App\Models\AiSetting;
use App\Models\AiUsageLog;
use App\Models\Expense;
use App\Models\MaintenanceRequest;
use App\Models\Plan;
use App\Models\User;
use App\Services\TenantService;
use Database\Seeders\PlansSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Mail;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Testing\TextResponseFake;
use Prism\Prism\ValueObjects\Usage;

beforeEach(function () {
    Facade::clearResolvedInstance('prism');
    (new RolesAndPermissionsSeeder)->run();
    (new PlansSeeder)->run();
});

class AiSettingTestStub
{
    public static function createFor($organization, ?string $narrative = null): AiSetting
    {
        $setting = new AiSetting([
            'provider' => 'openai',
            'model' => 'gpt-4o',
            'api_key' => 'sk-digest-key-1234567890',
            'allow_all_members' => true,
        ]);
        $setting->owner()->associate($organization);
        $setting->save();

        // Pre-register the narrative the faked Prism call will return.
        Prism::fake([
            TextResponseFake::make()
                ->withText($narrative ?? '')
                ->withUsage(new Usage(80, 40)),
        ]);

        return $setting;
    }
}

test('digest command emails owners with weekly numbers and skips narrative without a key', function () {
    Mail::fake();

    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = app(TenantService::class)->createOrganization(
        owner: $owner,
        name: 'Acme Estates',
        plan: Plan::where('slug', 'free')->firstOrFail(),
    );

    $property = $organization->properties()->create([
        'name' => 'Sunset Heights', 'slug' => 'sunset-heights', 'status' => 'active', 'created_by' => $owner->id,
    ]);
    $unit = $property->units()->create(['name' => 'A1', 'created_by' => $owner->id]);

    Expense::create([
        'organization_id' => $organization->id,
        'expenseable_type' => Property::class,
        'expenseable_id' => $property->id,
        'category' => 'cleaning', 'amount' => 3000,
        'spent_on' => now()->subDay(), 'created_by' => $owner->id,
    ]);

    MaintenanceRequest::create([
        'organization_id' => $organization->id,
        'property_id' => $property->id,
        'raised_by' => $owner->id,
        'title' => 'Gate broken', 'description' => '-',
        'status' => 'opened', 'priority' => 'urgent',
    ]);

    $this->artisan('ai:send-digest')->assertSuccessful();

    Mail::assertSent(OrgDigestMail::class, 1);

    Mail::assertSent(OrgDigestMail::class, function ($mail) use ($owner, $organization) {
        return $mail->hasTo($owner->email)
            && $mail->organization->id === $organization->id
            && $mail->numbers['expenses_total'] === 3000.0
            && $mail->numbers['maintenance_opened'] === 1
            && array_key_exists('leases_started_prev', $mail->numbers)
            && array_key_exists('units_occupied', $mail->numbers)
            && array_key_exists('expiring_leases_60d', $mail->numbers)
            && $mail->narrative === null; // no AI key configured
    });
});

test('digest includes the AI narrative and logs usage when an org key exists', function () {
    Mail::fake();

    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = app(TenantService::class)->createOrganization(
        owner: $owner,
        name: 'Acme Estates',
        plan: Plan::where('slug', 'free')->firstOrFail(),
    );

    AiSettingTestStub::createFor($organization, narrative: 'Quiet week overall — nothing urgent.');

    $this->artisan('ai:send-digest')->assertSuccessful();

    Mail::assertSent(OrgDigestMail::class,
        fn ($mail) => $mail->narrative === 'Quiet week overall — nothing urgent.');

    expect(AiUsageLog::where('feature', 'ask_data')->count())->toBe(1);
});
