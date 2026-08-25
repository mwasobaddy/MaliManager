export type Period = 'weekly' | 'monthly' | 'yearly';

type Props = {
    value: Period;
    onChange: (period: Period) => void;
};

const options: { value: Period; label: string }[] = [
    { value: 'weekly', label: 'Weekly' },
    { value: 'monthly', label: 'Monthly' },
    { value: 'yearly', label: 'Yearly' },
];

export function PeriodSelector({ value, onChange }: Props) {
    return (
        <div className="inline-flex rounded-md border p-0.5">
            {options.map((option) => (
                <button
                    key={option.value}
                    type="button"
                    onClick={() => onChange(option.value)}
                    className={
                        value === option.value
                            ? 'rounded bg-primary px-3 py-1 text-sm text-primary-foreground'
                            : 'rounded px-3 py-1 text-sm text-muted-foreground'
                    }
                >
                    {option.label}
                </button>
            ))}
        </div>
    );
}
