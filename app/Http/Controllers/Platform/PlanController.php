<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DestroyPlanRequest;
use App\Http\Requests\Admin\StorePlanRequest;
use App\Http\Requests\Admin\UpdatePlanRequest;
use App\Http\Requests\Admin\UpdatePlanStatusRequest;
use App\Models\Plan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlanController extends Controller
{
    /**
     * Allowlisted so a crafted `?sort=` can never reach the ORDER BY.
     *
     * `features` is absent: it is a JSON column and ordering by it would be a
     * lexicographic sort of a serialised blob, which is not an ordering anyone
     * scanning this list is looking for.
     *
     * @var array<string, string>
     */
    private const SORTABLE = [
        'name' => 'name',
        'slug' => 'slug',
        'price' => 'price',
        'sort_order' => 'sort_order',
        'is_active' => 'is_active',
        'organizations_count' => 'organizations_count',
    ];

    /**
     * The shared search/filter base for the table and any CSV export, so the two
     * can never disagree about what "the current list" means.
     *
     * @return Builder<Plan>
     */
    private function filtered(Request $request): Builder
    {
        return Plan::query()
            ->withCount('organizations')
            // `Request::string()` returns a Stringable and an object is always truthy,
            // so `->when($request->string('search')->trim(), ...)` ran the filter even
            // with no term — binding `like '%%'`, and for a category also `= ''`, which
            // matched nothing and emptied the table. ->toString() gives a real string.
            ->when($request->string('search')->trim()->toString(), fn ($query, string $search) => $query->where(function (Builder $q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            }))
            ->when(
                $request->string('status')->toString() !== '',
                fn ($query) => $query->where('is_active', $request->string('status')->toString() === 'active'),
            );
    }

    public function index(Request $request): Response
    {
        $sort = $request->string('sort')->toString();
        $sortColumn = self::SORTABLE[$sort] ?? null;
        $direction = $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc';

        $plans = $this->filtered($request)
            ->when(
                $sortColumn !== null,
                fn (Builder $query) => $query->orderBy($sortColumn, $direction),
            )
            // Default order is the merchandising order (cheapest tier first), and
            // it is ascending — so the unsorted default must not inherit the
            // `direction` default of desc, or clicking away from a sort flips it.
            ->when($sortColumn === null, fn (Builder $query) => $query->orderBy('sort_order')->orderBy('name'))
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Plan $plan): array => $this->serialize($plan));

        // The row overlay is addressed by URL, so it must work from a shared
        // link and not only from a tap on a visible row. Same fallback as the
        // organizations and users indexes: fetch the row only when the page
        // cannot supply it.
        $focusedId = $request->integer('plan') ?: null;

        $focused = null;

        if ($focusedId !== null && ! $plans->getCollection()->contains('id', $focusedId)) {
            $focused = Plan::query()
                ->withCount('organizations')
                ->find($focusedId);
        }

        return Inertia::render('platform/plans/index', [
            'plans' => $plans,
            'focusedPlan' => $focused ? $this->serialize($focused) : null,
            'filters' => [
                'search' => (string) $request->string('search'),
                'status' => (string) $request->string('status'),
            ],
            'sort' => [
                'column' => $sortColumn === null ? null : $sort,
                'direction' => $direction,
            ],
        ]);
    }

    /**
     * One row shape for both the paginated table and the focused row, so the
     * overlay renders identically whichever way the row was resolved.
     *
     * @return array<string, mixed>
     */
    private function serialize(Plan $plan): array
    {
        return [
            'id' => $plan->id,
            'name' => $plan->name,
            'slug' => $plan->slug,
            'description' => $plan->description,
            'currency' => $plan->currency,
            'price' => $plan->price,
            'properties_limit' => $plan->properties_limit,
            'units_limit' => $plan->units_limit,
            'has_dedicated_db' => $plan->has_dedicated_db,
            'has_custom_domain' => $plan->has_custom_domain,
            'has_email_notifications' => $plan->has_email_notifications,
            'has_sms_notifications' => $plan->has_sms_notifications,
            'features' => $plan->features ?? [],
            'is_active' => $plan->is_active,
            'sort_order' => $plan->sort_order,
            'organizations_count' => (int) ($plan->getAttribute('organizations_count') ?? 0),
        ];
    }

    public function store(StorePlanRequest $request): RedirectResponse
    {
        Plan::create(array_merge(
            $request->validated(),
            ['created_by' => $request->user()?->id],
        ));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Plan created.']);

        return redirect()->route('platform.plans.index');
    }

    public function update(UpdatePlanRequest $request, Plan $plan): RedirectResponse
    {
        $plan->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Plan updated.']);

        return redirect()->route('platform.plans.index');
    }

    /**
     * Suspend/reactivate a plan from the table's action cell.
     *
     * `is_active` gates which tiers a new organization can be put on, so it is
     * a state change worth confirming, but not worth a separate page.
     */
    public function setStatus(UpdatePlanStatusRequest $request, Plan $plan): RedirectResponse
    {
        $plan->update(['is_active' => $request->boolean('is_active')]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $plan->is_active
                ? "{$plan->name} is active again."
                : "{$plan->name} is now inactive.",
        ]);

        return back();
    }

    public function destroy(DestroyPlanRequest $request, Plan $plan): RedirectResponse
    {
        $plan->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Plan deleted.']);

        return redirect()->route('platform.plans.index');
    }
}
