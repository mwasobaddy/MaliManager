<?php

declare(strict_types=1);

namespace App\Http\Controllers\Searcher;

use App\Actions\MoveOut\DecideMoveOutNotice;
use App\Actions\MoveOut\SubmitMoveOutNotice;
use App\Data\MoveOut\SubmitMoveOutNoticeData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Searcher\StoreMoveOutNoticeRequest;
use App\Models\Lease;
use App\Models\MoveOutNotice;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * A tenant's own 30-day move-out notices: file one against an active
 * lease, or withdraw a pending one. Landlords act from the lease sheet;
 * this controller never touches another tenant's leases.
 */
class MoveOutNoticeController extends Controller
{
    public function store(StoreMoveOutNoticeRequest $request, SubmitMoveOutNotice $submit): RedirectResponse
    {
        $lease = Lease::query()->findOrFail((int) $request->integer('lease_id'));

        abort_unless($lease->person_id === $request->user()->person_id, 404);

        $submit->handle($request->user(), $lease, new SubmitMoveOutNoticeData(
            organizationId: (int) $lease->organization_id,
            moveOutDate: (string) $request->string('move_out_date'),
            note: $request->filled('note') ? trim((string) $request->input('note')) : null,
        ));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Move-out notice submitted.']);

        return back();
    }

    public function destroy(MoveOutNotice $notice, DecideMoveOutNotice $decide): RedirectResponse
    {
        $decide->withdraw(request()->user(), $notice);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Move-out notice withdrawn.']);

        return back();
    }
}
