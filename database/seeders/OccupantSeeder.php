<?php

namespace Database\Seeders;

use App\Models\Occupant;
use App\Models\Organization;
use App\Models\Property;
use App\Models\User;
use App\Services\OccupantService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class OccupantSeeder extends Seeder
{
    /**
     * Seed occupants across every organization. Each organization gets:
     *  - 1 occupant with an active rental (unit assigned + active lease)
     *  - 1 occupant who has been moved out (lease ended, history kept)
     *  - 1 occupant with no unit assignment (no rental)
     *  - 1 inactive occupant (no rental)
     * Total: 20 occupants (4 per org x 5 orgs).
     */
    public function run(): void
    {
        $occupantService = app(OccupantService::class);

        foreach (Organization::all() as $organization) {
            $owner = $organization->users()->wherePivot('is_owner', true)->first();

            if (! $owner) {
                continue;
            }

            $property = $organization->properties()->firstOrCreate(
                ['slug' => Str::slug($organization->slug.'-residences')],
                [
                    'name' => $organization->name.' Residences',
                    'status' => 'active',
                    'created_by' => $owner->id,
                ],
            );

            $units = [];
            foreach (['A1', 'A2', 'A3', 'A4'] as $unitName) {
                $units[$unitName] = $property->units()->firstOrCreate(
                    ['name' => $unitName],
                    [
                        'status' => 'occupied',
                        'type' => '1 Bedroom',
                        'monthly_rent' => 25000,
                        'deposit' => 25000,
                        'created_by' => $owner->id,
                    ],
                );
            }

            $base = "occupant.{$organization->slug}";

            // 1) Active rental: unit assigned + active lease.
            $this->createOccupant($occupantService, $organization, $property, $owner, [
                'first_name' => 'Active',
                'last_name' => "Renter {$organization->slug}",
                'email' => "{$base}.active@malimanager.test",
                'phone' => '0711000001',
                'national_id' => (string) rand(30000000, 39999999),
                'status' => 'active',
                'unit_ids' => [$units['A1']->id],
                'lease' => [
                    'starts_at' => now()->subMonths(6)->toDateString(),
                    'rent_amount' => 25000,
                    'rent_frequency' => 'monthly',
                    'deposit' => 25000,
                    'currency' => 'KES',
                    'agreement_text' => 'Standard tenancy agreement.',
                ],
            ]);

            // 2) Moved out: lease created then ended (history preserved).
            $movedOut = $this->createOccupant($occupantService, $organization, $property, $owner, [
                'first_name' => 'Moved',
                'last_name' => "Out {$organization->slug}",
                'email' => "{$base}.moved@malimanager.test",
                'phone' => '0711000002',
                'national_id' => (string) rand(30000000, 39999999),
                'status' => 'active',
                'unit_ids' => [$units['A2']->id],
                'lease' => [
                    'starts_at' => now()->subYear()->toDateString(),
                    'rent_amount' => 22000,
                    'rent_frequency' => 'monthly',
                    'deposit' => 22000,
                    'currency' => 'KES',
                ],
            ]);
            $occupantService->moveOut($movedOut, $property, $owner);

            // 3) No unit assignment: occupant record, no rental.
            $this->createOccupant($occupantService, $organization, $property, $owner, [
                'first_name' => 'No',
                'last_name' => "Unit {$organization->slug}",
                'email' => "{$base}.nounit@malimanager.test",
                'phone' => '0711000003',
                'national_id' => (string) rand(30000000, 39999999),
                'status' => 'active',
                'unit_ids' => [],
            ]);

            // 4) Inactive occupant: no rental.
            $this->createOccupant($occupantService, $organization, $property, $owner, [
                'first_name' => 'Inactive',
                'last_name' => "Renter {$organization->slug}",
                'email' => "{$base}.inactive@malimanager.test",
                'phone' => '0711000004',
                'national_id' => (string) rand(30000000, 39999999),
                'status' => 'inactive',
                'unit_ids' => [],
            ]);
        }

        $this->command?->info('Occupants seeded: 20 across all organizations.');
    }

    private function createOccupant(
        OccupantService $occupantService,
        Organization $organization,
        Property $property,
        User $owner,
        array $data,
    ): Occupant {
        return $occupantService->create($organization, $property, $owner, $data);
    }
}
