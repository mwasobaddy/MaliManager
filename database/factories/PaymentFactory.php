<?php

namespace Database\Factories;

use App\Models\Lease;
use App\Models\Occupant;
use App\Models\Organization;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'lease_id' => Lease::factory(),
            'occupant_id' => Occupant::factory(),
            'amount' => fake()->randomFloat(2, 1000, 50000),
            'currency' => 'KES',
            'paid_on' => fake()->date(),
            'period_start' => fake()->dateTimeThisMonth()->format('Y-m-01'),
            'period_end' => fake()->dateTimeThisMonth()->format('Y-m-t'),
            'method' => fake()->randomElement(Payment::METHODS),
            'reference' => fake()->optional()->numerify('MPE########'),
            'status' => fake()->randomElement(Payment::STATUSES),
        ];
    }
}
