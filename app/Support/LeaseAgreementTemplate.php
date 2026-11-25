<?php

namespace App\Support;

use App\Enums\UtilityChargeKind;
use App\Models\Lease;
use App\Models\UtilityCharge;

/**
 * Merges {{placeholder}} tokens into an agreement's rich-text HTML using
 * live lease data. Unknown tokens are left untouched so authors can see
 * typos instead of silently losing content.
 */
class LeaseAgreementTemplate
{
    /**
     * Sanitize editor HTML to a safe rich-text allowlist (see the `rich_text`
     * profile in config/purifier.php). Preserves Word-like formatting, tables,
     * images and links while stripping scripts, event handlers, iframes and
     * any other active content, so the stored HTML is safe to render.
     */
    public static function sanitize(string $html): string
    {
        return clean($html, 'rich_text');
    }

    /**
     * @return array<int, array{token: string, description: string}>
     */
    public static function availableTokens(): array
    {
        return [
            ['token' => 'occupant_name', 'description' => 'Tenant full name'],
            ['token' => 'occupant_email', 'description' => 'Tenant email'],
            ['token' => 'occupant_phone', 'description' => 'Tenant phone'],
            ['token' => 'property_name', 'description' => 'Property name'],
            ['token' => 'unit_name', 'description' => 'Unit name'],
            ['token' => 'parcel_name', 'description' => 'Land parcel name'],
            ['token' => 'section_name', 'description' => 'Parcel section name'],
            ['token' => 'parcel_acreage', 'description' => 'Parcel / section area'],
            ['token' => 'organization_name', 'description' => 'Landlord / company'],
            ['token' => 'start_date', 'description' => 'Lease start date'],
            ['token' => 'end_date', 'description' => 'Lease end date (empty while active)'],
            ['token' => 'rent_amount', 'description' => 'Rent amount'],
            ['token' => 'rent_frequency', 'description' => 'Rent frequency'],
            ['token' => 'deposit_amount', 'description' => 'Deposit amount'],
            ['token' => 'currency', 'description' => 'Currency code'],
            ['token' => 'utility_charges', 'description' => 'Utility charges for this unit (billing clause)'],
        ];
    }

    public static function render(string $html, Lease $lease): string
    {
        $lease->loadMissing('person', 'unit', 'property.organization');

        $values = [
            'occupant_name' => trim(($lease->person?->first_name ?? '').' '.($lease->person?->last_name ?? '')),
            'occupant_email' => $lease->person?->email,
            'occupant_phone' => $lease->person?->phone,
            'property_name' => $lease->property?->name,
            'unit_name' => $lease->unit?->name,
            'parcel_name' => $lease->landParcel?->name,
            'section_name' => $lease->landParcelSection?->name,
            'parcel_acreage' => $lease->landParcel?->acreage,
            'organization_name' => $lease->property?->organization?->name
                ?? $lease->landParcel?->organization?->name,
            'start_date' => $lease->starts_at?->toDateString(),
            'end_date' => $lease->ends_at?->toDateString(),
            'rent_amount' => $lease->rent_amount !== null ? number_format((float) $lease->rent_amount, 2) : null,
            'rent_frequency' => $lease->rent_frequency,
            'deposit_amount' => $lease->deposit !== null ? number_format((float) $lease->deposit, 2) : null,
            'currency' => $lease->currency,
            'utility_charges' => self::utilityChargesSummary($lease),
        ];

        foreach ($values as $token => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $html = preg_replace('/\{\{\s*'.preg_quote($token, '/').'\s*\}\}/', htmlspecialchars($value, ENT_QUOTES), $html);
        }

        return $html;
    }

    /**
     * Plain-text billing clause for the agreement: every active charge
     * assigned to the lease's unit, currency shown per charge because the
     * clause authorizes billing generally, not one currency's subset.
     * Always returns a value so the token never survives into print.
     */
    private static function utilityChargesSummary(Lease $lease): string
    {
        if ($lease->unit_id === null) {
            return 'None';
        }

        $charges = UtilityCharge::query()
            ->where('property_id', $lease->property_id)
            ->where('is_active', true)
            ->whereHas('units', fn ($query) => $query->where('units.id', $lease->unit_id))
            ->with(['units' => fn ($query) => $query->where('units.id', $lease->unit_id)])
            ->orderBy('name')
            ->get();

        if ($charges->isEmpty()) {
            return 'None';
        }

        return $charges->map(function (UtilityCharge $charge): string {
            $pivot = $charge->units->first()?->pivot;

            if ($charge->kind === UtilityChargeKind::Metered) {
                $unit = $charge->utility?->unitOfMeasure() ?? 'unit';

                return "{$charge->name} — metered at ".number_format((float) ($charge->rate ?? 0), 2)." {$charge->currency} per {$unit}";
            }

            $amount = number_format((float) ($pivot->amount ?? 0), 2);
            $suffix = $charge->kind === UtilityChargeKind::OneTime ? 'one-time' : 'per period';

            if ($charge->kind === UtilityChargeKind::Shared) {
                $suffix = "shared master bill ({$charge->allocation} split)";
            }

            return "{$charge->name} — {$amount} {$charge->currency} {$suffix}";
        })->implode('; ');
    }
}
