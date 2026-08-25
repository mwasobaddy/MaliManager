<?php

namespace Database\Seeders;

use App\Enums\PlatformRole;
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
    }
}
