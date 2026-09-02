<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DestroyPlatformExpenseRequest;
use App\Http\Requests\Admin\StorePlatformExpenseRequest;
use App\Http\Requests\Admin\UpdatePlatformExpenseRequest;
use App\Models\PlatformExpense;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlatformExpenseController extends Controller
{
    public function index(Request $request): Response
    {
        $expenses = PlatformExpense::query()
            ->when($request->string('search')->trim(), fn ($query, string $search) => $query->where(function ($q) use ($search) {
                $q->where('category', 'like', "%{$search}%")
                    ->orWhere('vendor_name', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%");
            }))
            ->when($request->string('category')->trim(), fn ($query, string $category) => $query->where('category', $category))
            ->orderByDesc('spent_on')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('platform/expenses/index', [
            'expenses' => $expenses,
            'filters' => [
                'search' => (string) $request->string('search'),
                'category' => (string) $request->string('category'),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('platform/expenses/create');
    }

    public function store(StorePlatformExpenseRequest $request): RedirectResponse
    {
        PlatformExpense::create(array_merge(
            $request->validated(),
            ['created_by' => $request->user()?->id],
        ));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Platform expense recorded.']);

        return redirect()->route('platform.expenses.index');
    }

    public function edit(PlatformExpense $platformExpense): Response
    {
        return Inertia::render('platform/expenses/edit', [
            'expense' => $platformExpense,
        ]);
    }

    public function update(UpdatePlatformExpenseRequest $request, PlatformExpense $platformExpense): RedirectResponse
    {
        $platformExpense->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Platform expense updated.']);

        return redirect()->route('platform.expenses.index');
    }

    public function destroy(DestroyPlatformExpenseRequest $request, PlatformExpense $platformExpense): RedirectResponse
    {
        $platformExpense->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Platform expense deleted.']);

        return redirect()->route('platform.expenses.index');
    }
}
