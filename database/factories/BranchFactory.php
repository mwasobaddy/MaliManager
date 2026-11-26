<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Branch>
 */
class BranchFactory extends Factory
{
    protected $model = Branch::class;

    public function definition(): array
    {
        $name = fake()->unique()->city().' Branch';

        return [
            'organization_id' => Organization::factory(),
            'name' => $name,
            'slug' => str($name)->slug(),
        ];
    }
}
