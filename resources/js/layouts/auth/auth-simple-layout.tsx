import { Link, usePage } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

/**
 * Shared shell for every auth screen.
 *
 * One rounded card on a soft canvas, split into a form column and a brand
 * column. The brand column is decorative and hidden below `lg`, so a phone gets
 * the form on its own rather than a squeezed two-column layout.
 *
 * All colour comes from tokens, so the card flips with the theme while the
 * espresso brand panel stays dark in both, matching the app chrome.
 */
export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    const { name } = usePage().props;

    return (
        <div className="relative flex min-h-svh items-center justify-center bg-background p-4 sm:p-6 lg:p-10">
            {/* Ambient brand glow behind the card. */}
            <div
                aria-hidden
                className="pointer-events-none absolute inset-0 overflow-hidden"
            >
                <div className="absolute -top-32 -left-24 size-[28rem] rounded-full bg-brand-primary-100 opacity-60 blur-3xl dark:opacity-20" />
                <div className="absolute right-0 -bottom-32 size-[26rem] rounded-full bg-brand-accent-100 opacity-50 blur-3xl dark:opacity-20" />
            </div>

            <div className="relative grid w-full max-w-6xl overflow-hidden rounded-[28px] border border-border bg-card shadow-[0_1px_2px_rgb(42_38_33_/_0.04),0_24px_60px_-24px_rgb(42_38_33_/_0.25)] lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
                {/* Form column */}
                <div className="flex flex-col gap-8 px-6 py-8 sm:px-10 sm:py-10 lg:px-14 lg:py-12">
                    <Link
                        href={home()}
                        className="flex items-center gap-2 self-start font-medium"
                        aria-label={`${name ?? 'MaliManager'} home`}
                    >
                        <span className="flex size-9 items-center justify-center">
                            <AppLogoIcon className="size-9" />
                        </span>
                        <span className="text-xl leading-tight font-semibold tracking-tight">
                            <span className="text-brand-primary-600">Mali</span>
                            <span className="text-brand-accent">Manager</span>
                        </span>
                    </Link>

                    <div className="my-auto flex flex-col gap-6">
                        {(title || description) && (
                            <div className="space-y-2 text-center">
                                {title && (
                                    <h1 className="text-2xl font-semibold tracking-tight text-balance text-foreground">
                                        {title}
                                    </h1>
                                )}
                                {description && (
                                    <p className="mx-auto max-w-sm text-sm text-balance text-muted-foreground">
                                        {description}
                                    </p>
                                )}
                            </div>
                        )}

                        <div className="mx-auto w-full max-w-md">
                            {children}
                        </div>
                    </div>

                    <p className="mt-auto text-center text-xs text-muted-foreground">
                        © {new Date().getFullYear()} {name ?? 'MaliManager'}.
                        All rights reserved.
                    </p>
                </div>

                {/* Brand column */}
                <div className="relative hidden p-3 lg:block">
                    <div className="relative flex h-full flex-col overflow-hidden rounded-[20px] bg-brand-secondary px-10 py-12 text-on-secondary">
                        {/* Faint grid, like the reference panel. */}
                        <div
                            aria-hidden
                            className="absolute inset-0 opacity-[0.06]"
                            style={{
                                backgroundImage:
                                    'linear-gradient(rgb(255 255 255) 1px, transparent 1px), linear-gradient(90deg, rgb(255 255 255) 1px, transparent 1px)',
                                backgroundSize: '56px 56px',
                            }}
                        />

                        {/* Floating stat cards. */}
                        <div aria-hidden className="relative h-56 shrink-0">
                            <div className="absolute top-0 left-0 w-[52%] -rotate-3 rounded-xl border bg-card p-4 text-foreground shadow-xl">
                                <p className="text-[11px] font-medium text-muted-foreground">
                                    Occupancy
                                </p>
                                <div className="mt-2 flex items-center gap-3">
                                    <div
                                        className="relative size-14 shrink-0 rounded-full"
                                        style={{
                                            background:
                                                'conic-gradient(var(--brand-primary) 0 66%, var(--brand-accent) 66% 88%, var(--border) 88% 100%)',
                                        }}
                                    >
                                        <div className="absolute inset-[6px] rounded-full bg-card" />
                                        <span className="absolute inset-0 grid place-items-center text-xs font-semibold">
                                            88%
                                        </span>
                                    </div>
                                    <div className="space-y-1 text-[11px]">
                                        <p className="flex items-center gap-1.5">
                                            <span className="size-1.5 rounded-full bg-brand-primary" />
                                            Occupied
                                        </p>
                                        <p className="flex items-center gap-1.5">
                                            <span className="size-1.5 rounded-full bg-brand-accent" />
                                            In notice
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div className="absolute top-24 right-0 w-[48%] rotate-2 rounded-xl border bg-card p-4 text-foreground shadow-xl">
                                <p className="text-[11px] font-medium text-muted-foreground">
                                    Rent collected
                                </p>
                                <p className="mt-1 text-lg font-semibold tabular-nums">
                                    KES 1.24M
                                </p>
                                <p className="text-[11px] text-muted-foreground">
                                    of KES 1.35M this month
                                </p>
                                <div className="mt-3 h-1.5 w-full overflow-hidden rounded-full bg-border">
                                    <div className="h-full w-[91%] rounded-full bg-brand-primary" />
                                </div>
                            </div>
                        </div>

                        {/* Brand statement. */}
                        <div className="relative my-auto text-center">
                            <div className="mx-auto grid size-14 place-items-center rounded-2xl bg-white/10 ring-1 ring-white/15">
                                <AppLogoIcon className="size-8" />
                            </div>
                            <h2 className="mt-6 font-display text-2xl leading-tight font-semibold tracking-tight text-balance">
                                Every property. Every tenant. One dashboard.
                            </h2>
                            <p className="mx-auto mt-3 max-w-xs text-sm leading-relaxed text-on-secondary-muted">
                                A single, real-time view across every unit —
                                rent through M-Pesa, leases and maintenance in
                                one place.
                            </p>
                        </div>

                        {/* Progress rail, echoing the reference's slide bars. */}
                        <div
                            aria-hidden
                            className="relative mt-10 flex gap-1.5"
                        >
                            <span className="h-1 w-10 rounded-full bg-brand-primary" />
                            <span className="h-1 w-10 rounded-full bg-brand-accent" />
                            <span className="h-1 flex-1 rounded-full bg-white/15" />
                            <span className="h-1 flex-1 rounded-full bg-white/15" />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}
