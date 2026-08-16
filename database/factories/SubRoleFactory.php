<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\SubRole;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubRole>
 */
class SubRoleFactory extends Factory
{
    protected $model = SubRole::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'organization_id' => Organization::factory(),
            'name' => $name,
            'slug' => str($name)->slug().'-'.fake()->unique()->numberBetween(100, 999),
            'description' => fake()->sentence(),
            'status' => 'active',
        ];
    }
}
