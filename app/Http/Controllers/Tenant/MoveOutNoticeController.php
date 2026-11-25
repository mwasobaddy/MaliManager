<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Actions\MoveOut\DecideMoveOutNotice;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\AcknowledgeMoveOutNoticeRequest;
use App\Models\MoveOutNotice;
use App\Support\TenancyContext;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Landlord-side acknowledgement of tenant move-out notices. Requesting and
 * withdrawing live on the central domain (the tenant's own pages); this
 * endpoint only flips pending to acknowledged.
 */
class MoveOutNoticeController extends Controller
{
    public function acknowledge(AcknowledgeMoveOutNoticeRequest $request, MoveOutNotice $notice, DecideMoveOutNotice $decide): RedirectResponse
    {
        $organization = TenancyContext::organization();

        abort_unless($organization !== null && $notice->organization_id === $organization->id, 404);

        $decide->acknowledge($request->user(), $notice);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Move-out notice acknowledged.']);

        return back();
    }
}
