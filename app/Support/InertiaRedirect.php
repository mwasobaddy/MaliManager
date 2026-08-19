<?php

namespace App\Support;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Produces redirects that work across the central and tenant domains.
 *
 * Inertia performs POSTs via XHR. A plain 302 to another host makes the
 * browser follow it cross-origin, which trips CORS before Inertia can see
 * the response. Instead, return a 409 with the X-Inertia-Location header so
 * the Inertia client performs a full-page navigation (no XHR, no CORS).
 */
class InertiaRedirect
{
    public static function to(string $url, Request $request): RedirectResponse|Response
    {
        $destinationHost = parse_url($url, PHP_URL_HOST);
        $crossDomain = $destinationHost && $destinationHost !== $request->getHost();

        if ($crossDomain && $request->header('X-Inertia')) {
            return response()->noContent(409, ['X-Inertia-Location' => $url]);
        }

        return redirect()->to($url);
    }
}
