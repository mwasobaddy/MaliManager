<?php

namespace App\Concerns;

use App\Support\ImportResult;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * The shared outcome handling for a bulk import.
 *
 * Every import endpoint answers the same way: a success toast and a redirect to
 * the index when nothing failed, otherwise a warning or error toast plus the
 * per-row report, leaving the import sheet open so it can render it.
 */
trait FlashesImportResult
{
    protected function respondToImport(
        ImportResult $result,
        string $singular,
        string $plural,
        string $indexRoute,
    ): RedirectResponse {
        if (! $result->hasFailures()) {
            Inertia::flash('toast', [
                'type' => 'success',
                'message' => $result->imported === 1
                    ? "1 {$singular} imported."
                    : "{$result->imported} {$plural} imported.",
            ]);

            return redirect()->route($indexRoute);
        }

        Inertia::flash('toast', [
            'type' => $result->imported > 0 ? 'warning' : 'error',
            'message' => "{$result->imported} imported, {$result->failed} failed.",
        ]);

        Inertia::flash('importErrors', $result->errors);

        return back();
    }
}
