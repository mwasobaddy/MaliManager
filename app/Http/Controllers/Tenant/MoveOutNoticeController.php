<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Actions\MoveOut\AcknowledgeMoveOutNotice;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\AcknowledgeMoveOutNoticeRequest;
use App\Models\MoveOutNotice;
use App\Support\TenancyContext;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Landlord-side acknowledgement of tenant move-out notices. Requesting and
 * withdrawing live on the central domain (the tenant's own pages); this
 * endpoint acknowledges, optionally confirms the move-out day, and starts
 * the deposit refund where one is due.
 */
class MoveOutNoticeController extends Controller
{
    public function acknowledge(AcknowledgeMoveOutNoticeRequest $request, MoveOutNotice $notice, AcknowledgeMoveOutNotice $acknowledge): RedirectResponse
    {
        $organization = TenancyContext::organization();

        abort_unless($organization !== null && $notice->organization_id === $organization->id, 404);

        $acknowledge->handle($request->user(), $organization->refresh(), $notice, $request->input('move_out_date'));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Move-out notice acknowledged.']);

        return back();
    }
}
