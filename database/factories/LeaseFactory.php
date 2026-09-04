<?php

namespace Database\Factories;

use App\Models\Lease;
use App\Models\Organization;
use App\Models\Person;
use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lease>
 */
class LeaseFactory extends Factory
{
    protected $model = Lease::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'person_id' => Person::factory(),
            'organization_id' => Organization::factory(),
            'tenant_id' => null,
            'property_id' => Property::factory(),
            'unit_id' => null,
            'starts_at' => fake()->dateTimeBetween('-1 year', 'now'),
            'ends_at' => null,
            'rent_amount' => fake()->randomFloat(2, 5000, 30000),
            'rent_frequency' => 'monthly',
            'currency' => 'KES',
            'deposit' => fake()->randomFloat(2, 5000, 30000),
            'status' => 'active',
            'created_by' => null,
        ];
    }
}
