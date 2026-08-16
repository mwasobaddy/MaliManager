<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\OtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function store(Request $request, OtpService $otpService): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user) {
            $user = User::create([
                'name' => ucfirst(Str::before($validated['email'], '@')),
                'email' => $validated['email'],
                'status' => 'active',
            ]);
        }

        if ($user->status !== 'active') {
            throw ValidationException::withMessages([
                'email' => 'This account has been suspended.',
            ]);
        }

        $otpService->issue($user);

        $request->session()->put('login.email', $validated['email']);

        return redirect()->route('login.otp');
    }
}
