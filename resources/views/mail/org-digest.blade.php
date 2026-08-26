@component('mail::message')

# Weekly digest — {{ $organization->name }}

@if ($narrative)
{{ $narrative }}
@endif

@component('mail::panel')
**Last 7 days**

- Leases started: **{{ $numbers['leases_started'] }}**
- Leases ended: **{{ $numbers['leases_ended'] }}**
- Expenses: **KES {{ number_format($numbers['expenses_total'], 2) }}** ({{ $numbers['expenses_count'] }} entries)
- Maintenance opened: **{{ $numbers['maintenance_opened'] }}** (resolved: {{ $numbers['maintenance_resolved'] }})
- Urgent still open: **{{ $numbers['urgent_open'] }}**
- Occupancy: **{{ $numbers['units_occupied'] ?? '—' }} / {{ $numbers['units_total'] ?? '—' }} units occupied**
- Leases expiring in 60 days: **{{ $numbers['expiring_leases_60d'] ?? 0 }}**
@endcomponent

@isset($reportsUrl)
@component('mail::button', ['url' => $reportsUrl])
View full reports
@endcomponent
@endisset

Thanks,<br>
{{ config('app.name') }}

@endcomponent
