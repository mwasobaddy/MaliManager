<?php

namespace Database\Factories;

use App\Models\Property;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Unit>
 */
class UnitFactory extends Factory
{
    protected $model = Unit::class;

    public function definition(): array
    {
        $name = fake()->unique()->bothify('Unit ##');

        return [
            'property_id' => Property::factory(),
            'name' => $name,
            // Derived from the name so a factory-made unit has the same
            // key shape as a real one — the slug is NOT NULL and is what the
            // photo folder is named after.
            'slug' => Str::slug($name),
            'status' => fake()->randomElement(['vacant', 'occupied', 'maintenance']),
            'type' => fake()->randomElement(['Studio', '1 Bedroom', '2 Bedroom', '3 Bedroom']),
            'monthly_rent' => fake()->randomFloat(2, 5000, 150000),
            'deposit' => fake()->randomFloat(2, 5000, 150000),
        ];
    }
}
