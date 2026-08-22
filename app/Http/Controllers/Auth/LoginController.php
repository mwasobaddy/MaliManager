<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Support\OtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Throwable;

class LoginController extends Controller
{
    public function store(LoginRequest $request, OtpService $otpService): RedirectResponse
    {
        try {
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
                throw ValidationException::withMessages([
                    'email' => 'This account has been suspended.',
                ]);
            }

            $otpService->issue($user);

            $request->session()->put('login.email', $validated['email']);

            return redirect()->route('login.otp');
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);

            Inertia::flash('toast', ['type' => 'error', 'message' => 'We could not sign you in. Please try again.']);

            return redirect()->route('login');
        }
    }
}
