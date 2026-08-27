export type User = {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type PropertySummary = {
    id: number;
    name: string;
    slug: string;
    city: string | null;
    status: string;
    units_count: number;
};

export type LandParcelSummary = {
    id: number;
    name: string;
    slug: string;
    city: string | null;
    status: string;
    acreage: number | null;
};

export type OrganizationSummary = {
    id: number;
    name: string;
    slug: string;
    domain: string | null;
    is_owner: boolean;
    properties: PropertySummary[];
    land_parcels: LandParcelSummary[];
};

export type Auth = {
    user: User;
    ai_enabled?: boolean;
    canRaiseMaintenance?: boolean;
    permissions: string[];
    organizations: OrganizationSummary[];
};

export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};
