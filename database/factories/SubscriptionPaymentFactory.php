<?php

namespace Database\Factories;

use App\Models\SubscriptionPayment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubscriptionPayment>
 */
class SubscriptionPaymentFactory extends Factory
{
    protected $model = SubscriptionPayment::class;

    public function definition(): array
    {
        return [
            'amount' => fake()->randomFloat(2, 500, 50000),
            'currency' => 'KES',
            'received_on' => fake()->dateTimeBetween('-2 years', 'now')->format('Y-m-d'),
            'method' => fake()->randomElement(['mpesa', 'card', 'bank', 'cash']),
            'reference' => fake()->numerify('####-####'),
            'created_by' => null,
        ];
    }
}
