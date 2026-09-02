<?php

namespace Database\Factories;

use App\Models\PlatformExpense;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlatformExpense>
 */
class PlatformExpenseFactory extends Factory
{
    protected $model = PlatformExpense::class;

    public function definition(): array
    {
        return [
            'category' => fake()->randomElement(PlatformExpense::CATEGORIES),
            'amount' => fake()->randomFloat(2, 100, 50000),
            'currency' => 'KES',
            'spent_on' => fake()->dateTimeBetween('-2 years', 'now')->format('Y-m-d'),
            'vendor_name' => fake()->company(),
            'notes' => fake()->optional()->sentence(),
            'created_by' => null,
        ];
    }
}
