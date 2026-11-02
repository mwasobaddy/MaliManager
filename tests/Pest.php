<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
 * Action tests are "unit" in the sense that matters — no HTTP, no route, no
 * session — but an Action that writes through Eloquent still needs a booted
 * application and a database. Feature gets `RefreshDatabase` unconditionally;
 * here it stays opt-in per file so a genuinely pure test pays nothing.
 */
pest()->extend(TestCase::class)->in('Unit/Actions');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Assert that an error toast was flashed for the previous (non-Inertia-JSON)
 * request. Permission denials and caught exceptions now flash an error toast
 * and redirect instead of rendering an error page.
 */
function assertErrorToast(): void
{
    $flash = session()->get('inertia.flash_data', []);

    expect($flash['toast']['type'] ?? null)->toBe('error');
}

/**
 * Assert that a success toast was flashed for the previous request.
 */
function assertSuccessToast(): void
{
    $flash = session()->get('inertia.flash_data', []);

    expect($flash['toast']['type'] ?? null)->toBe('success');
}
