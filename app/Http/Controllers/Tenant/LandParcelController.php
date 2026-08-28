<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\SubPermissionKey;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\DestroyLandParcelRequest;
use App\Http\Requests\Tenant\StoreLandParcelRequest;
use App\Http\Requests\Tenant\UpdateLandParcelRequest;
use App\Models\LandParcel;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\SubRole;
use App\Services\LandParcelService;
use App\Services\StaffService;
use App\Support\TenancyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LandParcelController extends Controller
{
    public function index(Request $request): Response
    {
        $organization = TenancyContext::organization();
        $user = $request->user();

        $parcels = $organization->landParcels()
            ->when(! $user->isOwnerOf($organization), fn ($query) => $query
                ->whereIn('id', app(StaffService::class)->delegatedLandParcelIds($user->membershipFor($organization))))
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn (LandParcel $parcel) => $this->present($parcel));

        return Inertia::render('tenant/land-parcels/index', [
            'organization' => $organization->only('id', 'name', 'slug'),
            'land_parcels' => $parcels,
            'canCreateLandParcel' => $user->isOwnerOf($organization)
                || $user->hasSubPermission($organization, SubPermissionKey::LandParcelCreate),
        ]);
    }

    public function create(Request $request): Response
    {
        $organization = TenancyContext::organization();
        $parcel = new LandParcel;

        return Inertia::render('tenant/land-parcels/create', [
            'organization' => $organization->only('id', 'name', 'slug'),
            'managers' => $this->managers($organization),
            'parcel' => null,
            'selected_manager_ids' => [],
        ]);
    }

    public function store(StoreLandParcelRequest $request, LandParcelService $service): RedirectResponse
    {
        $organization = TenancyContext::organization();

        $parcel = $service->create($organization, $request->user(), $request->validated());
        $this->attachImages($parcel, $request);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Land parcel created.']);

        return redirect()->route('tenant.land-parcels.show', [
            'land_parcel' => $parcel->slug,
        ]);
    }

    public function show(Request $request, LandParcel $landParcel): Response
    {
        $this->authorizeAccess($request, $landParcel);

        $organization = TenancyContext::organization();
        $user = $request->user();

        return Inertia::render('tenant/land-parcels/show', [
            'organization' => $organization->only('id', 'name', 'slug'),
            'parcel' => $this->present($landParcel, true),
            'managers' => $this->managerNames($landParcel),
            'sections' => $landParcel->sections()
                ->orderBy('name')
                ->get(['id', 'name', 'area', 'status', 'notes'])
                ->all(),
            'canEditLandParcel' => $user->hasSubPermission($organization, SubPermissionKey::LandParcelEdit),
            'canDeleteLandParcel' => $user->hasSubPermission($organization, SubPermissionKey::LandParcelDelete),
            'canManageSections' => $user->hasSubPermission($organization, SubPermissionKey::LandParcelManage),
            'canLease' => $user->hasSubPermission($organization, SubPermissionKey::LeaseManage),
        ]);
    }

    public function edit(Request $request, LandParcel $landParcel): Response
    {
        $this->authorizeAccess($request, $landParcel);

        $organization = TenancyContext::organization();

        return Inertia::render('tenant/land-parcels/edit', [
            'organization' => $organization->only('id', 'name', 'slug'),
            'parcel' => $this->present($landParcel, true),
            'managers' => $this->managers($organization),
            'canDeleteLandParcel' => $user->hasSubPermission($organization, SubPermissionKey::LandParcelDelete),
        ]);
    }

    public function update(UpdateLandParcelRequest $request, LandParcel $landParcel, LandParcelService $service): RedirectResponse
    {
        $this->authorizeAccess($request, $landParcel);

        $service->update($landParcel, $request->user(), $request->validated());
        $this->syncImages($landParcel, $request);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Land parcel updated.']);

        return redirect()->route('tenant.land-parcels.show', [
            'land_parcel' => $landParcel->slug,
        ]);
    }

    public function destroy(DestroyLandParcelRequest $request, LandParcel $landParcel, LandParcelService $service): RedirectResponse
    {
        $this->authorizeAccess($request, $landParcel);

        $service->softDelete($landParcel);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Land parcel removed.']);

        return redirect()->route('tenant.land-parcels.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function present(LandParcel $parcel, bool $withMedia = false): array
    {
        $data = [
            'id' => $parcel->id,
            'name' => $parcel->name,
            'slug' => $parcel->slug,
            'title_deed_number' => $parcel->title_deed_number,
            'acreage' => $parcel->acreage,
            'zoning' => $parcel->zoning,
            'address' => $parcel->address,
            'city' => $parcel->city,
            'status' => $parcel->status,
            'latitude' => $parcel->latitude,
            'longitude' => $parcel->longitude,
            'available_for_lease' => $parcel->available_for_lease,
            'notes' => $parcel->notes,
            'manager_ids' => app(LandParcelService::class)->managerUserIds($parcel),
        ];

        if ($withMedia) {
            $data['images'] = $parcel->getMedia('photos')->map(fn ($media) => [
                'id' => $media->id,
                'url' => $media->getUrl(),
                'name' => $media->name ?? $media->file_name,
            ])->all();
        }

        return $data;
    }

    /**
     * The org's active members (as OrganizationUser rows) for delegation.
     *
     * @return array<int, array{id: int, name: string, sub_role: string|null}>
     */
    private function managers(Organization $organization): array
    {
        return $organization->users()
            ->wherePivot('status', 'active')
            ->withPivot('id', 'sub_role_id')
            ->get()
            ->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'sub_role' => $user->pivot->sub_role_id
                    ? optional(SubRole::find($user->pivot->sub_role_id))->name
                    : null,
            ])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function managerNames(LandParcel $parcel): array
    {
        $ids = app(LandParcelService::class)->managerIds($parcel);

        if (empty($ids)) {
            return [];
        }

        return OrganizationUser::whereIn('id', $ids)
            ->with('user')
            ->get()
            ->map(fn ($membership) => $membership->user?->name)
            ->filter()
            ->all();
    }

    private function attachImages(LandParcel $parcel, Request $request): void
    {
        if (! $request->hasFile('images')) {
            return;
        }

        foreach ($request->file('images') as $image) {
            $parcel->addMedia($image)->toMediaCollection('photos');
        }
    }

    private function syncImages(LandParcel $parcel, UpdateLandParcelRequest $request): void
    {
        foreach ($request->input('remove_media_ids', []) as $mediaId) {
            $media = $parcel->media()->find($mediaId);
            $media?->delete();
        }

        $this->attachImages($parcel, $request);
    }

    /**
     * Owners bypass; other members must be delegated to the parcel.
     */
    private function authorizeAccess(Request $request, LandParcel $parcel): void
    {
        $user = $request->user();
        $organization = $parcel->organization;

        if ($user->isOwnerOf($organization)) {
            return;
        }

        $membership = $user->membershipFor($organization);

        if ($membership && in_array($parcel->id, app(StaffService::class)->delegatedLandParcelIds($membership), true)) {
            return;
        }

        abort(403);
    }
}
