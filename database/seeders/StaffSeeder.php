<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\Person;
use App\Models\User;
use App\Services\StaffService;
use App\Support\DefaultSubRoles;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class StaffSeeder extends Seeder
{
    /**
     * Seed staff: 5 per organization (tied, with a sub-role) and 25 standalone
     * users that belong to no organization, so both states can be observed.
     */
    public function run(): void
    {
        $staffService = app(StaffService::class);

        $roleSlugs = [
            DefaultSubRoles::LANDLORD,
            DefaultSubRoles::CARETAKER,
            DefaultSubRoles::AGENT,
        ];

        $standaloneCount = 0;

        foreach (Organization::all() as $organization) {
            $owner = $organization->users()->wherePivot('is_owner', true)->first();

            if (! $owner) {
                continue;
            }

            $roles = $organization->subRoles()
                ->whereIn('slug', $roleSlugs)
                ->get();

            if ($roles->isEmpty()) {
                continue;
            }

            for ($i = 1; $i <= 5; $i++) {
                $email = "staff.{$organization->slug}.{$i}@malimanager.test";
                $name = "Staff {$organization->slug} {$i}";

                $user = $this->ensureUser($email, $name);

                $staffService->create($organization, $owner, [
                    'name' => $name,
                    'email' => $email,
                    'phone' => '07'.str_pad((string) (1000000 + $i), 7, '0', STR_PAD_LEFT),
                    'sub_role_id' => $roles->get($i % $roles->count())->id,
                    'property_ids' => [],
                ]);

                $standaloneCount++;
            }
        }

        // Standalone staff with no organization membership.
        for ($i = 1; $i <= 25; $i++) {
            $email = "standalone.{$i}@malimanager.test";
            $this->ensureUser($email, "Standalone Staff {$i}");
            $standaloneCount++;
        }

        $this->command?->info("Staff seeded: {$standaloneCount} users (tied to orgs + standalone).");
    }

    private function ensureUser(string $email, string $name): User
    {
        $person = Person::firstOrCreate(
            ['email' => $email],
            [
                'first_name' => $name,
                'last_name' => 'User',
                'email' => $email,
                'status' => 'active',
            ],
        );

        return User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make('password'),
                'person_id' => $person->id,
                'onboarded_at' => now(),
                'status' => 'active',
            ],
        );
    }
}
