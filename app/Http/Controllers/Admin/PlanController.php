<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePlanRequest;
use App\Http\Requests\Admin\UpdatePlanRequest;
use App\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlanController extends Controller
{
    public function index(Request $request): Response
    {
        $plans = Plan::query()
            ->withCount('organizations')
            ->when($request->string('search')->trim(), fn ($query, string $search) => $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            }))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/plans/index', [
            'plans' => $plans,
            'filters' => [
                'search' => (string) $request->string('search'),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/plans/create');
    }

    public function store(StorePlanRequest $request): RedirectResponse
    {
        Plan::create(array_merge(
            $request->validated(),
            ['created_by' => $request->user()?->id],
        ));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Plan created.']);

        return redirect()->route('plans.index');
    }

    public function edit(Plan $plan): Response
    {
        return Inertia::render('admin/plans/edit', [
            'plan' => $plan,
        ]);
    }

    public function update(UpdatePlanRequest $request, Plan $plan): RedirectResponse
    {
        $plan->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Plan updated.']);

        return redirect()->route('plans.index');
    }

    public function destroy(Plan $plan): RedirectResponse
    {
        $plan->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Plan deleted.']);

        return redirect()->route('plans.index');
    }
}
