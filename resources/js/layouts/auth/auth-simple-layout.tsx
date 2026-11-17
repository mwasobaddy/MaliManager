import { Link, usePage } from '@inertiajs/react';
import { BadgeCheck, Smartphone, Wrench } from 'lucide-react';
import { useEffect, useState } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
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

function StatsVisual() {
    return (
        <div aria-hidden className="relative h-56 shrink-0">
            <div className="absolute top-0 left-0 w-[52%] -rotate-3 rounded-xl border bg-[#FAF8F5] p-4 text-[#2A2621] shadow-xl">
                <p className="text-[11px] font-medium text-[#6B635A]">
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
                        <div className="absolute inset-[6px] rounded-full bg-[#FAF8F5]" />
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

            <div className="absolute top-24 right-0 w-[48%] rotate-2 rounded-xl border bg-[#FAF8F5] p-4 text-[#2A2621] shadow-xl">
                <p className="text-[11px] font-medium text-[#6B635A]">
                    Rent collected
                </p>
                <p className="mt-1 text-lg font-semibold tabular-nums">
                    KES 1.24M
                </p>
                <p className="text-[11px] text-[#6B635A]">
                    of KES 1.35M this month
                </p>
                <div className="mt-3 h-1.5 w-full overflow-hidden rounded-full bg-border">
                    <div className="h-full w-[91%] rounded-full bg-brand-primary" />
                </div>
            </div>
        </div>
    );
}

function MpesaVisual() {
    return (
        <div aria-hidden className="relative mx-auto h-56 w-56 shrink-0">
            <div className="absolute inset-x-10 top-0 bottom-0 rounded-[2rem] border border-white/20 bg-white/5 p-3 backdrop-blur-sm">
                <div className="flex h-full flex-col gap-2">
                    <div className="mx-auto h-1 w-10 shrink-0 rounded-full bg-white/20" />
                    <div className="rounded-xl bg-[#FAF8F5] p-3 text-[#2A2621] shadow-xl">
                        <p className="flex items-center gap-1.5 text-[11px] font-semibold text-status-success">
                            <Smartphone className="size-3.5" />
                            M-PESA
                        </p>
                        <p className="mt-1 text-sm font-semibold">
                            STK push sent
                        </p>
                        <p className="text-[11px] text-[#6B635A]">
                            KES 25,000 · Unit A1
                        </p>
                    </div>
                    <div className="rounded-xl bg-[#FAF8F5] p-3 text-[#2A2621] shadow-xl">
                        <p className="flex items-center gap-1.5 text-[11px] font-medium">
                            <BadgeCheck className="size-3.5 text-brand-primary" />
                            Matched & receipted
                        </p>
                    </div>
                </div>
            </div>
        </div>
    );
}

function MaintenanceVisual() {
    return (
        <div
            aria-hidden
            className="relative mx-auto h-56 w-full max-w-sm shrink-0"
        >
            <div className="absolute inset-x-0 top-4 rotate-1 rounded-xl border bg-[#FAF8F5] p-4 text-[#2A2621] shadow-xl">
                <div className="flex items-center justify-between gap-2">
                    <p className="flex items-center gap-1.5 text-[11px] font-medium text-[#6B635A]">
                        <Wrench className="size-3.5" />
                        Fix leaking tap · A3
                    </p>
                    <span className="rounded-full bg-brand-accent/20 px-2 py-0.5 text-[10px] font-semibold text-brand-accent">
                        High
                    </span>
                </div>
                <div className="mt-3 h-1.5 w-full overflow-hidden rounded-full bg-border">
                    <div className="h-full w-2/3 rounded-full bg-brand-primary" />
                </div>
                <p className="mt-2 text-[11px] text-[#6B635A]">
                    Assigned: Caretaker · In progress
                </p>
            </div>
            <div className="absolute right-2 bottom-2 -rotate-2 rounded-xl border bg-[#FAF8F5] px-3 py-2 text-[#2A2621] shadow-xl">
                <p className="flex items-center gap-1.5 text-[11px] font-medium">
                    <BadgeCheck className="size-3.5 text-status-success" />
                    Gate motor — resolved
                </p>
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
