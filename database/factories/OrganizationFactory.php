<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    protected $model = Organization::class;

    public function definition(): array
    {
        $name = fake()->company();

        return [
            'tenant_id' => Tenant::factory(),
            'name' => $name,
            'slug' => str($name)->slug().'-'.fake()->unique()->numberBetween(100, 999),
            'email' => fake()->companyEmail(),
            'phone' => fake()->phoneNumber(),
            'status' => 'active',
        ];
    }
}
