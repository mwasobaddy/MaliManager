<?php

namespace App\Services\Reporting;

use App\Models\Expense;
use App\Models\LandParcel;
use App\Models\Lease;
use App\Models\MaintenanceRequest;
use App\Models\Property;
use Carbon\CarbonImmutable;

/**
 * Deterministic report datasets for the Reports section. Every method
 * returns the same envelope so one generic table renderer (+ CSV/print)
 * can serve all four reports:
 *
 *   ['title', 'columns' => [{key,label}], 'rows' => [assoc], 'summary' => [label => value]]
 *
 * Date ranges apply to the natural time axis of each dataset.
 */
class ReportService
{
    public const TYPES = ['occupancy', 'leases', 'expenses', 'maintenance', 'market'];

    public function __construct(private int $organizationId) {}

    /**
     * @param  string|null  $from  Y-m-d, null = unbounded
     * @param  string|null  $to  Y-m-d, null = unbounded
     */
    public function build(string $type, ?string $from, ?string $to): array
    {
        return match ($type) {
            'occupancy' => $this->occupancy(),
            'leases' => $this->leases($from, $to),
            'expenses' => $this->expenses($from, $to),
            'maintenance' => $this->maintenance($from, $to),
            'market' => $this->market(),
            default => abort(422, 'Unknown report type.'),
        };
    }

    // ── Occupancy ─────────────────────────────────────────────────────────

    private function occupancy(): array
    {
        $properties = Property::query()
            ->where('organization_id', $this->organizationId)
            ->whereNull('deleted_at')
            ->with('units:id,property_id,status')
            ->get(['id', 'name']);

        $parcelsExist = LandParcel::query()
            ->where('organization_id', $this->organizationId)
            ->exists();

        $rows = $properties->map(fn (Property $property): array => [
            'asset' => $property->name,
            'type' => 'Property',
            'total_units' => $property->units->count(),
            'vacant' => $property->units->where('status', 'vacant')->count(),
            'occupied' => $property->units->where('status', 'occupied')->count(),
        ])->all();

        if ($parcelsExist) {
            $rows[] = [
                'asset' => LandParcel::where('organization_id', $this->organizationId)->count().' land parcel(s)',
                'type' => 'LandParcel',
                'total_units' => '—',
                'vacant' => '—',
                'occupied' => '—',
            ];
        }

        return [
            'title' => 'Occupancy report',
            'columns' => [
                ['key' => 'asset', 'label' => 'Asset'],
                ['key' => 'type', 'label' => 'Type'],
                ['key' => 'total_units', 'label' => 'Units'],
                ['key' => 'vacant', 'label' => 'Vacant'],
                ['key' => 'occupied', 'label' => 'Occupied'],
            ],
            'rows' => $rows,
            'summary' => [],
        ];
    }

    // ── Lease pipeline ────────────────────────────────────────────────────

    private function leases(?string $from, ?string $to): array
    {
        $query = Lease::query()
            ->where('organization_id', $this->organizationId)
            ->when($from, fn ($query) => $query->where('starts_at', '>=', $from))
            ->when($to, fn ($query) => $query->where('starts_at', '<=', $to))
            ->with(['unit:id,name', 'person:id,first_name,last_name'])
            ->orderByDesc('starts_at');

        $leases = $query->get();

        $activeCount = $leases->where('status', 'active')->whereNull('ends_at')->count();

        return [
            'title' => 'Lease pipeline',
            'columns' => [
                ['key' => 'unit', 'label' => 'Unit'],
                ['key' => 'occupant', 'label' => 'Occupant'],
                ['key' => 'rent', 'label' => 'Rent'],
                ['key' => 'status', 'label' => 'Status'],
                ['key' => 'starts_at', 'label' => 'Start'],
                ['key' => 'ends_at', 'label' => 'End'],
            ],
            'rows' => $leases->map(fn (Lease $lease): array => [
                'unit' => $lease->unit?->name,
                'occupant' => trim(($lease->person?->first_name ?? '').' '.($lease->person?->last_name ?? '')),
                'rent' => $lease->rent_amount !== null
                    ? number_format((float) $lease->rent_amount, 2).' '.($lease->currency ?? '')
                    : '—',
                'status' => $lease->status,
                'starts_at' => $lease->starts_at?->toDateString(),
                'ends_at' => $lease->ends_at?->toDateString() ?? ($lease->status === 'active' ? 'Open-ended' : '—'),
            ])->all(),
            'summary' => [
                'Total leases' => $leases->count(),
                'Active (open-ended)' => $activeCount,
            ],
        ];
    }

    // ── Expenses ──────────────────────────────────────────────────────────

    private function expenses(?string $from, ?string $to): array
    {
        $query = Expense::query()
            ->where('organization_id', $this->organizationId)
            ->when($from, fn ($query) => $query->where('spent_on', '>=', $from))
            ->when($to, fn ($query) => $query->where('spent_on', '<=', $to))
            ->with(['unit:id,name'])
            ->orderByDesc('spent_on');

        $expenses = $query->get(['id', 'expenseable_type', 'expenseable_id', 'unit_id', 'category', 'amount', 'currency', 'spent_on', 'notes']);

        $assetNames = $this->assetNamesFor($expenses);

        $total = (float) $expenses->sum('amount');

        return [
            'title' => 'Expenses report',
            'columns' => [
                ['key' => 'spent_on', 'label' => 'Date'],
                ['key' => 'asset', 'label' => 'Asset'],
                ['key' => 'unit', 'label' => 'Unit'],
                ['key' => 'category', 'label' => 'Category'],
                ['key' => 'amount', 'label' => 'Amount'],
                ['key' => 'notes', 'label' => 'Notes'],
            ],
            'rows' => $expenses->map(fn (Expense $expense): array => [
                'spent_on' => $expense->spent_on?->toDateString(),
                'asset' => $assetNames[$expense->expenseable_type.'#'.$expense->expenseable_id] ?? '—',
                'unit' => $expense->unit?->name ?? '—',
                'category' => $expense->category,
                'amount' => number_format((float) $expense->amount, 2).' '.($expense->currency ?? ''),
                'notes' => $expense->notes,
            ])->all(),
            'summary' => [
                'Total expenses' => number_format($total, 2).' KES',
                'Entries' => $expenses->count(),
            ],
        ];
    }

