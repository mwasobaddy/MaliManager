export function SampleBadge() {
    /*
     * Warning-soft rather than the raw Tailwind amber scale: "sample data" is
     * a caveat, so it earns a status colour, but the literal `amber-100`/
     * `amber-700` pair had two problems. It needed its own `dark:` overrides
     * to stay legible, and it was the one chip on the page that ignored the
     * theme tokens — so it drifted whenever the palette moved.
     */
    return (
        <span className="bg-status-warning-bg text-status-warning-fg inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium">
            Sample data
        </span>
    );
}
