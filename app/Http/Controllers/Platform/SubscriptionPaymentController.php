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
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SubscriptionPaymentController extends Controller
{
    public function index(Request $request): Response
    {
        $payments = SubscriptionPayment::query()
            ->with(['organization:id,name', 'plan:id,name'])
            ->when($request->string('search')->trim(), fn ($query, string $search) => $query->where(function ($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                    ->orWhere('method', 'like', "%{$search}%")
                    ->orWhereHas('organization', fn ($org) => $org->where('name', 'like', "%{$search}%"));
            }))
            ->orderByDesc('received_on')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('platform/subscription-payments/index', [
            'payments' => $payments,
            'filters' => [
                'search' => (string) $request->string('search'),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('platform/subscription-payments/create', [
            'organizations' => $this->organizationOptions(),
            'plans' => $this->planOptions(),
        ]);
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

    public function edit(SubscriptionPayment $subscriptionPayment): Response
    {
        return Inertia::render('platform/subscription-payments/edit', [
            'payment' => $subscriptionPayment,
            'organizations' => $this->organizationOptions(),
            'plans' => $this->planOptions(),
        ]);
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