    // ── Maintenance ───────────────────────────────────────────────────────

    private function maintenance(?string $from, ?string $to): array
    {
        $requests = MaintenanceRequest::query()
            ->where('organization_id', $this->organizationId)
            ->when($from, fn ($query) => $query->where('created_at', '>=', CarbonImmutable::parse($from)->startOfDay()))
            ->when($to, fn ($query) => $query->where('created_at', '<=', CarbonImmutable::parse($to)->endOfDay()))
            ->with(['unit:id,name', 'assignee:id,name'])
            ->orderByDesc('created_at')
            ->get();

        $resolved = $requests->filter(fn (MaintenanceRequest $r) => $r->resolved_at !== null);
        $avgDays = $resolved->isNotEmpty()
            ? round($resolved->avg(fn (MaintenanceRequest $r) => $r->created_at->diffInDays($r->resolved_at)), 1)
            : null;

        return [
            'title' => 'Maintenance report',
            'columns' => [
                ['key' => 'created_at', 'label' => 'Raised'],
                ['key' => 'title', 'label' => 'Request'],
                ['key' => 'unit', 'label' => 'Unit'],
                ['key' => 'priority', 'label' => 'Priority'],
                ['key' => 'status', 'label' => 'Status'],
                ['key' => 'assignee', 'label' => 'Assigned to'],
                ['key' => 'days_to_resolve', 'label' => 'Days to resolve'],
            ],
            'rows' => $requests->map(fn (MaintenanceRequest $request): array => [
                'created_at' => $request->created_at?->toDateString(),
                'title' => $request->title,
                'unit' => $request->unit?->name ?? '—',
                'priority' => $request->priority,
                'status' => str_replace('_', ' ', $request->status),
                'assignee' => $request->assignee?->name ?? 'Unassigned',
                'days_to_resolve' => $request->resolved_at
                    ? $request->created_at->diffInDays($request->resolved_at)
                    : '—',
            ])->all(),
            'summary' => [
                'Requests' => $requests->count(),
                'Resolved' => $resolved->count(),
                'Avg days to resolve' => $avgDays ?? '—',
            ],
        ];
    }

    // ── Market (internal portfolio analysis) ──────────────────────────────

    /**
     * Internal market analysis: per-property occupancy and current rent
     * levels derived from active leases. No external data required.
     */
    private function market(): array
    {
        $properties = Property::query()
            ->where('organization_id', $this->organizationId)
            ->whereNull('deleted_at')
            ->with(['units:id,property_id,status'])
            ->get(['id', 'name']);

        $rentByProperty = Lease::query()
            ->where('organization_id', $this->organizationId)
            ->where('status', 'active')
            ->select(
                'property_id',
                DB::raw('avg(rent_amount) as avg_rent'),
                DB::raw('min(rent_amount) as min_rent'),
                DB::raw('max(rent_amount) as max_rent'),
                DB::raw('count(*) as leases'),
            )
            ->groupBy('property_id')
            ->get()
            ->keyBy('property_id');

        $rows = $properties->map(function (Property $property) use ($rentByProperty): array {
            $units = $property->units;
            $total = $units->count();
            $occupied = $units->where('status', 'occupied')->count();
            $rents = $rentByProperty->get($property->id);

            return [
                'asset' => $property->name,
                'units' => $total,
                'occupied' => $occupied,
                'occupancy_pct' => $total > 0 ? round(($occupied / $total) * 100).'%' : '—',
                'active_leases' => $rents?->leases ?? 0,
                'avg_rent' => $rents ? number_format((float) $rents->avg_rent, 0) : '—',
                'min_max_rent' => $rents
                    ? number_format((float) $rents->min_rent, 0).' – '.number_format((float) $rents->max_rent, 0)
                    : '—',
            ];
        })->all();

        return [
            'title' => 'Internal market analysis',
            'columns' => [
                ['key' => 'asset', 'label' => 'Asset'],
                ['key' => 'units', 'label' => 'Units'],
                ['key' => 'occupied', 'label' => 'Occupied'],
                ['key' => 'occupancy_pct', 'label' => 'Occupancy'],
                ['key' => 'active_leases', 'label' => 'Active leases'],
                ['key' => 'avg_rent', 'label' => 'Avg rent'],
                ['key' => 'min_max_rent', 'label' => 'Rent range'],
            ],
            'rows' => $rows,
            'summary' => [],
        ];
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    /**
     * Map "Type#id" keys to display names for expense assets.
     *
     * @return array<string, string>
     */
    private function assetNamesFor($expenses): array
    {
        $propertyIds = $expenses->where('expenseable_type', Property::class)->pluck('expenseable_id')->unique();
        $parcelIds = $expenses->where('expenseable_type', LandParcel::class)->pluck('expenseable_id')->unique();

        $names = [];

        foreach (Property::whereIn('id', $propertyIds)->get(['id', 'name']) as $property) {
            $names[Property::class.'#'.$property->id] = $property->name;
        }

        foreach (LandParcel::whereIn('id', $parcelIds)->get(['id', 'name']) as $parcel) {
            $names[LandParcel::class.'#'.$parcel->id] = $parcel->name.' (land parcel)';
        }

        return $names;
    }
}
