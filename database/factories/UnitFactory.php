<?php

namespace Database\Factories;

use App\Models\Property;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Unit>
 */
class UnitFactory extends Factory
{
    protected $model = Unit::class;

    public function definition(): array
    {
        return [
            'property_id' => Property::factory(),
            'name' => fake()->unique()->bothify('Unit ##'),
            'status' => fake()->randomElement(['vacant', 'occupied', 'maintenance']),
            'type' => fake()->randomElement(['Studio', '1 Bedroom', '2 Bedroom', '3 Bedroom']),
            'monthly_rent' => fake()->randomFloat(2, 5000, 150000),
            'deposit' => fake()->randomFloat(2, 5000, 150000),
        ];
    }
}
