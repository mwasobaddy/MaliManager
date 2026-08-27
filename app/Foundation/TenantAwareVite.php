<?php

namespace App\Foundation;

use Illuminate\Foundation\Vite;

/**
 * Vite variant that emits dev (hot) asset URLs on the *current request
 * host* instead of the static host written into the `public/hot` file.
 *
 * The default Laravel Vite pins every dev module to the central host
 * (e.g. `malimanager.test:5173`). On a tenant subdomain
 * (`kelvin-properties.malimanager.test`) the browser then fetches those
 * modules cross-origin, which fails (CORS / host mismatch) and surfaces
 * as a brief white error screen when switching assets. Serving the
 * modules same-origin (current host + dev port) removes that failure.
 */
class TenantAwareVite extends Vite
{
    protected function hotAsset($asset): string
    {
        $hot = rtrim((string) file_get_contents($this->hotFile()));

        if (function_exists('request') && request() !== null) {
            $parsed = parse_url($hot);
            $scheme = $parsed['scheme'] ?? 'http';
            $port = $parsed['port'] ?? 5173;
            $host = request()->getHost();

            return "{$scheme}://{$host}:{$port}/".ltrim($asset, '/');
        }

        return $hot.'/'.$asset;
    }
}
