<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\Person;
use App\Models\Plan;
use App\Models\User;
use App\Services\TenantService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class OrganizationSeeder extends Seeder
{
    /**
     * Create five demo organizations. The first is owned by the platform
     * admin (seeded by AdminSeeder); the rest get their own owner accounts.
     *
     * @var array<int, array{name: string, owner: string|null, owner_name: string|null}>
     */
    private array $organizations = [
        ['name' => 'Kelvin Properties', 'owner' => null, 'owner_name' => null],
        ['name' => 'Savannah Estates', 'owner' => 'owner.savannah@malimanager.test', 'owner_name' => 'Savannah Owner'],
        ['name' => 'Nairobi Heights', 'owner' => 'owner.nairobi@malimanager.test', 'owner_name' => 'Nairobi Owner'],
        ['name' => 'Coastal Homes', 'owner' => 'owner.coastal@malimanager.test', 'owner_name' => 'Coastal Owner'],
        ['name' => 'Umoja Ventures', 'owner' => 'owner.umoja@malimanager.test', 'owner_name' => 'Umoja Owner'],
    ];

    public function run(): void
    {
        $admin = User::where('email', 'kelvinramsiel@gmail.com')->firstOrFail();
        $plan = Plan::where('slug', 'free')->firstOrFail();
        $tenantService = app(TenantService::class);

        foreach ($this->organizations as $definition) {
            $slug = Str::slug($definition['name']);

            $organization = Organization::where('slug', $slug)->first();

            if ($organization) {
                $this->command?->info("Organization already exists: {$definition['name']}");

                continue;
            }

            $owner = $admin;

            if ($definition['owner']) {
                $ownerPerson = Person::firstOrCreate(
                    ['email' => $definition['owner']],
                    [
                        'first_name' => $definition['owner_name'],
                        'last_name' => 'Account',
                        'email' => $definition['owner'],
                        'status' => 'active',
                    ],
                );

                $owner = User::firstOrCreate(
                    ['email' => $definition['owner']],
                    [
                        'name' => $definition['owner_name'].' Account',
                        'password' => Hash::make('password'),
                        'person_id' => $ownerPerson->id,
                        'onboarded_at' => now(),
                        'status' => 'active',
                    ],
                );
            }

            $tenantService->createOrganization(
                owner: $owner,
                name: $definition['name'],
                plan: $plan,
                email: $definition['owner'] ?? $admin->email,
                phone: $definition['owner'] ? '0700000000' : $admin->phone,
            );

            $this->command?->info("Organization created: {$definition['name']}");
        }
    }
}
