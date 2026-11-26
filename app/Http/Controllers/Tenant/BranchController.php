<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreBranchRequest;
use App\Models\Branch;
use App\Support\TenancyContext;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Branch management. Branches are organization structure, so only owners
 * may add them — staff are assigned *to* branches, never create them.
 */
class BranchController extends Controller
{
    public function store(StoreBranchRequest $request): RedirectResponse
    {
        $organization = TenancyContext::organization()->refresh();
        $user = $request->user();

        abort_unless($user->isOwnerOf($organization) || $user->isPlatformAdmin(), 403);

        $organization->branches()->create([
            'name' => $request->string('name')->trim()->toString(),
            'slug' => Branch::uniqueSlug($organization->id, $request->string('name')->toString()),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Branch added. Assign properties to it from the property form.']);

        return back();
    }
}
