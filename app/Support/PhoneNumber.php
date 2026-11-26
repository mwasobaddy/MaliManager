<?php

namespace App\Support;

/**
 * Kenyan-first phone handling for login and SMS delivery.
 *
 * Numbers arrive in the wild as `0712…`, `254712…`, `+254712…` or spaced
 * and dashed variants of each. Everything is canonicalised to E.164
 * (`+254…`) before storage comparison or gateway submission, so a tenant
 * who typed their number one way can sign in having typed it another.
 */
class PhoneNumber
{
    /**
     * Canonicalise to E.164, or null when the input is not a phone number.
     */
    public static function normalize(?string $input): ?string
    {
        if ($input === null) {
            return null;
        }

        $cleaned = preg_replace('/[\s\-().]/', '', trim($input));

        if ($cleaned === null || $cleaned === '') {
            return null;
        }

        // Local Kenyan mobile: 07… / 01… (10 digits).
        if (preg_match('/^0(\d{9})$/', $cleaned, $matches) === 1) {
            return '+254'.$matches[1];
        }

        // Kenyan without the plus.
        if (preg_match('/^254(\d{9})$/', $cleaned, $matches) === 1) {
            return '+254'.$matches[1];
        }

        // Already international.
        if (preg_match('/^\+\d{7,15}$/', $cleaned) === 1) {
            return $cleaned;
        }

        // Bare international digits: assume the country code is included.
        if (preg_match('/^\d{9,15}$/', $cleaned) === 1) {
            return '+'.$cleaned;
        }

        return null;
    }

    public static function isPhone(?string $input): bool
    {
        return self::normalize($input) !== null;
    }

    /**
     * The trailing digits used for suffix matching against numbers stored
     * in whatever format they were typed in.
     */
    public static function suffix(?string $canonical): ?string
    {
        if ($canonical === null) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $canonical);

        if ($digits === null || strlen($digits) < 9) {
            return null;
        }

        return substr($digits, -9);
    }

    /**
     * Shows just enough to confirm where the code went on a shared screen.
     */
    public static function mask(string $canonical): string
    {
        $digits = preg_replace('/\D/', '', $canonical) ?? '';

        if (strlen($digits) < 4) {
            return $canonical;
        }

        return substr($digits, 0, 4).'••••'.substr($digits, -2);
    }
}
