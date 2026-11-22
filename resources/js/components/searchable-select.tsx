import * as SelectPrimitive from '@radix-ui/react-select';
import { Check, ChevronDown, Search, SearchX } from 'lucide-react';
import { useMemo, useRef, useState } from 'react';
import { cn } from '@/lib/utils';

export type SearchableSelectOption = {
    value: string;
    label?: string;
};

type SearchableSelectProps = {
    /** Form field name. Submitted through a hidden input. */
    name: string;
    options: Array<string | SearchableSelectOption>;
    defaultValue?: string;
    placeholder?: string;
    searchPlaceholder?: string;
    ariaLabel?: string;
    disabled?: boolean;
    triggerClassName?: string;
    id?: string;
};

/**
 * A Radix Select with a filter field pinned above the options — the native
 * Select has no search, and 50+ catalogue entries are unusable without one.
 * The selected value is submitted through a hidden input so the control
 * works inside plain Inertia forms next to real inputs.
 */
export function SearchableSelect({
    name,
    options,
    defaultValue = '',
    placeholder = 'Select…',
    searchPlaceholder = 'Search…',
    ariaLabel,
    disabled = false,
    triggerClassName,
    id,
}: SearchableSelectProps) {
    const normalized = useMemo<SearchableSelectOption[]>(
        () =>
            options.map((option) =>
                typeof option === 'string'
                    ? { value: option, label: option }
                    : { label: option.value, ...option },
            ),
        [options],
    );
    const [value, setValue] = useState(defaultValue);
    const [query, setQuery] = useState('');
    const searchRef = useRef<HTMLInputElement>(null);

    const filtered = useMemo(() => {
        const needle = query.trim().toLowerCase();

        if (needle === '') {
            return normalized;
        }

        return normalized.filter((option) =>
            (option.label ?? option.value).toLowerCase().includes(needle),
        );
    }, [normalized, query]);

    const selected = normalized.find((option) => option.value === value);

    return (
        <>
            <input type="hidden" name={name} value={value} />
            <SelectPrimitive.Root
                value={value}
                onValueChange={setValue}
                disabled={disabled}
                onOpenChange={(open) => {
                    if (open) {
                        setQuery('');
                        requestAnimationFrame(() => searchRef.current?.focus());
                    }
                }}
            >
                <SelectPrimitive.Trigger
                    id={id}
                    aria-label={ariaLabel ?? placeholder}
                    className={cn(
                        'flex h-9 w-full items-center justify-between gap-2 rounded-md border border-input bg-background px-3 py-2 text-sm whitespace-nowrap shadow-xs transition-[color,box-shadow] outline-none disabled:cursor-not-allowed disabled:opacity-50 [&>span]:truncate',
                        triggerClassName,
                    )}
                >
                    <SelectPrimitive.Value placeholder={placeholder}>
                        {selected ? (selected.label ?? selected.value) : null}
                    </SelectPrimitive.Value>
                    <SelectPrimitive.Icon asChild>
                        <ChevronDown className="size-4 opacity-50" />
                    </SelectPrimitive.Icon>
                </SelectPrimitive.Trigger>
                <SelectPrimitive.Portal>
                    <SelectPrimitive.Content
                        position="popper"
                        sideOffset={4}
                        avoidCollisions
                        className="relative z-50 max-h-(--radix-select-content-available-height) min-w-[8rem] origin-(--radix-select-content-transform-origin) overflow-x-hidden overflow-y-auto rounded-md border bg-popover text-popover-foreground shadow-md"
                    >
                        <div className="sticky top-0 z-10 border-b bg-popover p-2">
                            <div className="relative">
                                <Search className="pointer-events-none absolute top-2.5 left-2.5 size-4 text-muted-foreground" />
                                <input
                                    ref={searchRef}
                                    value={query}
                                    onChange={(event) =>
                                        setQuery(event.target.value)
                                    }
                                    // Radix typeahead would hijack keystrokes
                                    // and jump the highlight while typing.
                                    onKeyDown={(event) =>
                                        event.stopPropagation()
                                    }
                                    placeholder={searchPlaceholder}
                                    aria-label={searchPlaceholder}
                                    className="h-9 w-full rounded-md border border-input bg-background pr-3 pl-9 text-sm shadow-xs outline-none"
                                />
                            </div>
                        </div>
                        <SelectPrimitive.Viewport className="scroll-my-1 p-1">
                            {filtered.length === 0 ? (
                                <p className="flex items-center gap-2 px-2 py-6 text-sm text-muted-foreground">
                                    <SearchX className="size-4" />
                                    No types match.
                                </p>
                            ) : (
                                filtered.map((option) => (
                                    <SelectPrimitive.Item
                                        key={option.value}
                                        value={option.value}
                                        className="relative flex w-full cursor-default items-center gap-2 rounded-sm py-1.5 pr-8 pl-2 text-sm select-none focus:bg-accent focus:text-accent-foreground data-[disabled]:pointer-events-none data-[disabled]:opacity-50"
                                    >
                                        <SelectPrimitive.ItemText>
                                            {option.label ?? option.value}
                                        </SelectPrimitive.ItemText>
                                        <span className="absolute right-2 flex size-3.5 items-center justify-center">
                                            <SelectPrimitive.ItemIndicator>
                                                <Check className="size-4" />
                                            </SelectPrimitive.ItemIndicator>
                                        </span>
                                    </SelectPrimitive.Item>
                                ))
                            )}
                        </SelectPrimitive.Viewport>
                    </SelectPrimitive.Content>
                </SelectPrimitive.Portal>
            </SelectPrimitive.Root>
        </>
    );
}
