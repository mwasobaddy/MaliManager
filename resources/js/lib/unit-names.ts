/**
 * Finds a clashing unit name for the blur check on unit name inputs.
 * Comparison is case- and whitespace-insensitive ("A1" matches " a1 "),
 * because those collide in practice even though the slugger would happily
 * mint `a1-2`. Scoped by the caller to one property: similar names across
 * different buildings are normal and must not flag.
 *
 * @param value the typed name
 * @param names names already taken in the target property
 * @param currentName the row's own name when editing — keeping it is not a clash
 * @returns the existing name as stored, or null when free
 */
export function duplicateUnitName(
    value: string,
    names: readonly string[],
    currentName?: string | null,
): string | null {
    const needle = value.trim().toLowerCase();

    if (needle === '') {
        return null;
    }

    if (currentName != null && currentName.trim().toLowerCase() === needle) {
        return null;
    }

    return names.find((name) => name.trim().toLowerCase() === needle) ?? null;
}
