export type UtilityType = 'electricity' | 'water' | 'gas';

/**
 * Mirrors `UnitMeterReadingResource`, not the database row: `reading` and
 * `consumption` arrive as numbers (the resource casts the decimal strings) and
 * `utility` as the enum's backing value.
 */
export type MeterReading = {
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

export type MeterReadingFilters = {
    unit_id: number | null;
    utility: string;
};

export type PaginatedReadings = {
    data: MeterReading[];
    current_page: number;
    last_page: number;
    total: number;
};

/**
 * The form's own shape. `unit_id` and `reading` are strings because that is
 * what an `<input>`/`<option>` produces — converting at the boundary keeps the
 * component from making numeric decisions the server already makes.
 */
export type MeterReadingFormData = {
    unit_id: string;
    utility: UtilityType | '';
    reading: string;
    read_on: string;
    notes: string;
};
