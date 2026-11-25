export type UtilityType = 'electricity' | 'water' | 'gas';

/**
 * Mirrors `UtilityReadingResource`, not the database row: `reading` and
 * `consumption` arrive as numbers (the resource casts the decimal strings) and
 * `utility` as the enum's backing value.
 */
export type UtilityReading = {
    id: number;
    unit_id: number;
    unit_name: string | null;
    utility: UtilityType;
    utility_label: string;
    unit_of_measure: string;
    reading: number;
    consumption: number | null;
    read_on: string;
    notes: string | null;
    recorded_by: number | null;
    recorder_name: string | null;
    created_at: string | null;
};

export type UtilityOption = {
    value: UtilityType;
    label: string;
    unit_of_measure: string;
};

export type UnitOption = {
    value: number;
    label: string;
};

export type UtilityReadingFilters = {
    unit_id: number | null;
    utility: string;
    search: string;
};

export type PaginatedReadings = {
    data: UtilityReading[];
    current_page: number;
    last_page: number;
    total: number;
};

export type ChargeUnit = {
    id: number;
    name: string;
    /** Per-unit amount for fixed charges; null for metered and shared. */
    amount: string | null;
};

export type UtilityChargeRow = {
    id: number;
    property_id: number;
    property_name: string | null;
    name: string;
    kind: 'metered' | 'fixed' | 'shared';
    kind_label: string;
    utility: string | null;
    utility_label: string | null;
    unit_of_measure: string | null;
    rate: string | null;
    allocation: string | null;
    master_amount: string | null;
    currency: string;
    gl_code: string | null;
    is_active: boolean;
    units: ChargeUnit[];
};

export type ChargeProperty = {
    id: number;
    name: string;
    slug: string;
    units: { id: number; name: string }[];
};

/**
 * The form's own shape. `unit_id` and `reading` are strings because that is
 * what an `<input>`/`<option>` produces — converting at the boundary keeps the
 * component from making numeric decisions the server already makes.
 */
export type UtilityReadingFormData = {
    unit_id: string;
    utility: UtilityType | '';
    reading: string;
    read_on: string;
    notes: string;
};
