<?php

namespace App\Http\Controllers\Searcher;

use App\Http\Controllers\Controller;
use App\Models\Lease;
use App\Models\Person;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LeaseController extends Controller
{
    /**
     * Show the searcher's own rental history across all organizations.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        $person = $user?->person_id
            ? Person::find($user->person_id)
            : null;

        $leases = $person
            ? Lease::forPerson($person)
                ->with(['property', 'unit', 'organization'])
                ->orderByDesc('starts_at')
                ->get()
                ->map(function (Lease $lease): array {
                    return [
                        'id' => $lease->id,
                        'property_name' => $lease->property?->name,
                        'unit_name' => $lease->unit?->name,
                        'organization_name' => $lease->organization?->name,
                        'starts_at' => $lease->starts_at?->toDateString(),
                        'ends_at' => $lease->ends_at?->toDateString(),
                        'rent_amount' => $lease->rent_amount,
                        'rent_frequency' => $lease->rent_frequency,
                        'currency' => $lease->currency,
                        'deposit' => $lease->deposit,
                        'agreement_text' => $lease->agreement_text,
                        'status' => $lease->status,
                        'duration_in_days' => $lease->durationInDays(),
                        'total_cost' => $lease->totalCost(),
                        'is_active' => $lease->isActive(),
                    ];
                })
            : collect();

        return Inertia::render('searcher/rentals', [
            'leases' => $leases,
        ]);
    }
}
