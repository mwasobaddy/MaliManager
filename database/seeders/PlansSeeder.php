<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlansSeeder extends Seeder
{
    /**
     * Seed the four default subscription tiers.
     */
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Free',
                'slug' => 'free',
                'description' => 'For a single property or a small rental up to 10 units.',
                'currency' => 'KES',
                'price' => 0,
                'properties_limit' => 1,
                'units_limit' => 10,
                'has_dedicated_db' => false,
                'has_custom_domain' => false,
                'has_email_notifications' => false,
                'has_sms_notifications' => false,
                'sort_order' => 1,
            ],
            [
                'name' => 'Starter',
                'slug' => 'starter',
                'description' => 'Up to 5 properties and 100 units.',
                'currency' => 'KES',
                'price' => 2500,
                'properties_limit' => 5,
                'units_limit' => 100,
                'has_dedicated_db' => false,
                'has_custom_domain' => false,
                'has_email_notifications' => true,
                'has_sms_notifications' => false,
                'sort_order' => 2,
            ],
            [
                'name' => 'Growth',
                'slug' => 'growth',
                'description' => 'Up to 20 properties and 500 units.',
                'currency' => 'KES',
                'price' => 7500,
                'properties_limit' => 20,
                'units_limit' => 500,
                'has_dedicated_db' => false,
                'has_custom_domain' => true,
                'has_email_notifications' => true,
                'has_sms_notifications' => true,
                'sort_order' => 3,
            ],
            [
                'name' => 'Enterprise',
                'slug' => 'enterprise',
                'description' => 'Unlimited properties and units, dedicated database and custom domain.',
                'currency' => 'KES',
                'price' => 25000,
                'properties_limit' => null,
                'units_limit' => null,
                'has_dedicated_db' => true,
                'has_custom_domain' => true,
                'has_email_notifications' => true,
                'has_sms_notifications' => true,
                'sort_order' => 4,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(['slug' => $plan['slug']], $plan);
        }
    }
}
