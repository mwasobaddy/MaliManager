<?php

namespace Database\Seeders;

use App\Enums\AiProvider;
use App\Enums\PlatformRole;
use App\Models\AiSetting;
use App\Models\Person;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Create the platform administrator account.
     */
    public function run(): void
    {
        $person = Person::firstOrCreate(
            ['email' => 'kelvinramsiel@gmail.com'],
            [
                'first_name' => 'Kelvin',
                'last_name' => 'Mwangi Wanjohi',
                'phone' => '0740252837',
                'national_id' => '38025533',
                'gender' => 'male',
                'status' => 'active',
            ],
        );

        $admin = User::firstOrCreate(
            ['email' => 'kelvinramsiel@gmail.com'],
            [
                'name' => 'Kelvin Mwangi Wanjohi',
                'phone' => '0740252837',
                'password' => Hash::make('password'),
                'person_id' => $person->id,
                'onboarded_at' => now(),
                'status' => 'active',
            ],
        );

        if ($person->created_by === null) {
            $person->update(['created_by' => $admin->id]);
        }

        $admin->assignRole(PlatformRole::Admin->value);

        $this->command?->info('Admin created: kelvinramsiel@gmail.com (password: password)');

        $this->seedAdminAiKey($admin);
    }

    /**
     * Personal OpenCode credential for the admin, so the platform assistant
     * works straight after seeding. The key lives in the environment — never
     * in this file — and an existing setting is left untouched.
     */
    private function seedAdminAiKey(User $admin): void
    {
        $apiKey = env('OPENCODE_API_KEY');

        if (! is_string($apiKey) || $apiKey === '') {
            $this->command?->warn('OPENCODE_API_KEY is not set: skipping the admin personal AI key.');

            return;
        }

        $setting = AiSetting::firstOrCreate(
            [
                'owner_type' => $admin->getMorphClass(),
                'owner_id' => $admin->id,
            ],
            [
                'provider' => AiProvider::OpenCode->value,
                'model' => 'muse-spark-1.3',
                'base_url' => null,
                'api_key' => $apiKey,
                'features' => null,
            ],
        );

        if ($setting->wasRecentlyCreated) {
            $this->command?->info('Admin personal AI key seeded (OpenCode, muse-spark-1.3).');
        }
    }
}
