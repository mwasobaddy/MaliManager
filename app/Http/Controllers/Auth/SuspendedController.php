<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Config;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The account-suspended landing page. Suspended users arrive logged out,
 * so the page carries its own contact line and reactivation form instead
 * of relying on authenticated settings or an inbox they may not read.
 */
class SuspendedController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('auth/suspended', [
            'supportEmail' => Config::string('mail.from.address'),
        ]);
    }
}
