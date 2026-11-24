<?php

namespace Database\Seeders;

use App\Enums\UtilityType;
use App\Models\Property;
use App\Models\UtilityReading;
use Illuminate\Database\Seeder;

/**
 * A couple of water readings on each property's first occupied unit, so the
 * utilities page demonstrates consumption (first reading has none, the
 * second carries the difference). Readings are inert meter facts — no
 * charges are seeded; those are landlord configuration, created in the UI.
 */
class UtilitySeeder extends Seeder
{
    public function run(): void
    {
        foreach (Property::with('units')->get() as $property) {
            $unit = $property->units->firstWhere('status', 'occupied')
                ?? $property->units->first();

            if (! $unit) {
                continue;
            }

            $ownerId = $property->created_by;

            $attributes = [
                'organization_id' => $property->organization_id,
                'unit_id' => $unit->id,
                'utility' => UtilityType::Water,
            ];

            UtilityReading::firstOrCreate(
                $attributes + ['read_on' => now()->subDays(60)->toDateString()],
                [
                    'reading' => '480.000',
                    'consumption' => null,
                    'recorded_by' => $ownerId,
                ]
            );

            UtilityReading::firstOrCreate(
                $attributes + ['read_on' => now()->subDays(30)->toDateString()],
                [
                    'reading' => '512.500',
                    'consumption' => '32.500',
                    'recorded_by' => $ownerId,
                ]
            );
        }

        $this->command?->info('Utility readings seeded.');
    }
}
