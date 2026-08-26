<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\DestroyExpenseRequest;
use App\Http\Requests\Tenant\StoreExpenseRequest;
use App\Http\Requests\Tenant\UpdateExpenseRequest;
use App\Models\Expense;
use App\Models\LandParcel;
use App\Models\Property;
use App\Services\ExpenseService;
use App\Support\TenancyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Organization-level expense records. Expenses target a Property or a
 * LandParcel (optionally a Unit inside that property) and carry an
 * optional receipt stored in the tenant folder layout.
 */
class ExpenseController extends Controller
{
    public function index(Request $request): Response
    {
        $category = $request->string('category')->toString();

        $expenses = Expense::query()
            ->where('organization_id', TenancyContext::organization()?->id)
            ->when($category !== '', fn ($query) => $query->where('category', $category))
            ->with(['expenseable', 'unit:id,name'])
            ->withCount('media')
            ->orderByDesc('spent_on')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Expense $expense): array => [
                'id' => $expense->id,
                'asset_name' => $expense->expenseable?->name,
                'asset_type' => class_basename($expense->expenseable_type),
                'unit_name' => $expense->unit?->name,
                'category' => $expense->category,
                'amount' => $expense->amount,
                'currency' => $expense->currency,
                'spent_on' => $expense->spent_on?->toDateString(),
                'notes' => $expense->notes,
                'has_receipt' => $expense->media_count > 0,
                'receipt_url' => $expense->getFirstMedia('receipt')?->getUrl(),
            ]);

        return Inertia::render('expenses/index', [
            'expenses' => $expenses,
            'filters' => [
                'category' => $category,
            ],
            'categories' => Expense::CATEGORIES,
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('expenses/form', [
            'expense' => null,
            'targets' => $this->targets(),
        ]);
    }

    public function store(StoreExpenseRequest $request, ExpenseService $service): RedirectResponse
    {
        $expense = $service->create(
            $request->user(),
            TenancyContext::organization()?->id,
            $request->validated(),
        );

        $this->syncReceipt($request, $expense);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Expense recorded.']);

        return redirect()->route('tenant.expenses.index');
    }

    public function edit(Request $request, Expense $expense): Response
    {
        abort_if($expense->organization_id !== TenancyContext::organization()?->id, 403);

        $receipt = $expense->getFirstMedia('receipt');

        return Inertia::render('expenses/form', [
            'expense' => [
                'id' => $expense->id,
                'expenseable_type' => str_contains($expense->expenseable_type, 'LandParcel') ? 'land_parcel' : 'property',
                'expenseable_id' => $expense->expenseable_id,
                'unit_id' => $expense->unit_id,
                'category' => $expense->category,
                'amount' => $expense->amount,
                'currency' => $expense->currency,
                'spent_on' => $expense->spent_on?->toDateString(),
                'notes' => $expense->notes,
                'receipt_name' => $receipt?->file_name,
                'receipt_url' => $receipt?->getUrl(),
            ],
            'targets' => $this->targets(),
        ]);
    }

    public function update(UpdateExpenseRequest $request, Expense $expense, ExpenseService $service): RedirectResponse
    {
        abort_if($expense->organization_id !== TenancyContext::organization()?->id, 403);

        $expense = $service->update($expense, $request->user(), $request->validated());

        $this->syncReceipt($request, $expense);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Expense updated.']);

        return redirect()->route('tenant.expenses.index');
    }

    public function destroy(DestroyExpenseRequest $request, Expense $expense, ExpenseService $service): RedirectResponse
    {
        abort_if($expense->organization_id !== TenancyContext::organization()?->id, 403);

        $service->softDelete($expense);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Expense removed.']);

        return redirect()->route('tenant.expenses.index');
    }

    /**
     * Attach or remove the receipt. singleFile() semantics mean a fresh
     * upload replaces the previous one.
     */
    private function syncReceipt(Request $request, Expense $expense): void
    {
        if ($request->boolean('remove_receipt')) {
            $expense->clearMediaCollection('receipt');

            return;
        }

        if ($request->hasFile('receipt')) {
            $expense->addMediaFromRequest('receipt')->toMediaCollection('receipt');
        }
    }

    /**
     * Asset dropdowns for the expense form: every property (with its units)
     * and every land parcel in the organization.
     *
     * @return array{properties: array<int, array{id: int, name: string, slug: string, units: array<int, array{id: int, name: string}>}>, land_parcels: array<int, array{id: int, name: string, slug: string}>}
     */
    private function targets(): array
    {
        $organizationId = TenancyContext::organization()?->id;

        return [
            'properties' => Property::query()
                ->where('organization_id', $organizationId)
                ->with('units:id,property_id,name')
                ->get(['id', 'name', 'slug'])
                ->map(fn (Property $property) => [
                    'id' => $property->id,
                    'name' => $property->name,
                    'slug' => $property->slug,
                    'units' => $property->units
                        ->map(fn ($unit) => ['id' => $unit->id, 'name' => $unit->name])
                        ->all(),
                ])
                ->all(),
            'land_parcels' => LandParcel::query()
                ->where('organization_id', $organizationId)
                ->get(['id', 'name', 'slug'])
                ->map(fn (LandParcel $parcel) => [
                    'id' => $parcel->id,
                    'name' => $parcel->name,
                    'slug' => $parcel->slug,
                ])
                ->all(),
        ];
    }
}
