<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public marketing landing. Pricing is read from the plans table so the
 * page can never drift from what is actually for sale.
 */
class HomeController extends Controller
{
    public function __invoke(): Response
    {
        $plans = Plan::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('price')
            ->get([
                'name',
                'slug',
                'description',
                'currency',
                'price',
                'properties_limit',
                'units_limit',
            ])
            ->values();

        // The featured card is the median by price — no hardcoded tier names,
        // so renaming plans never silently breaks the highlight.
        $featuredIndex = $plans->isNotEmpty()
            ? (int) floor($plans->count() / 2)
            : null;

        return Inertia::render('welcome', [
            'plans' => $plans,
            'featuredPlan' => $featuredIndex,
        ]);
    }
}
