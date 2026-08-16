<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Stancl\Tenancy\Features\UserImpersonation;

class ImpersonationController extends Controller
{
    public function login(string $token): RedirectResponse
    {
        return UserImpersonation::makeResponse($token);
    }
}
