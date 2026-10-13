<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DestroyPlatformExpenseRequest;
use App\Http\Requests\Admin\StorePlatformExpenseRequest;
use App\Http\Requests\Admin\UpdatePlatformExpenseRequest;
use App\Models\PlatformExpense;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlatformExpenseController extends Controller
{
    /**
     * Allowlisted so a crafted `?sort=` can never reach the ORDER BY.
     *
     * @var array<string, string>
     */
    private const SORTABLE = [
        'spent_on' => 'spent_on',
        'amount' => 'amount',
        'category' => 'category',
        'vendor_name' => 'vendor_name',
        'created_at' => 'created_at',
    ];

    /**
     * The shared search/filter base for the table and any CSV export, so the two
     * can never disagree about what "the current list" means.
     *
     * @return Builder<PlatformExpense>
     */
    private function filtered(Request $request): Builder
    {
        return PlatformExpense::query()
            // `Request::string()` returns a Stringable and an object is always truthy,
            // so `->when($request->string('search')->trim(), ...)` ran the filter even
            // with no term — binding `like '%%'`, and for a category also `= ''`, which
            // matched nothing and emptied the table. ->toString() gives a real string.
            ->when($request->string('search')->trim()->toString(), fn ($query, string $search) => $query->where(function (Builder $q) use ($search) {
                $q->where('category', 'like', "%{$search}%")
                    ->orWhere('vendor_name', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%");
            }))
            ->when(
                $request->string('category')->trim()->toString(),
                fn ($query) => $query->where('category', $request->string('category')->toString()),
            )
            ->when(
                $request->string('from')->toString() !== '',
                fn ($query) => $query->whereDate('spent_on', '>=', $request->string('from')->toString()),
            )
            ->when(
                $request->string('to')->toString() !== '',
                fn ($query) => $query->whereDate('spent_on', '<=', $request->string('to')->toString()),
            );
    }

    public function index(Request $request): Response
    {
        $sort = $request->string('sort')->toString();
        $sortColumn = self::SORTABLE[$sort] ?? null;
        $direction = $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc';

        $expenses = $this->filtered($request)
            ->when(
                $sortColumn !== null,
                fn (Builder $query) => $query->orderBy($sortColumn, $direction),
            )
            // Newest spend first: the question this list answers is "what did we
            // spend money on lately", and that is also what the `desc` direction
            // default is for, so the unsorted branch matches it.
            ->when($sortColumn === null, fn (Builder $query) => $query->orderByDesc('spent_on'))
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (PlatformExpense $expense): array => $this->serialize($expense));

        // The row overlay is addressed by URL, so it must work from a shared
        // link and not only from a tap on a visible row.
        $focusedId = $request->integer('expense') ?: null;

        $focused = null;

        if ($focusedId !== null && ! $expenses->getCollection()->contains('id', $focusedId)) {
            $focused = PlatformExpense::query()->find($focusedId);
        }

        return Inertia::render('platform/expenses/index', [
            'expenses' => $expenses,
            'focusedExpense' => $focused ? $this->serialize($focused) : null,
            'filters' => [
                'search' => (string) $request->string('search'),
                'category' => (string) $request->string('category'),
                'from' => (string) $request->string('from'),
                'to' => (string) $request->string('to'),
            ],
            'sort' => [
                'column' => $sortColumn === null ? null : $sort,
                'direction' => $direction,
            ],
            'categories' => PlatformExpense::CATEGORIES,
        ]);
    }

    /**
     * One row shape for both the paginated table and the focused row, so the
     * overlay renders identically whichever way the row was resolved.
     *
     * @return array<string, mixed>
     */
    private function serialize(PlatformExpense $expense): array
    {
        return [
            'id' => $expense->id,
            'category' => $expense->category,
            // `amount` is cast to `decimal:2`, so the attribute is a *string*.
            // A float is safe here — money in this app is whole-currency
            // amounts — and it lets the column sort and align numerically
            // without a Number() at every call site.
            'amount' => (float) $expense->amount,
            'currency' => $expense->currency,
            'spent_on' => $expense->spent_on?->format('Y-m-d') ?? '',
            'vendor_name' => $expense->vendor_name,
            'notes' => $expense->notes,
        ];
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
