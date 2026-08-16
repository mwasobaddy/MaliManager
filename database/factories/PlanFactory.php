<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    public function definition(): array
    {
        return [
            'name' => fake()->word(),
            'slug' => fake()->unique()->slug(),
            'description' => fake()->sentence(),
            'currency' => 'KES',
            'price' => fake()->randomElement([0, 2500, 7500, 25000]),
            'properties_limit' => fake()->numberBetween(1, 100),
            'units_limit' => fake()->numberBetween(10, 500),
            'has_dedicated_db' => false,
            'has_custom_domain' => false,
            'has_email_notifications' => true,
            'has_sms_notifications' => false,
            'is_active' => true,
            'sort_order' => fake()->numberBetween(1, 10),
        ];
    }

    public function free(): static
    {
        return $this->state(fn (): array => [
            'name' => 'Free',
            'slug' => 'free',
            'price' => 0,
            'properties_limit' => 1,
            'units_limit' => 10,
            'has_email_notifications' => false,
            'has_sms_notifications' => false,
        ]);
    }

    public function enterprise(): static
    {
        return $this->state(fn (): array => [
            'name' => 'Enterprise',
            'slug' => 'enterprise',
            'price' => 25000,
            'properties_limit' => null,
            'units_limit' => null,
            'has_dedicated_db' => true,
            'has_custom_domain' => true,
            'has_email_notifications' => true,
            'has_sms_notifications' => true,
        ]);
    }
}
