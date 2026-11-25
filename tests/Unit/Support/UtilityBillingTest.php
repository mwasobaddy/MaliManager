<?php

use App\Support\UtilityBilling;
use Carbon\CarbonImmutable;

function jan(int $day): CarbonImmutable
{
    return CarbonImmutable::create(2026, 1, $day)->startOfDay();
}

test('a full-period stay bills the face amount', function () {
    $result = UtilityBilling::prorate('500.00', 'monthly', jan(1), jan(31), jan(1), jan(31));

    expect($result['amount'])->toBe('500.00')
        ->and($result['full'])->toBeTrue()
        ->and($result['label'])->toBe('per period')
        ->and($result['days_in_period'])->toBe(31);
});

test('a mid-month move-in pro-rates by days occupied', function () {
    // 500 × 21/31 = 338.7096… → 338.71.
    $result = UtilityBilling::prorate('500.00', 'monthly', jan(1), jan(31), jan(11), jan(31));

    expect($result['amount'])->toBe('338.71')
        ->and($result['full'])->toBeFalse()
        ->and($result['label'])->toBe('prorated 21 of 31 days')
        ->and($result['days_occupied'])->toBe(21);
});

test('occupancy outside the period bills nothing', function () {
    $result = UtilityBilling::prorate(
        '500.00',
        'monthly',
        jan(1),
        jan(31),
        CarbonImmutable::create(2026, 2, 1)->startOfDay(),
        CarbonImmutable::create(2026, 2, 28)->startOfDay(),
    );

    expect($result['amount'])->toBe('0.00')
        ->and($result['full'])->toBeFalse()
        ->and($result['label'])->toBe('prorated 0 of 31 days');
});

test('period lengths follow the frequency', function () {
    expect(UtilityBilling::periodDays('daily', jan(15)))->toBe(1)
        ->and(UtilityBilling::periodDays('weekly', jan(15)))->toBe(7)
        ->and(UtilityBilling::periodDays('monthly', jan(15)))->toBe(31)
        ->and(UtilityBilling::periodDays('monthly', CarbonImmutable::create(2026, 2, 10)))->toBe(28)
        ->and(UtilityBilling::periodDays('yearly', CarbonImmutable::create(2024, 6, 1)))->toBe(366)
        ->and(UtilityBilling::periodDays('yearly', jan(15)))->toBe(365);
});

test('a partial week pro-rates over seven days', function () {
    // 700 × 3/7 = 300.00.
    $result = UtilityBilling::prorate('700.00', 'weekly', jan(5), jan(11), jan(9), jan(11));

    expect($result['amount'])->toBe('300.00')
        ->and($result['label'])->toBe('prorated 3 of 7 days');
});

test('a same-day stay still bills a day', function () {
    $result = UtilityBilling::prorate('90.00', 'daily', jan(5), jan(5), jan(5), jan(5));

    expect($result['amount'])->toBe('90.00')
        ->and($result['full'])->toBeTrue();
});

test('shared splits tie to the master total to the cent', function () {
    $shares = UtilityBilling::sharedShares('100.00', ['a' => 1, 'b' => 1, 'c' => 1]);
    $amounts = array_values($shares);
    sort($amounts);

    expect($amounts)->toBe(['33.33', '33.33', '33.34']);

    $total = '0.00';
    foreach ($shares as $amount) {
        $total = bcadd($total, $amount, 2);
    }
    expect($total)->toBe('100.00');
});

test('shared splits with no weight or no master bill nothing', function () {
    expect(UtilityBilling::sharedShares('100.00', ['a' => 0, 'b' => 0]))->toBe(['a' => '0.00', 'b' => '0.00'])
        ->and(UtilityBilling::sharedShares('0.00', ['a' => 1]))->toBe(['a' => '0.00']);
});
