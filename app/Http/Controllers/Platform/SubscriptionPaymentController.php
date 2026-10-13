<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DestroySubscriptionPaymentRequest;
use App\Http\Requests\Admin\StoreSubscriptionPaymentRequest;
use App\Http\Requests\Admin\UpdateSubscriptionPaymentRequest;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\SubscriptionPayment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SubscriptionPaymentController extends Controller
{
    /**
     * Allowlisted so a crafted `?sort=` can never reach the ORDER BY.
     *
     * `organization_id` is deliberately absent even though it is a real column:
     * sorting by it would order by an opaque id rather than by the name the
     * table actually shows, which is a different and much less useful sort than
     * it appears to be. Sorting by the organization's *name* needs a join or a
     * correlated subquery, so there is no such sort until one is worth paying
     * for.
     *
     * @var array<string, string>
     */
    private const SORTABLE = [
        'amount' => 'amount',
        'received_on' => 'received_on',
        'method' => 'method',
        'reference' => 'reference',
        'currency' => 'currency',
        'created_at' => 'created_at',
    ];

    /**
     * The shared search/filter base for the table and any CSV export, so the two
     * can never disagree about what "the current list" means.
     *
     * @return Builder<SubscriptionPayment>
     */
    private function filtered(Request $request): Builder
    {
        return SubscriptionPayment::query()
            ->with(['organization:id,name', 'plan:id,name'])
            // `Request::string()` returns a Stringable and an object is always truthy,
            // so `->when($request->string('search')->trim(), ...)` ran the filter even
            // with no term — binding `like '%%'`, and for a category also `= ''`, which
            // matched nothing and emptied the table. ->toString() gives a real string.
            ->when($request->string('search')->trim()->toString(), fn ($query, string $search) => $query->where(function (Builder $q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                    ->orWhere('method', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhereHas('organization', fn (Builder $org) => $org->where('name', 'like', "%{$search}%"));
            }))
            ->when(
                $request->string('from')->toString() !== '',
                fn ($query) => $query->whereDate('received_on', '>=', $request->string('from')->toString()),
            )
            ->when(
                $request->string('to')->toString() !== '',
                fn ($query) => $query->whereDate('received_on', '<=', $request->string('to')->toString()),
            );
    }

    public function index(Request $request): Response
    {
        $sort = $request->string('sort')->toString();
        $sortColumn = self::SORTABLE[$sort] ?? null;
        $direction = $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc';

        $payments = $this->filtered($request)
            ->when(
                $sortColumn !== null,
                fn (Builder $query) => $query->orderBy($sortColumn, $direction),
            )
            // Newest first is the only useful default for income: it is what
            // someone checking "did this month's money come in?" wants to see.
            ->when($sortColumn === null, fn (Builder $query) => $query->orderByDesc('received_on'))
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (SubscriptionPayment $payment): array => $this->serialize($payment));

        // The row overlay is addressed by URL, so it must work from a shared
        // link and not only from a tap on a visible row. Same fallback as the
        // organizations, users and plans indexes.
        $focusedId = $request->integer('payment') ?: null;

        $focused = null;

        if ($focusedId !== null && ! $payments->getCollection()->contains('id', $focusedId)) {
            $focused = SubscriptionPayment::query()
                ->with(['organization:id,name', 'plan:id,name'])
                ->find($focusedId);
        }

        return Inertia::render('platform/subscription-payments/index', [
            'payments' => $payments,
            'focusedPayment' => $focused ? $this->serialize($focused) : null,
            'filters' => [
                'search' => (string) $request->string('search'),
                'from' => (string) $request->string('from'),
                'to' => (string) $request->string('to'),
            ],
            'sort' => [
                'column' => $sortColumn === null ? null : $sort,
                'direction' => $direction,
            ],
            // Optional rather than eager: both lists are unbounded — a
            // platform will eventually have thousands of organizations — and
            // only the create sheet reads them. Sending them eagerly would
            // re-query and re-serialise both on every debounced keystroke,
            // sort, page change and sheet open.
            'organizations' => Inertia::optional(fn () => $this->organizationOptions()),
            'plans' => Inertia::optional(fn () => $this->planOptions()),
        ]);
    }

    /**
     * One row shape for both the paginated table and the focused row, so the
     * overlay renders identically whichever way the row was resolved.
     *
     * @return array<string, mixed>
     */
    private function serialize(SubscriptionPayment $payment): array
    {
        return [
            'id' => $payment->id,
            'organization_id' => $payment->organization_id,
            'organization' => $payment->organization
                ? ['id' => $payment->organization->id, 'name' => $payment->organization->name]
                : null,
            'plan_id' => $payment->plan_id,
            'plan' => $payment->plan
                ? ['id' => $payment->plan->id, 'name' => $payment->plan->name]
                : null,
            // `amount` is cast to `decimal:2`, so the attribute is a *string*.
            // A float is safe here — money in this app is whole-currency
            // amounts — and it lets the column sort and align numerically
            // without a Number() at every call site.
            'amount' => (float) $payment->amount,
            'currency' => $payment->currency,
            'received_on' => $payment->received_on?->format('Y-m-d') ?? '',
            'period_start' => $payment->period_start?->format('Y-m-d'),
            'period_end' => $payment->period_end?->format('Y-m-d'),
            'method' => $payment->method,
            'reference' => $payment->reference,
            'notes' => $payment->notes,
        ];
    }

    public function store(StoreSubscriptionPaymentRequest $request): RedirectResponse
    {
        SubscriptionPayment::create(array_merge(
            $request->validated(),
            ['created_by' => $request->user()?->id],
        ));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Subscription payment recorded.']);

        return redirect()->route('platform.subscription-payments.index');
    }

    public function update(UpdateSubscriptionPaymentRequest $request, SubscriptionPayment $subscriptionPayment): RedirectResponse
    {
        $subscriptionPayment->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Subscription payment updated.']);

        return redirect()->route('platform.subscription-payments.index');
    }

    public function destroy(DestroySubscriptionPaymentRequest $request, SubscriptionPayment $subscriptionPayment): RedirectResponse
    {
        $subscriptionPayment->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Subscription payment deleted.']);

        return redirect()->route('platform.subscription-payments.index');
    }

    /**
     * @return list<array{value: int, label: string}>
     */
    private function organizationOptions(): array
    {
        return Organization::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Organization $org) => ['value' => $org->id, 'label' => $org->name])
            ->all();
    }

    /**
     * @return list<array{value: int, label: string}>
     */
    private function planOptions(): array
    {
        return Plan::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Plan $plan) => ['value' => $plan->id, 'label' => $plan->name])
            ->all();
    }
}
