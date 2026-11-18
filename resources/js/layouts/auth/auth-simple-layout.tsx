import { Link, usePage } from '@inertiajs/react';
import {
    BadgeCheck,
    BellRing,
    Camera,
    CheckCheck,
    Clock3,
    ReceiptText,
    Signal,
    Smartphone,
    Star,
    TrendingUp,
    Wrench,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import { Iphone } from '@/components/ui/iphone';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';
import { cn } from '@/lib/utils';

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

/**
 * Rotating stories for the auth brand panel. Each slide pairs a CSS-drawn
 * visual with one headline: the panel stays decorative, but the words cycle
 * so repeat visitors see more than a single poster.
 */
const AUTOPLAY_MS = 6500;

type SlideVisual = 'stats' | 'mpesa' | 'maintenance';

type Slide = {
    headline: string;
    sub: string;
    visual: SlideVisual;
    /** Alternate slides lead with the words instead of the visual. */
    visualFirst: boolean;
};

const SLIDES: Slide[] = [
    {
        headline: 'Every property. Every tenant. One dashboard.',
        sub: 'A single, real-time view across every unit — rent through M-Pesa, leases and maintenance in one place.',
        visual: 'stats',
        visualFirst: true,
    },
    {
        headline: 'Rent that collects itself.',
        sub: 'Tenants pay with an M-Pesa STK push, matched to the right unit and receipted automatically.',
        visual: 'mpesa',
        visualFirst: false,
    },
    {
        headline: 'Fixes before they become vacancies.',
        sub: 'Requests, photos and assignees in one queue, from reported to resolved.',
        visual: 'maintenance',
        visualFirst: true,
    },
];

/**
 * Portfolio overview: occupancy ring, rent momentum and the overdue nudge.
 *
 * Composition is a diagonal cascade (top-left → mid-right → bottom-left)
 * so the eye travels across the panel instead of reading two boxes
 * side-by-side. Solid warm cards carry the data over the busy photo;
 * the single dark glass chip is the depth accent.
 */
function StatsVisual() {
    const bars = [38, 62, 48, 76, 58, 88, 100];

    return (
        <div
            aria-hidden
            className="relative mx-auto h-72 w-full max-w-md shrink-0"
        >
            <div className="absolute top-0 left-0 w-[54%] -rotate-3 rounded-2xl bg-auth-card p-5 text-auth-card-ink shadow-2xl ring-1 ring-white/40">
                <div className="flex items-center justify-between gap-2">
                    <p className="text-[11px] font-medium tracking-wide text-auth-card-muted uppercase">
                        Occupancy
                    </p>
                    <span className="flex items-center gap-1 rounded-full bg-status-success-bg px-2 py-0.5 text-[10px] font-semibold text-status-success-fg">
                        <TrendingUp className="size-3" />
                        +2.1%
                    </span>
                </div>
                <div className="mt-2 flex items-center gap-3">
                    <div
                        className="relative size-14 shrink-0 rounded-full"
                        style={{
                            background:
                                'conic-gradient(var(--brand-primary) 0 66%, var(--brand-accent) 66% 88%, var(--auth-card-line) 88% 100%)',
                        }}
                    >
                        <div className="absolute inset-[6px] rounded-full bg-auth-card" />
                        <span className="absolute inset-0 grid place-items-center text-xs font-semibold">
                            88%
                        </span>
                    </div>
                    <div className="space-y-1 text-[11px]">
                        <p className="flex items-center gap-1.5">
                            <span className="size-1.5 rounded-full bg-brand-primary" />
                            Occupied · 66%
                        </p>
                        <p className="flex items-center gap-1.5">
                            <span className="size-1.5 rounded-full bg-brand-accent" />
                            In notice · 22%
                        </p>
                        <p className="flex items-center gap-1.5 text-auth-card-muted">
                            <span className="size-1.5 rounded-full bg-auth-card-line" />
                            Vacant · 12%
                        </p>
                    </div>
                </div>
            </div>

            <div className="absolute top-20 right-0 w-[50%] rotate-2 rounded-2xl bg-auth-card p-5 text-auth-card-ink shadow-2xl ring-1 ring-white/40">
                <p className="text-[11px] font-medium tracking-wide text-auth-card-muted uppercase">
                    Rent collected · Oct
                </p>
                <p className="mt-1 text-lg font-semibold tabular-nums">
                    KES 1.24M
                </p>
                <p className="text-[11px] text-auth-card-muted">
                    of KES 1.35M · 91%
                </p>
                <div className="mt-2 flex h-7 items-end gap-1" aria-hidden>
                    {bars.map((height, index) => (
                        <span
                            key={index}
                            style={{ height: `${height}%` }}
                            className={
                                index === bars.length - 1
                                    ? 'w-full flex-1 rounded-sm bg-brand-accent'
                                    : 'w-full flex-1 rounded-sm bg-brand-primary/75'
                            }
                        />
                    ))}
                </div>
            </div>

            <div className="absolute bottom-0 left-6 flex rotate-1 items-center gap-2 rounded-full bg-black/55 py-1.5 pr-3 pl-2 text-white shadow-xl ring-1 ring-white/25 backdrop-blur-md">
                <span className="grid size-6 place-items-center rounded-full bg-status-warning-bg">
                    <BellRing className="size-3.5 text-status-warning-fg" />
                </span>
                <p className="text-[11px] font-medium">
                    6 overdue · KES 84K — nudge sent
                </p>
            </div>
        </div>
    );
}

/**
 * M-Pesa collection inside the real Magic UI iPhone frame (true 433:882
 * proportions): the phone that asks, the ledger that matches, the tenant
 * who gets a receipt. Phone sits left; proof stacks right; the reconciled
 * pill bridges the two at the bottom.
 */
function MpesaVisual() {
    const barcode = [2, 1, 1, 3, 1, 2, 1, 1, 2, 3, 1, 2, 1, 1, 3, 2, 1, 2];

    return (
        <div
            aria-hidden
            className="relative mx-auto h-72 w-full max-w-md shrink-0"
        >
            <span className="absolute top-0 left-4 block h-full">
                <Iphone
                    className="-rotate-2"
                    style={{ height: '100%', width: 'auto' }}
                >
                    <span className="flex h-full w-full flex-col bg-auth-screen">
                        <span className="flex items-center justify-between px-5 pt-7 text-[9px] font-medium text-white/90">
                            9:41
                            <Signal className="size-3" />
                        </span>
                        <span className="flex flex-1 flex-col gap-1.5 p-2">
                            <span className="rounded-xl bg-auth-card p-2.5 text-auth-card-ink shadow-xl">
                                <span className="flex items-center gap-1.5 text-[10px] font-semibold text-status-success">
                                    <Smartphone className="size-3" />
                                    M-PESA
                                </span>
                                <span className="mt-0.5 block text-[13px] font-semibold">
                                    STK push sent
                                </span>
                                <span className="block text-[10px] text-auth-card-muted">
                                    KES 25,000 · Unit A1
                                </span>
                                <span className="mt-1 flex items-center gap-1 text-[9px] text-auth-card-muted tabular-nums">
                                    <span className="size-1.5 animate-pulse rounded-full bg-status-warning" />
                                    14:02 · awaiting PIN
                                </span>
                            </span>
                            <span className="rounded-xl bg-auth-card p-2.5 text-auth-card-ink shadow-xl">
                                <span className="flex items-center gap-1.5 text-[10px] font-medium">
                                    <BadgeCheck className="size-3 text-brand-primary" />
                                    Matched & receipted
                                </span>
                                <span className="mt-0.5 block text-[9px] text-auth-card-muted tabular-nums">
                                    14:03 · 42s later
                                </span>
                            </span>
                        </span>
                        <span className="mx-auto mb-1.5 h-1 w-16 shrink-0 rounded-full bg-white/30" />
                    </span>
                </Iphone>
            </span>

            <div className="absolute top-6 right-0 w-[50%] rotate-1 rounded-2xl bg-auth-card p-4 text-auth-card-ink shadow-2xl ring-1 ring-white/40">
                <p className="flex items-center gap-1.5 text-[11px] font-semibold">
                    <ReceiptText className="size-3.5 text-brand-primary" />
                    RCP-2026-0841
                </p>
                <p className="mt-1 text-sm font-semibold tabular-nums">
                    KES 25,000 · A1
                </p>
                <div
                    aria-hidden
                    className="my-2 border-t border-dashed border-auth-card-line"
                />
                <div aria-hidden className="flex h-5 items-stretch gap-[2px]">
                    {barcode.map((width, index) => (
                        <span
                            key={index}
                            style={{ width }}
                            className="bg-auth-card-ink"
                        />
                    ))}
                </div>
                <p className="mt-1.5 text-[11px] text-auth-card-muted">
                    Auto-matched to the right unit
                </p>
            </div>

            <div className="absolute top-44 right-2 flex w-[46%] rotate-2 items-center gap-2 rounded-2xl rounded-br-md bg-black/55 p-3 text-white shadow-xl ring-1 ring-white/25 backdrop-blur-md">
                <span className="grid size-7 shrink-0 place-items-center rounded-full bg-status-success-bg">
                    <CheckCheck className="size-4 text-status-success-fg" />
                </span>
                <div>
                    <p className="text-[11px] font-semibold">
                        Receipt SMS → tenant
                    </p>
                    <p className="text-[10px] text-white/70">Delivered 14:03</p>
                </div>
            </div>

            <div className="absolute bottom-1 left-36 rotate-1 rounded-full bg-auth-card px-3 py-1.5 text-[11px] font-semibold text-auth-card-ink shadow-xl ring-1 ring-white/40">
                No typing · reconciled
            </div>
        </div>
    );
}

/**
 * Maintenance queue: the open ticket with its crew and SLA up top, the
 * resolved job with its rating below, and a glass turnaround chip for
 * the portfolio-level payoff.
 */
function MaintenanceVisual() {
    return (
        <div
            aria-hidden
            className="relative mx-auto h-72 w-full max-w-md shrink-0"
        >
            <div className="absolute inset-x-0 top-0 rotate-1 rounded-2xl bg-auth-card p-5 text-auth-card-ink shadow-2xl ring-1 ring-white/40">
                <div className="flex items-center justify-between gap-2">
                    <p className="flex items-center gap-2 text-xs font-semibold">
                        <span className="grid size-6 place-items-center rounded-lg bg-brand-primary-100">
                            <Wrench className="size-3.5 text-brand-primary-600" />
                        </span>
                        Fix leaking tap · A3
                    </p>
                    <span className="rounded-full bg-status-danger-bg px-2 py-0.5 text-[10px] font-semibold text-status-danger-fg">
                        High
                    </span>
                </div>
                <div className="mt-3 flex items-center gap-2">
                    <span className="size-2 rounded-full bg-brand-primary" />
                    <span className="h-0.5 w-8 rounded-full bg-brand-primary" />
                    <span className="size-2 rounded-full bg-brand-primary ring-4 ring-brand-primary-100" />
                    <span className="h-0.5 flex-1 rounded-full bg-auth-card-line" />
                    <span className="size-2 rounded-full bg-auth-card-line" />
                    <span className="ml-1 text-[10px] font-medium text-auth-card-muted">
                        Reported → In progress → Resolved
                    </span>
                </div>
                <div className="mt-3 flex items-center justify-between gap-2">
                    <div className="flex items-center gap-2">
                        <span className="flex -space-x-1.5">
                            <span className="grid size-6 place-items-center rounded-full bg-brand-primary text-[10px] font-semibold text-white ring-2 ring-auth-card">
                                W
                            </span>
                            <span className="grid size-6 place-items-center rounded-full bg-brand-accent text-[10px] font-semibold text-white ring-2 ring-auth-card">
                                O
                            </span>
                        </span>
                        <p className="text-[11px] text-auth-card-muted">
                            Wanjiku · Otis — in progress
                        </p>
                    </div>
                    <p className="flex shrink-0 items-center gap-3 text-[10px] text-auth-card-muted">
                        <span className="flex items-center gap-1">
                            <Camera className="size-3" />3
                        </span>
                        <span className="flex items-center gap-1">
                            <Clock3 className="size-3" />
                            Due tomorrow
                        </span>
                    </p>
                </div>
            </div>

            <div className="absolute bottom-9 left-2 flex -rotate-2 items-center gap-2 rounded-full bg-black/55 py-1.5 pr-3 pl-2 text-white shadow-xl ring-1 ring-white/25 backdrop-blur-md">
                <Clock3 className="size-3.5 text-brand-accent" />
                <p className="text-[11px] font-medium">
                    Avg fix 1.8 days — 32% faster
                </p>
            </div>

            <div className="absolute right-0 bottom-0 flex -rotate-1 items-center gap-2 rounded-2xl bg-auth-card px-3 py-2 text-auth-card-ink shadow-2xl ring-1 ring-white/40">
                <BadgeCheck className="size-4 shrink-0 text-status-success" />
                <p className="text-[11px] font-medium">Gate motor — resolved</p>
                <span className="flex items-center gap-1 rounded-full bg-status-warning-bg px-2 py-0.5 text-[10px] font-semibold text-status-warning-fg">
                    <Star className="size-3 fill-current" />
                    4.8
                </span>
            </div>
        </div>
    );
}

function SlideVisual({ visual }: { visual: SlideVisual }) {
    if (visual === 'mpesa') {
        return <MpesaVisual />;
    }

    if (visual === 'maintenance') {
        return <MaintenanceVisual />;
    }

    return <StatsVisual />;
}
export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    const { name } = usePage().props;
    const [slide, setSlide] = useState(0);
    const [paused, setPaused] = useState(false);
    const [reduceMotion] = useState(
        () =>
            typeof window !== 'undefined' &&
            window.matchMedia('(prefers-reduced-motion: reduce)').matches,
    );
    const current = SLIDES[slide % SLIDES.length];

    useEffect(() => {
        if (paused || reduceMotion) {
            return;
        }

        const id = window.setInterval(
            () => setSlide((previous) => (previous + 1) % SLIDES.length),
            AUTOPLAY_MS,
        );

        return () => window.clearInterval(id);
    }, [paused, reduceMotion]);

    return (
        <div className="relative flex min-h-svh w-full bg-background">
            {/* Ambient brand glow behind the card. */}
            <div
                aria-hidden
                className="pointer-events-none absolute inset-0 overflow-hidden"
            >
                <div className="absolute -top-32 -left-24 size-[28rem] rounded-full bg-brand-primary-100 opacity-60 blur-3xl dark:opacity-20" />
                <div className="absolute right-0 -bottom-32 size-[26rem] rounded-full bg-brand-accent-100 opacity-50 blur-3xl dark:opacity-20" />
            </div>

            <div className="relative grid min-h-svh w-full bg-transparent lg:grid-cols-2">
                {/* Form column */}
                <div className="flex h-full flex-col gap-8 px-6 py-8 sm:px-10 sm:py-10 lg:px-14 lg:py-12">
                    <Link
                        href={home()}
                        className="flex items-center gap-2 self-center font-medium"
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
                <div className="relative hidden h-full lg:block">
                    <div className="relative flex h-full w-full flex-col overflow-hidden bg-brand-secondary px-10 py-12 text-on-secondary">
                        {/* Skyline backdrop. Decorative — the slides carry the meaning. */}
                        <img
                            src="/images/auth-skyline.webp"
                            alt=""
                            aria-hidden
                            className="absolute inset-0 h-full w-full object-cover"
                        />
                        {/* Legibility gradient over the photo. */}
                        <div
                            aria-hidden
                            className="absolute inset-0 bg-gradient-to-t from-black/80 via-black/50 to-black/35"
                        />

                        {/* Floating stat cards. */}
                        <div
                            role="region"
                            aria-roledescription="carousel"
                            aria-label="Why MaliManager"
                            onMouseEnter={() => setPaused(true)}
                            onMouseLeave={() => setPaused(false)}
                            onFocus={() => setPaused(true)}
                            onBlur={() => setPaused(false)}
                            className="relative flex min-h-0 flex-1 flex-col"
                        >
                            <div
                                key={slide}
                                role="group"
                                aria-roledescription="slide"
                                aria-label={`${slide + 1} of ${SLIDES.length}: ${current.headline}`}
                                className="flex min-h-0 flex-1 animate-fade-in-up flex-col"
                            >
                                <div
                                    className={cn(
                                        current.visualFirst
                                            ? 'order-1'
                                            : 'order-2 mt-auto',
                                    )}
                                >
                                    <SlideVisual visual={current.visual} />
                                </div>

                                {/* Brand statement. */}
                                <div
                                    className={cn(
                                        'relative my-auto text-center',
                                        current.visualFirst
                                            ? 'order-2'
                                            : 'order-1',
                                    )}
                                >
                                    <div className="mx-auto grid size-14 place-items-center rounded-2xl bg-white/10 ring-1 ring-white/15">
                                        <AppLogoIcon className="size-8" />
                                    </div>
                                    <h2 className="mt-6 font-display text-2xl leading-tight font-semibold tracking-tight text-balance">
                                        {current.headline}
                                    </h2>
                                    <p className="mx-auto mt-3 max-w-xs text-sm leading-relaxed text-on-secondary-muted">
                                        {current.sub}
                                    </p>
                                </div>
                            </div>
                        </div>

                        {/* Dots double as the progress rail. */}
                        <div className="relative mt-10 flex gap-1.5">
                            {SLIDES.map((item, index) => (
                                <button
                                    key={item.headline}
                                    type="button"
                                    onClick={() => setSlide(index)}
                                    aria-label={`Show slide ${index + 1}: ${item.headline}`}
                                    aria-current={
                                        index === slide ? 'true' : undefined
                                    }
                                    className={cn(
                                        'h-1 rounded-full transition-all',
                                        index === slide
                                            ? 'w-10 bg-brand-accent'
                                            : 'flex-1 bg-white/15 hover:bg-white/30',
                                    )}
                                />
                            ))}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}
