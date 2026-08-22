<?php

namespace Database\Factories;

use App\Models\LandParcel;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LandParcel>
 */
class LandParcelFactory extends Factory
{
    protected $model = LandParcel::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->streetName(),
            'title_deed_number' => fake()->bothify('LR-####'),
            'acreage' => fake()->randomFloat(2, 0.1, 500),
            'zoning' => fake()->randomElement(['residential', 'commercial', 'agricultural', 'mixed', 'industrial']),
            'address' => fake()->address(),
            'city' => fake()->city(),
            'status' => 'active',
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
            'available_for_lease' => fake()->boolean(),
            'notes' => fake()->sentence(),
        ];
    }
}
