<?php

namespace App\Http\Controllers\Tenant;

use App\Actions\Billing\HandleMpesaCallback;
use App\Http\Controllers\Controller;
use Botnetdobbs\Mpesa\Contracts\CallbackResponder;
use Illuminate\Http\Request;

/**
 * Safaricom's async STK callback. No auth session exists here, so
 * this route lives outside the auth group and is CSRF-exempt. The
 * expected envelope is always answered — failures are recorded on the
 * payment attempt, never bounced back for retry.
 */
class MpesaCallbackController extends Controller
{
    public function store(Request $request, CallbackResponder $responder)
    {
        app(HandleMpesaCallback::class)->handle($request);

        return $responder->success();
    }
}
