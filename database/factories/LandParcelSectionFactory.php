<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\LandParcel;
use App\Models\LandParcelSection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class LandParcelSectionFactory extends Factory
{
    protected $model = LandParcelSection::class;

    public function definition(): array
    {
        return [
            'land_parcel_id' => LandParcel::factory(),
            'name' => fake()->word(),
            'area' => fake()->randomFloat(2, 0.1, 50),
            'status' => 'vacant',

            'notes' => fake()->sentence(),
            'created_by' => User::factory(),
        ];
    }
}
