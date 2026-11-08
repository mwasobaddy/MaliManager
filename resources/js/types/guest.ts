/**
 * Mirrors `ListingResource`, not the database row: money arrives as decimal
 * strings, and only the names a guest may see are present.
 */
export type ListingTrust = {
    photos: boolean;
    deposit: boolean;
    landlord_since: number | null;
};

export type ListedUnit = {
    id: number;
    name: string;
    type: string | null;
    monthly_rent: string | null;
    deposit: string | null;
    currency: string;
    photo_url: string | null;
    trust: ListingTrust;
    property_name: string | null;
    organization_name: string | null;
};

export type SavedSearch = {
    id: number;
    name: string;
    filters: Record<string, string | number | null>;
};

export type ListingPhoto = {
    id: number;
    url: string;
    name: string;
};

export type ListedUnitDetail = {
    id: number;
    name: string;
    type: string | null;
    monthly_rent: string | null;
    deposit: string | null;
    currency: string;
    photos: ListingPhoto[];
    trust: ListingTrust;
    property_name: string | null;
    property_city: string | null;
    organization_name: string | null;
    landlord_since: number | null;
};

export type GuestLease = {
    id: number;
    property_name: string | null;
    unit_name: string | null;
    rent_amount: number | null;
    currency: string | null;
    status: string;
};

export type Workspace = {
    organization_id: number;
    organization_name: string;
    role: 'owner' | 'staff';
    url: string;
};

export type BrowseFilters = {
    query?: string | null;
    min_rent?: string | number | null;
    max_rent?: string | number | null;
    type?: string | null;
};
