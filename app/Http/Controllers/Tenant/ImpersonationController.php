<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Stancl\Tenancy\Features\UserImpersonation;
use Throwable;

class ImpersonationController extends Controller
{
    public function login(string $token): RedirectResponse
    {
        try {
            return UserImpersonation::makeResponse($token);
        } catch (Throwable $e) {
            report($e);

            Inertia::flash('toast', ['type' => 'error', 'message' => 'This impersonation link is invalid or has expired.']);

            return redirect()->back();
        }
    }
}
