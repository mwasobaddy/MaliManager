import { usePage } from '@inertiajs/react';
import {
    ArrowRight,
    Building2,
    Layers,
    LayoutDashboard,
    MapPin,
    Map,
    ShieldCheck,
} from 'lucide-react';
import { createContext, useContext, useEffect, useState } from 'react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { OrganizationSummary } from '@/types/auth';

type PropertyPickerContextValue = {
    open: () => void;
    close: () => void;
    organizations: OrganizationSummary[];
    hasProperties: boolean;
    hasLand: boolean;
    hasAssets: boolean;
};

const PropertyPickerContext = createContext<PropertyPickerContextValue | null>(
    null,
);

export function usePropertyPicker(): PropertyPickerContextValue {
    const ctx = useContext(PropertyPickerContext);

    if (!ctx) {
        throw new Error(
            'usePropertyPicker must be used within a PropertyPickerProvider',
        );
    }

    return ctx;
}

function tenantUrl(
    organizationDomain: string | null,
    propertySlug: string,
): string {
    if (!organizationDomain) {
        return '/dashboard';
    }

    const scheme = window.location.protocol;

    return `${scheme}//${organizationDomain}/${propertySlug}/dashboard`;
}

function landUrl(organizationDomain: string | null, landSlug: string): string {
    if (!organizationDomain) {
        return `/land-parcels/${landSlug}`;
    }

    const scheme = window.location.protocol;

    return `${scheme}//${organizationDomain}/land-parcels/${landSlug}`;
}

// The organization-level overview lives on the tenant subdomain, so an owner
// leaving the central dashboard crosses back into the tenancy context.
function orgOverviewUrl(organizationDomain: string | null): string {
    if (!organizationDomain) {
        return '/overview';
    }

    const scheme = window.location.protocol;

    return `${scheme}//${organizationDomain}/overview`;
}

// The central (platform) dashboard lives off the tenant subdomain. Admins who
// choose "Continue as admin" leave the current tenant context and land on the
// central dashboard, identified purely by host (the path is always /dashboard
// on central; the tenant property/land paths are not routable centrally).
function centralDashboardUrl(centralUrlBase: string | undefined): string {
    const base = centralUrlBase ?? window.location.origin;
    const url = new URL(base);
    url.protocol = window.location.protocol;
    url.pathname = '/dashboard';

    return url.toString();
}

