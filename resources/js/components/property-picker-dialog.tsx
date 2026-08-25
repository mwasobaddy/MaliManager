import { usePage } from '@inertiajs/react';
import { Building2, Layers, MapPin } from 'lucide-react';
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
};

const PropertyPickerContext = createContext<PropertyPickerContextValue | null>(null);

export function usePropertyPicker(): PropertyPickerContextValue {
    const ctx = useContext(PropertyPickerContext);

    if (!ctx) {
        throw new Error('usePropertyPicker must be used within a PropertyPickerProvider');
    }

    return ctx;
}

function tenantUrl(organizationDomain: string | null, propertySlug: string): string {
    if (!organizationDomain) {
        return '/dashboard';
    }

    const scheme = window.location.protocol;

    return `${scheme}//${organizationDomain}/${propertySlug}/dashboard`;
}

export function PropertyPickerProvider({ children }: { children: React.ReactNode }) {
    const page = usePage();
    const organizations = (page.props.auth?.organizations ?? []) as OrganizationSummary[];
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

    const hasProperties = organizations.some((organization) => organization.properties.length > 0);

    const selectProperty = (organizationDomain: string | null, propertySlug: string) => {
        setIsOpen(false);
        window.location.assign(tenantUrl(organizationDomain, propertySlug));
    };

    return (
        <PropertyPickerContext.Provider value={{ open, close, organizations, hasProperties }}>
            {children}
            <Dialog open={isOpen} onOpenChange={setIsOpen}>
                <DialogContent className="sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>Select a property</DialogTitle>
                        <DialogDescription>
                            Choose the organization and property you want to manage.
                        </DialogDescription>
                    </DialogHeader>

                    {!hasProperties ? (
                        <p className="text-sm text-muted-foreground">
                            You don't have any properties assigned to you yet.
                        </p>
                    ) : (
                        <div className="max-h-[60vh] space-y-6 overflow-y-auto">
                            {organizations.map((organization) => (
                                <div key={organization.id}>
                                    <div className="mb-2 flex items-center gap-2">
                                        <h3 className="text-sm font-semibold">
                                            {organization.name}
                                        </h3>
                                        <span className="rounded bg-muted px-1.5 py-0.5 text-xs text-muted-foreground">
                                            {organization.is_owner ? 'Owner' : 'Staff'}
                                        </span>
                                    </div>

                                    <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                        {organization.properties.map((property) => (
                                            <button
                                                key={property.id}
                                                type="button"
                                                onClick={() =>
                                                    selectProperty(organization.domain, property.slug)
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
                                                            {property.city}
                                                        </span>
                                                    )}
                                                    <span className="flex items-center gap-1">
                                                        <Layers className="size-3" />
                                                        {property.units_count} units
                                                    </span>
                                                    <span className="capitalize">
                                                        {property.status}
                                                    </span>
                                                </span>
                                            </button>
                                        ))}
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </DialogContent>
            </Dialog>
        </PropertyPickerContext.Provider>
    );
}
