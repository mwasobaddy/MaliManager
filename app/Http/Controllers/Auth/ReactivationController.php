<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ReactivationRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Suspended-account reactivation requests. The requester is logged out,
 * so this only records the appeal in the auth audit log and tells them
 * what happens next — the actual unsuspend stays a platform-admin action.
 */
class ReactivationController extends Controller
{
    public function store(ReactivationRequest $request): RedirectResponse
    {
        $user = User::where('email', $request->validated()['email'])->first();

        activity()->inLog('auth')
            ->causedBy($user)
            ->event('reactivation.requested')
            ->withProperties(['email' => $request->validated()['email'], 'known_account' => $user !== null])
            ->log('Requested account reactivation');

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Request received. An administrator will review it and contact you by email.',
        ]);

        return redirect()->route('suspended');
    }
}