export function PropertyPickerProvider({
    children,
}: {
    children: React.ReactNode;
}) {
    const page = usePage();
    const organizations = (page.props.auth?.organizations ??
        []) as OrganizationSummary[];
    const permissions = (page.props.auth?.permissions ?? []) as string[];
    const autoOpen = page.props.autoOpenPropertyPicker === true;

    const [isOpen, setIsOpen] = useState(false);

    useEffect(() => {
        if (autoOpen) {
            // eslint-disable-next-line react-hooks/set-state-in-effect
            setIsOpen(true);
        }
    }, [autoOpen]);

    const open = () => setIsOpen(true);
    const close = () => setIsOpen(false);

    const hasProperties = organizations.some(
        (organization) => organization.properties.length > 0,
    );
    const hasLand = organizations.some(
        (organization) => organization.land_parcels.length > 0,
    );
    const hasAssets = hasProperties || hasLand;

    // Only users holding the central "access admin dashboard" permission may
    // leave the picker via the platform admin dashboard.
    const canContinueAsAdmin = permissions.includes('access admin dashboard');

    // Owners always see the organization overview card for any organization
    // that has properties; staff only see it once they manage more than one
    // property (a single delegated property means the property dashboard is
    // their destination, not an overview).
    const canAccessOrgDashboard = (organization: OrganizationSummary): boolean =>
        organization.properties.length > 0 &&
        (organization.is_owner || organization.properties.length > 1);

    const overallOrganizations = organizations.filter(canAccessOrgDashboard);
    const canContinueToOrgDashboard = overallOrganizations.length > 0;

    // Unless they have the admin card or an overview card, users must pick an
    // asset: any attempt to escape drops them into a random accessible asset.
    const mustChoose =
        hasAssets && !canContinueAsAdmin && !canContinueToOrgDashboard;

    // Persist that the picker was acknowledged so it does not re-open on the
    // next dashboard load/refresh within this login. The local close happens
    // regardless of the request outcome so the UI never gets stuck.
    //
    // This is a plain fetch (not an Inertia visit): the endpoint returns
    // 204 No Content, which Inertia cannot parse as a visit response and
    // would surface its error dialog. A background fetch avoids that.
    const acknowledge = (then?: () => void) => {
        const token = document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content');

        fetch('/property-picker/acknowledge', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': token ?? '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({}),
            credentials: 'same-origin',
        })
            .catch(() => {})
            .finally(() => {
                setIsOpen(false);
                then?.();
            });
    };

    const selectProperty = (
        organizationDomain: string | null,
        propertySlug: string,
    ) => {
        const navigate = () =>
            window.location.assign(tenantUrl(organizationDomain, propertySlug));
        acknowledge(navigate);
    };

    const selectLandParcel = (
        organizationDomain: string | null,
        landSlug: string,
    ) => {
        const navigate = () =>
            window.location.assign(landUrl(organizationDomain, landSlug));
        acknowledge(navigate);
    };

    const continueToOrgDashboard = (organizationDomain: string | null) => {
        const navigate = () =>
            window.location.assign(orgOverviewUrl(organizationDomain));
        acknowledge(navigate);
    };

    const selectRandomAsset = () => {
        const choices = [
            ...organizations.flatMap((organization) =>
                organization.properties.map((property) => ({
                    type: 'property' as const,
                    domain: organization.domain,
                    slug: property.slug,
                })),
            ),
            ...organizations.flatMap((organization) =>
                organization.land_parcels.map((parcel) => ({
                    type: 'land' as const,
                    domain: organization.domain,
                    slug: parcel.slug,
                })),
            ),
        ];

        if (choices.length === 0) {
            return;
        }

        const choice = choices[Math.floor(Math.random() * choices.length)]!;

        if (choice.type === 'land') {
            selectLandParcel(choice.domain, choice.slug);
        } else {
            selectProperty(choice.domain, choice.slug);
        }
    };

    return (
        <PropertyPickerContext.Provider
            value={{
                open,
                close,
                organizations,
                hasProperties,
                hasLand,
                hasAssets,
            }}
        >
            {children}
            <Dialog
                open={isOpen}
                onOpenChange={(next) => {
                    if (!mustChoose || next) {
                        setIsOpen(next);
                    }
                }}
            >
                <DialogContent
                    className="sm:max-w-2xl"
                    showCloseButton={!mustChoose}
                    onEscapeKeyDown={(event) => {
                        if (mustChoose) {
                            event.preventDefault();
                            selectRandomAsset();
                        }
                    }}
                    onInteractOutside={(event) => {
                        if (mustChoose) {
                            event.preventDefault();
                        }
                    }}
                >
                    <DialogHeader>
                        <DialogTitle>Where do you want to go?</DialogTitle>
                        <DialogDescription>
                            {canContinueAsAdmin
                                ? 'Continue to a dashboard, or pick a specific asset below.'
                                : canContinueToOrgDashboard
                                  ? 'Continue to an organization overview, or pick a specific asset below.'
                                  : 'Choose the organization, property, or land parcel you want to manage.'}
                        </DialogDescription>
                    </DialogHeader>

                    {!hasAssets ? (
                        <p className="text-sm text-muted-foreground">
                            You don't have any properties or land parcels
                            assigned to you yet.
                        </p>
                    ) : (
                        <div className="max-h-[60vh] space-y-6 overflow-y-auto">
                            {organizations.map((organization) => {
                                const orgHasAssets =
                                    organization.properties.length > 0 ||
                                    organization.land_parcels.length > 0;

                                if (!orgHasAssets) {
                                    return (
                                        <div key={organization.id}>
                                            <div className="mb-2 flex items-center gap-2">
                                                <h3 className="text-sm font-semibold">
                                                    {organization.name}
                                                </h3>
                                                <span className="rounded bg-muted px-1.5 py-0.5 text-xs text-muted-foreground">
                                                    {organization.is_owner
                                                        ? 'Owner'
                                                        : 'Staff'}
                                                </span>
                                            </div>
                                            <p className="text-xs text-muted-foreground">
                                                No properties or land parcels
                                                assigned.
                                            </p>
                                        </div>
                                    );
                                }

                                return (
                                    <div key={organization.id}>
                                        <div className="mb-2 flex items-center gap-2">
                                            <h3 className="text-sm font-semibold">
                                                {organization.name}
                                            </h3>
                                            <span className="rounded bg-muted px-1.5 py-0.5 text-xs text-muted-foreground">
                                                {organization.is_owner
                                                    ? 'Owner'
                                                    : 'Staff'}
                                            </span>
                                        </div>

                                        <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                            {organization.properties.map(
                                                (property) => (
                                                    <button
                                                        key={property.id}
                                                        type="button"
                                                        onClick={() =>
                                                            selectProperty(
                                                                organization.domain,
                                                                property.slug,
                                                            )
                                                        }
                                                        className="flex flex-col items-start gap-1 rounded-lg border p-3 text-left transition-colors hover:border-primary hover:bg-accent"
                                                    >
                                                        <span className="flex items-center gap-2 font-medium">
                                                            <Building2 className="size-4" />
                                                            {property.name}
                                                        </span>
                                                        <span className="flex flex-wrap items-center gap-3 text-xs text-muted-foreground">
                                                            {property.city && (
                                                                <span className="flex items-center gap-1">
                                                                    <MapPin className="size-3" />
                                                                    {
                                                                        property.city
                                                                    }
                                                                </span>
                                                            )}
                                                            <span className="flex items-center gap-1">
                                                                <Layers className="size-3" />
                                                                {
                                                                    property.units_count
                                                                }{' '}
                                                                units
                                                            </span>
                                                            <span className="capitalize">
                                                                {
                                                                    property.status
                                                                }
                                                            </span>
                                                        </span>
                                                    </button>
                                                ),
                                            )}

                                            {organization.land_parcels.map(
                                                (parcel) => (
                                                    <button
                                                        key={parcel.id}
                                                        type="button"
                                                        onClick={() =>
                                                            selectLandParcel(
                                                                organization.domain,
                                                                parcel.slug,
                                                            )
                                                        }
                                                        className="flex flex-col items-start gap-1 rounded-lg border p-3 text-left transition-colors hover:border-primary hover:bg-accent"
                                                    >
                                                        <span className="flex items-center gap-2 font-medium">
                                                            <Map className="size-4" />
                                                            {parcel.name}
                                                        </span>
                                                        <span className="flex flex-wrap items-center gap-3 text-xs text-muted-foreground">
                                                            {parcel.city && (
                                                                <span className="flex items-center gap-1">
                                                                    <MapPin className="size-3" />
                                                                    {
                                                                        parcel.city
                                                                    }
                                                                </span>
                                                            )}
                                                            {parcel.acreage !=
                                                                null && (
                                                                <span>
                                                                    {
                                                                        parcel.acreage
                                                                    }{' '}
                                                                    acres
                                                                </span>
                                                            )}
                                                            <span className="capitalize">
                                                                {parcel.status}
                                                            </span>
                                                        </span>
                                                    </button>
                                                ),
                                            )}
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    )}
                    {(canContinueAsAdmin || canContinueToOrgDashboard) && (
                        <div className="space-y-2 border-t pt-4">
                            {canContinueToOrgDashboard &&
                                overallOrganizations.map((organization) => (
                                    <button
                                        key={`overview-${organization.id}`}
                                        type="button"
                                        onClick={() =>
                                            continueToOrgDashboard(
                                                organization.domain,
                                            )
                                        }
                                        className="flex w-full items-center justify-between gap-3 rounded-lg bg-brand-accent p-4 text-left transition-colors hover:bg-brand-accent/90"
                                    >
                                        <span className="flex items-center gap-3">
                                            <LayoutDashboard className="size-5 text-white dark:text-[#1A1917]" />
                                            <span>
                                                <span className="block text-sm font-medium text-white dark:text-[#1A1917]">
                                                    Continue to overall
                                                    dashboard
                                                </span>
                                                <span className="block text-xs text-white/80 dark:text-[#1A1917]/80">
                                                    {organization.name} —
                                                    see how all its assets are
                                                    doing
                                                </span>
                                            </span>
                                        </span>
                                        <ArrowRight className="size-4 shrink-0 text-white dark:text-[#1A1917]" />
                                    </button>
                                ))}

                            {canContinueAsAdmin && (
                                <button
                                    type="button"
                                    onClick={() =>
                                        acknowledge(() =>
                                            window.location.assign(
                                                centralDashboardUrl(
                                                    page.props.centralUrl,
                                                ),
                                            ),
                                        )
                                    }
                                    className="flex w-full items-center justify-between gap-3 rounded-lg bg-brand-accent p-4 text-left transition-colors hover:bg-brand-accent/90"
                                >
                                    <span className="flex items-center gap-3">
                                        <ShieldCheck className="size-5 text-white dark:text-[#1A1917]" />
                                        <span>
                                            <span className="block text-sm font-medium text-white dark:text-[#1A1917]">
                                                Continue to admin dashboard
                                            </span>
                                            <span className="block text-xs text-white/80 dark:text-[#1A1917]/80">
                                                Platform-level administration
                                            </span>
                                        </span>
                                    </span>
                                    <ArrowRight className="size-4 shrink-0 text-white dark:text-[#1A1917]" />
                                </button>
                            )}
                        </div>
                    )}
                </DialogContent>
            </Dialog>
        </PropertyPickerContext.Provider>
    );
}
