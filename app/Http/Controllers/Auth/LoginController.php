<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Support\OtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function store(LoginRequest $request, OtpService $otpService): RedirectResponse
    {
        $validated = $request->validated();

        $user = User::where('email', $validated['email'])->first();

        if (! $user) {
            $user = User::create([
                'name' => ucfirst(Str::before($validated['email'], '@')),
                'email' => $validated['email'],
                'status' => 'active',
            ]);
        }

        if ($user->status !== 'active') {
            activity()->inLog('auth')
                ->causedBy($user)
                ->performedOn($user)
                ->event('login.suspended')
                ->log('Blocked sign-in for a suspended account');

            throw ValidationException::withMessages([
                'email' => 'This account has been suspended.',
            ]);
        }

        $otpService->issue($user);

        activity()->inLog('auth')
            ->causedBy($user)
            ->performedOn($user)
            ->event('otp.issued')
            ->log('Issued one-time code');

        $request->session()->put([
            'login.email' => $validated['email'],
            'login.remember' => (bool) ($validated['remember'] ?? false),
        ]);

        return redirect()->route('login.otp');
    }
}
