import { useState } from 'react';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

export const RENT_FREQUENCIES = [
    'daily',
    'weekly',
    'monthly',
    'yearly',
] as const;

export type RentFrequency = (typeof RENT_FREQUENCIES)[number];

type FrequencySelectProps = {
    /** Form field name. Submitted through a hidden input. */
    name: string;
    /** Uncontrolled initial value. Ignored when `value` is set. */
    defaultValue?: string;
    /** Controlled value. Pair with `onValueChange`. */
    value?: string;
    onValueChange?: (value: string) => void;
    placeholder?: string;
    ariaLabel?: string;
    disabled?: boolean;
    id?: string;
};

/**
 * Billing frequency picker with the value posted through a hidden input, so
 * it works inside plain Inertia forms. Fully uncontrolled by default; pass
 * `value` when the label next to it must react to the choice.
 */
export function FrequencySelect({
    name,
    defaultValue = 'monthly',
    value: controlledValue,
    onValueChange,
    placeholder = 'Select frequency…',
    ariaLabel,
    disabled = false,
    id,
}: FrequencySelectProps) {
    const [internalValue, setInternalValue] = useState(defaultValue);
    const isControlled = controlledValue !== undefined;
    const value = isControlled ? controlledValue : internalValue;

    function handleChange(next: string) {
        if (!isControlled) {
            setInternalValue(next);
        }
        onValueChange?.(next);
    }

    return (
        <>
            <input type="hidden" name={name} value={value} />
            <Select
                value={value}
                onValueChange={handleChange}
                disabled={disabled}
            >
                <SelectTrigger id={id} aria-label={ariaLabel ?? placeholder}>
                    <SelectValue placeholder={placeholder} />
                </SelectTrigger>
                <SelectContent>
                    {RENT_FREQUENCIES.map((frequency) => (
                        <SelectItem key={frequency} value={frequency}>
                            {frequency.charAt(0).toUpperCase() +
                                frequency.slice(1)}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </>
    );
}
