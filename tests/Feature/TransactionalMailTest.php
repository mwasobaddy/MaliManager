<?php

use App\Mail\BillingReminderMail;
use App\Mail\LoginOtpMail;
use App\Mail\RentReminderMail;

test('login otp mail renders brand chrome with a text alternative', function () {
    $mail = new LoginOtpMail('123456');
    $html = $mail->render();

    expect($html)
        ->toContain('#2E2622')
        ->toContain('#F5F2EB')
        ->toContain('ui-monospace')
        ->toContain('123456')
        ->not->toContain('#a1a1aa')
        ->and($mail->content()->text)->toBe('email.login-otp-text')
        ->and(view('email.login-otp-text', ['code' => '123456'])->render())
        ->toContain('123456');
});

test('rent reminder mail renders brand chrome with a text alternative', function () {
    $mail = new RentReminderMail('Sunset Heights', 'Your rent of KES 25,000 is due.');
    $html = $mail->render();

    expect($html)
        ->toContain('#2E2622')
        ->toContain('Your rent of KES 25,000 is due.')
        ->not->toContain('#a1a1aa')
        ->and($mail->content()->text)->toBe('email.rent-reminder-text');
});

test('billing reminder mail renders brand chrome with a text alternative', function () {
    $mail = new BillingReminderMail('Acme Estates', 'Growth', 'Your subscription renews soon.');
    $html = $mail->render();

    expect($html)
        ->toContain('#2E2622')
        ->toContain('Growth')
        ->not->toContain('#a1a1aa')
        ->and($mail->content()->text)->toBe('email.billing-reminder-text');
});
