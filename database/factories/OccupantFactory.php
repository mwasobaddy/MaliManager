<?php

namespace Database\Factories;

use App\Models\Occupant;
use App\Models\Organization;
use App\Models\Person;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Occupant>
 */
class OccupantFactory extends Factory
{
    protected $model = Occupant::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'person_id' => Person::factory(),
            'status' => 'active',
        ];
    }
}
