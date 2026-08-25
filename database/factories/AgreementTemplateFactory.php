<?php

namespace Database\Factories;

use App\Models\AgreementTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgreementTemplate>
 */
class AgreementTemplateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'body_html' => '<p>{{occupant_name}} agrees to rent {{unit_name}} at {{rent_amount}} {{currency}}.</p>',
            'created_by' => User::factory(),
        ];
    }
}
