<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\Plan;
use App\Models\PlatformExpense;
use App\Models\SubscriptionPayment;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class PlatformFinancialSeeder extends Seeder
{
    /**
     * Seed realistic subscription payments and platform expenses across
     * multiple years and months so the dashboard graphs have meaningful data.
     */
    public function run(): void
    {
        $this->seedSubscriptionPayments();
        $this->seedPlatformExpenses();
    }

    private function seedSubscriptionPayments(): void
    {
        if (SubscriptionPayment::count() > 0) {
            return;
        }

        $plans = Plan::all();
        $orgs = Organization::all();

        if ($plans->isEmpty() || $orgs->isEmpty()) {
            return;
        }

        $methods = ['mpesa', 'card', 'bank', 'cash'];
        $payments = [];

        // Generate monthly payments from Jan 2024 through Aug 2026.
        for ($year = 2024; $year <= 2026; $year++) {
            $maxMonth = $year === 2026 ? 8 : 12;

            for ($month = 1; $month <= $maxMonth; $month++) {
                // 3–6 payments per month.
                $count = fake()->numberBetween(3, 6);

                for ($i = 0; $i < $count; $i++) {
                    $plan = $plans->random();
                    $org = $orgs->random();
                    $day = fake()->numberBetween(1, Carbon::createFromDate($year, $month, 1)->daysInMonth);

                    $payments[] = [
                        'organization_id' => $org->id,
                        'plan_id' => $plan->id,
                        'amount' => (float) $plan->price,
                        'currency' => $plan->currency ?? 'KES',
                        'received_on' => sprintf('%04d-%02d-%02d', $year, $month, $day),
                        'method' => $methods[array_rand($methods)],
                        'reference' => strtoupper(fake()->lexify('????')).'-'.fake()->numerify('####'),
                        'notes' => fake()->optional(30)->sentence(),
                        'created_by' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }
        }

        foreach (array_chunk($payments, 200) as $chunk) {
            SubscriptionPayment::insert($chunk);
        }
    }

    private function seedPlatformExpenses(): void
    {
        if (PlatformExpense::count() > 0) {
            return;
        }

        $categories = PlatformExpense::CATEGORIES;
        $vendors = [
            'hosting' => ['AWS', 'DigitalOcean', 'Vercel'],
            'domain' => ['Namecheap', 'Cloudflare', 'GoDaddy'],
            'tooling' => ['GitHub', 'Linear', 'Figma'],
            'software' => ['Slack', 'Notion', 'Zoom'],
            'marketing' => ['Google Ads', 'Facebook', 'Twitter'],
            'other' => ['Office Supplies', 'Postage', 'Misc'],
        ];

        $expenses = [];

        for ($year = 2024; $year <= 2026; $year++) {
            $maxMonth = $year === 2026 ? 8 : 12;

            for ($month = 1; $month <= $maxMonth; $month++) {
                // 2–5 expenses per month.
                $count = fake()->numberBetween(2, 5);

                for ($i = 0; $i < $count; $i++) {
                    $category = $categories[array_rand($categories)];
                    $categoryVendors = $vendors[$category] ?? $vendors['other'];
                    $day = fake()->numberBetween(1, Carbon::createFromDate($year, $month, 1)->daysInMonth);

                    $expenses[] = [
                        'category' => $category,
                        'amount' => fake()->randomFloat(2, 500, 25000),
                        'currency' => 'KES',
                        'spent_on' => sprintf('%04d-%02d-%02d', $year, $month, $day),
                        'vendor_name' => $categoryVendors[array_rand($categoryVendors)],
                        'notes' => fake()->optional(40)->sentence(),
                        'created_by' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }
        }

        foreach (array_chunk($expenses, 200) as $chunk) {
            PlatformExpense::insert($chunk);
        }
    }
}
