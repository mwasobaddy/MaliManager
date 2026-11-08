<?php

namespace App\Services;

use Digikraaft\Paystack\Paystack;
use Digikraaft\Paystack\Transaction;

/**
 * Instance wrapper around Paystack's static SDK so card payments stay
 * testable: tests bind a fake subclass instead of hitting api.paystack.co.
 * Amounts cross this boundary in the lowest currency unit (cents) and are
 * converted here only — app code carries decimal money everywhere else.
 */
class PaystackClient
{
    public function __construct()
    {
        Paystack::setApiKey((string) config('services.paystack.secret'));
    }

    /**
     * Start a hosted card checkout. Returns the SDK response object with
     * `status`, `data->authorization_url`, `data->access_code`,
     * `data->reference`.
     */
    public function initialize(array $params): object
    {
        return (object) Transaction::initialize($params);
    }

    /**
     * Verify a completed checkout by reference. Returns the SDK response
     * object with `status`, `data->status`, `data->amount` (lowest unit),
     * `data->currency`, `data->reference`.
     */
    public function verify(string $reference): object
    {
        return (object) Transaction::verify($reference);
    }

    /**
     * Lowest-unit conversion lives here and nowhere else: the app stores
     * decimal money, Paystack speaks cents.
     */
    public static function toLowestUnit(string $decimalAmount): int
    {
        return (int) bcmul($decimalAmount, '100');
    }
}
