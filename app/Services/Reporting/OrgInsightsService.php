<?php

namespace App\Services\Reporting;

use App\Models\Expense;
use App\Models\Lease;
use App\Models\MaintenanceRequest;
use App\Models\Property;
use App\Models\Unit;
use Illuminate\Support\Facades\DB;

/**
 * Deterministic, read-only insight queries. The AI assistant's tools call
 * these — one source of truth so the LLM can never invent numbers.
 */
class OrgInsightsService
{
    public function __construct(private int $organizationId) {}

    /**
     * Vacancy: total/vacant/occupied units per property.
     */
    public function vacancySummary(): array
    {
        return Unit::query()
            ->whereHas('property', fn ($query) => $query
                ->where('organization_id', $this->organizationId)
                ->whereNull('deleted_at'))
            ->select('property_id', DB::raw('count(*) as total'))
            ->with('property:id,name,slug')
            ->groupBy('property_id')
            ->get()
            ->map(function ($row) {
                $vacant = Unit::where('property_id', $row->property_id)->where('status', 'vacant')->count();

                return [
                    'property' => $row->property?->name,
                    'total_units' => (int) $row->total,
                    'vacant' => $vacant,
                    'occupied' => (int) $row->total - $vacant,
                ];
            })
            ->all();
    }

    /**
     * Active leases + those expiring within the next 60 days.
     */
    public function leaseSummary(): array
    {
        $base = Lease::query()->where('organization_id', $this->organizationId);

        return [
            'active_leases' => (clone $base)->where('status', 'active')->whereNull('ends_at')->count(),
            'expiring_within_60_days' => (clone $base)
                ->where('status', 'active')
                ->whereNotNull('ends_at')
                ->whereBetween('ends_at', [now()->toDateString(), now()->addDays(60)->toDateString()])
                ->count(),
        ];
    }

    /**
     * Expense totals grouped by category, optionally by asset.
     */
    public function expenseTotals(?string $category = null): array
    {
        $rows = Expense::query()
            ->where('organization_id', $this->organizationId)
            ->when($category, fn ($query) => $query->where('category', $category))
            ->select(
                'category',
                DB::raw('sum(amount) as total'),
                DB::raw('count(*) as count'),
            )
            ->groupBy('category')
            ->get();

        return [
            'grand_total' => (float) $rows->sum('total'),
            'by_category' => $rows->map(fn ($row) => [
                'category' => $row->category,
                'total' => (float) $row->total,
                'count' => (int) $row->count,
            ])->all(),
        ];
    }

    /**
     * Maintenance backlog by status and priority.
     */
    public function maintenanceBacklog(): array
    {
        $requests = MaintenanceRequest::query()
            ->where('organization_id', $this->organizationId);

        return [
            'open_total' => (clone $requests)->whereIn('status', ['opened', 'assigned', 'in_progress'])->count(),
            'urgent_open' => (clone $requests)->whereIn('status', ['opened', 'assigned', 'in_progress'])->where('priority', 'urgent')->count(),
            'resolved' => (clone $requests)->where('status', 'resolved')->count(),
            'closed' => (clone $requests)->where('status', 'closed')->count(),
        ];
    }

    /**
     * Properties list with names — helps the model reference assets.
     */
    public function properties(): array
    {
        return Property::query()
            ->where('organization_id', $this->organizationId)
            ->get(['name', 'slug'])
            ->map(fn (Property $property) => ['name' => $property->name, 'slug' => $property->slug])
            ->all();
    }
}
