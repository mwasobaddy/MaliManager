<?php

namespace App\Support;

use App\Models\Lease;

/**
 * Merges {{placeholder}} tokens into an agreement's rich-text HTML using
 * live lease data. Unknown tokens are left untouched so authors can see
 * typos instead of silently losing content.
 */
class LeaseAgreementTemplate
{
    /**
     * The formatting tags preserved when saving editor HTML.
     */
    public const ALLOWED_TAGS = '<p><br><b><strong><i><em><u><s><ul><ol><li><h1><h2><h3><blockquote><code>';

    public static function sanitize(string $html): string
    {
        return strip_tags($html, self::ALLOWED_TAGS);
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
            ['token' => 'organization_name', 'description' => 'Landlord / company'],
            ['token' => 'start_date', 'description' => 'Lease start date'],
            ['token' => 'end_date', 'description' => 'Lease end date (empty while active)'],
            ['token' => 'rent_amount', 'description' => 'Rent amount'],
            ['token' => 'rent_frequency', 'description' => 'Rent frequency'],
            ['token' => 'deposit_amount', 'description' => 'Deposit amount'],
            ['token' => 'currency', 'description' => 'Currency code'],
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
            'organization_name' => $lease->property?->organization?->name,
            'start_date' => $lease->starts_at?->toDateString(),
            'end_date' => $lease->ends_at?->toDateString(),
            'rent_amount' => $lease->rent_amount !== null ? number_format((float) $lease->rent_amount, 2) : null,
            'rent_frequency' => $lease->rent_frequency,
            'deposit_amount' => $lease->deposit !== null ? number_format((float) $lease->deposit, 2) : null,
            'currency' => $lease->currency,
        ];

        foreach ($values as $token => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $html = preg_replace('/\{\{\s*'.preg_quote($token, '/').'\s*\}\}/', htmlspecialchars($value, ENT_QUOTES), $html);
        }

        return $html;
    }
}
