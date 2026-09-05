import { Head, Link, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import { dashboard, login } from '@/routes';

type AuthUser = { name: string };
type SharedProps = {
    name: string;
    auth: { user: AuthUser | null };
};

type SectionLink = { label: string; href: string };
type Feature = { title: string; body: string };
type PortalItem = { title: string; body: string };
type Testimonial = { quote: string; author: string; role: string };
type PricingTier = {
    name: string;
    units: string;
    price: string;
    priceNote?: string;
    features: string[];
    cta: string;
    featured?: boolean;
};
type Faq = { question: string; answer: string };

const navLinks: SectionLink[] = [
    { label: 'Features', href: '#features' },
    { label: 'Pricing', href: '#pricing' },
    { label: 'Testimonials', href: '#testimonials' },
    { label: 'FAQ', href: '#faq' },
];

const features: Feature[] = [
    {
        title: 'Automated rent collection',
        body: 'Tenants pay by M-Pesa STK push straight from their phones. Every payment is matched to the right unit and receipted automatically, so month-end reconciliation stops taking three days.',
    },
    {
        title: 'Tenant & lease tracking',
        body: "Store every lease, ID, and deposit record in one place. Get notified 60 days before a lease ends, so renewals happen on your terms, not the tenant's.",
    },
    {
        title: 'Expense & maintenance analytics',
        body: 'Log repairs as they happen and see exactly what each property costs to run. Compare spend across buildings and catch the units quietly eating your margin.',
    },
];

const landlordView: PortalItem[] = [
    {
        title: 'Portfolio overview',
        body: 'Occupancy, income, and arrears across every building you manage, in one screen.',
    },
    {
        title: 'Rent collection status',
        body: "See who has paid, who's late, and send an M-Pesa reminder with one tap.",
    },
    {
        title: 'Maintenance queue',
        body: 'Every request, prioritised, with a caretaker assigned and a cost logged.',
    },
    {
        title: 'Financial reports',
        body: 'Export a statement per property, ready for your accountant or KRA filing.',
    },
];

const tenantView: PortalItem[] = [
    {
        title: 'Pay rent in one tap',
        body: 'An M-Pesa prompt lands on their phone — no paybill number to remember.',
    },
    {
        title: 'Submit a maintenance request',
        body: 'Photo, description, and a status they can follow through to done.',
    },
    {
        title: 'View lease & payment history',
        body: 'Every receipt and the lease terms, always available, never lost.',
    },
    {
        title: 'Message the caretaker',
        body: 'One thread per unit, so nothing gets lost in a group chat.',
    },
];

const testimonials: Testimonial[] = [
    {
        quote: 'Since we moved our six buildings onto MaliManager, month-end reconciliation went from three days to about twenty minutes.',
        author: 'Wanjiru K.',
        role: 'Property Manager, Nairobi',
    },
    {
        quote: 'My tenants pay through M-Pesa now and I see it land in real time. No more chasing people for bank slips at the end of the month.',
        author: 'Otieno M.',
        role: 'Landlord, Kisumu',
    },
    {
        quote: 'I manage two estates from Mombasa and one in Nairobi. MaliManager is the only reason I can do that without a full-time office.',
        author: 'Fatuma A.',
        role: 'Estate Manager, Mombasa',
    },
];

const pricingTiers: PricingTier[] = [
    {
        name: 'Starter',
        units: 'Up to 10 units',
        price: 'Free',
        features: ['M-Pesa rent collection', 'Lease & tenant records', 'Single manager login'],
        cta: 'Get Started',
    },
    {
        name: 'Growth',
        units: 'Up to 50 units',
        price: 'KES 4,900',
        priceNote: '/mo',
        features: [
            'Everything in Starter',
            'Maintenance & expense analytics',
            'Up to 5 manager logins',
            'Financial exports',
        ],
        cta: 'Get Started',
        featured: true,
    },
    {
        name: 'Portfolio',
        units: 'Unlimited units',
        price: 'Custom',
        features: ['Everything in Growth', 'Multiple estates & teams', 'Dedicated onboarding'],
        cta: 'Talk to Us',
    },
];

const faqs: Faq[] = [
    {
        question: 'Does MaliManager work with M-Pesa?',
        answer: 'Yes. Tenants receive an M-Pesa STK push to pay rent directly from their phone, and every payment is automatically matched to the right unit and receipted.',
    },
    {
        question: 'Can I manage properties in different towns?',
        answer: 'Yes. MaliManager is built for portfolios spread across counties — the same dashboard covers Nairobi, Mombasa, Kisumu, or anywhere else your buildings are.',
    },
    {
        question: "Is my portfolio's data kept separate from other landlords'?",
        answer: 'Yes. MaliManager is fully multitenant — your properties, tenants, and financial records are isolated from every other account on the platform.',
    },
    {
        question: 'Can my caretaker or accountant have their own login?',
        answer: 'Yes. Growth and Portfolio plans support multiple manager logins with different levels of access, so your team sees only what they need.',
    },
    {
        question: 'What happens if I outgrow the Starter plan?',
        answer: 'You upgrade to Growth in a couple of clicks, right from your dashboard. Nothing is exported or migrated — your data just gains more room.',
    },
];

const primaryCta =
    'inline-flex items-center justify-center rounded-full bg-primary px-5 py-2.5 text-[15px] font-semibold text-primary-foreground transition-colors hover:bg-primary/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring';
const ghostCta =
    'inline-flex items-center justify-center rounded-full border border-border px-4 py-2.5 text-[15px] font-medium text-foreground transition-colors hover:border-muted-foreground/50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring';

const prefersReducedMotion = (): boolean =>
    typeof window === 'undefined' ? false : window.matchMedia('(prefers-reduced-motion: reduce)').matches;

function formatStatValue(value: number, format: 'plain' | 'comma', suffix: string): string {
    return (format === 'comma' ? value.toLocaleString() : String(value)) + suffix;
}

type AnimatedStatProps = {
    value: number;
    suffix?: string;
    format?: 'plain' | 'comma';
};

function AnimatedStat({ value, suffix = '', format = 'plain' }: AnimatedStatProps) {
    const [reducedMotion] = useState(() => prefersReducedMotion());
    const [display, setDisplay] = useState(() =>
        reducedMotion ? formatStatValue(value, format, suffix) : formatStatValue(0, format, suffix),
    );

    useEffect(() => {
        if (reducedMotion) {
            return;
        }

        const duration = 1100;
        const startTime = performance.now();
        let frame = 0;

        const tick = (now: number) => {
            const progress = Math.min((now - startTime) / duration, 1);
            const eased = 1 - Math.pow(1 - progress, 3);

            setDisplay(formatStatValue(Math.round(value * eased), format, suffix));

            if (progress < 1) {
                frame = requestAnimationFrame(tick);
            }
        };

        frame = requestAnimationFrame(tick);

        return () => cancelAnimationFrame(frame);
    }, [value, format, suffix, reducedMotion]);

    return <>{display}</>;
}

function FeatureIcon({ kind }: { kind: 0 | 1 | 2 }) {
    if (kind === 1) {
        return (
            <svg
                className="text-primary"
                width="22"
                height="22"
                viewBox="0 0 24 24"
                fill="none"
            >
                <path
                    d="M9 12h6m-6 4h6M9 8h1M5 21h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v14a2 2 0 002 2z"
                    stroke="currentColor"
                    strokeWidth="1.6"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                />
            </svg>
        );
    }

    if (kind === 2) {
        return (
            <svg
                className="text-primary"
                width="22"
                height="22"
                viewBox="0 0 24 24"
                fill="none"
            >
                <path
                    d="M4 19V10m6 9V5m6 14v-6"
                    stroke="currentColor"
                    strokeWidth="1.6"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                />
            </svg>
        );
    }

    return (
        <svg
            className="text-primary"
            width="22"
            height="22"
            viewBox="0 0 24 24"
            fill="none"
        >
            <path
                d="M3 10h18M7 15h2m4 0h4M5 6h14a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2z"
                stroke="currentColor"
                strokeWidth="1.6"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
        </svg>
    );
}

function BrandMark({ name }: { name: string }) {
    return (
        <span className="flex items-center gap-2.5">
            <span className="flex aspect-square size-9 items-center justify-center rounded-full border-2 border-primary p-1 text-primary shadow-sm">
                <AppLogoIcon className="size-7" />
            </span>
            <span className="text-lg font-semibold tracking-tight text-foreground">{name}</span>
        </span>
    );
}

function Chevron() {
    return (
        <svg
            className="text-muted-foreground"
            width="18"
            height="18"
            viewBox="0 0 24 24"
            fill="none"
        >
            <path
                d="M6 9l6 6 6-6"
                stroke="currentColor"
                strokeWidth="1.8"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
        </svg>
    );
}

const revealClasses = 'animate-fade-in-up motion-reduce:animate-none';

export default function Welcome() {
    const { name, auth } = usePage<SharedProps>().props;
    const [menuOpen, setMenuOpen] = useState(false);
    const [portalView, setPortalView] = useState<'landlord' | 'tenant'>('landlord');
    const [openFaq, setOpenFaq] = useState<number | null>(null);

    const primaryHref = auth.user ? dashboard().url : login().url;

    return (
        <>
            <Head title="Property management for Kenyan landlords" />

            <div className="scroll-smooth bg-background font-sans text-foreground antialiased">
                {/* ============ NAV ============ */}
                <header className="sticky top-0 z-50 border-b border-border bg-background/85 backdrop-blur-md">
                    <nav className="mx-auto flex h-18 max-w-7xl items-center justify-between px-6 lg:px-10">
                        <a
                            href="#top"
                            aria-label={`${name} home`}
                            className="shrink-0 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                        >
                            <BrandMark name={name} />
                        </a>

                        <div className="hidden items-center gap-9 text-[15px] text-muted-foreground md:flex">
                            {navLinks.map((link) => (
                                <a
                                    key={link.href}
                                    href={link.href}
                                    className="transition-colors hover:text-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                                >
                                    {link.label}
                                </a>
                            ))}
                        </div>

                        <div className="hidden items-center gap-3 md:flex">
                            {auth.user ? (
                                <Link href={primaryHref} className={primaryCta}>
                                    Dashboard
                                </Link>
                            ) : (
                                <>
                                    <Link href={login().url} className={ghostCta}>
                                        Log In
                                    </Link>
                                    <Link href={login().url} className={primaryCta}>
                                        Get Started
                                    </Link>
                                </>
                            )}
                        </div>

                        <button
                            type="button"
                            onClick={() => setMenuOpen((open) => !open)}
                            aria-expanded={menuOpen}
                            aria-controls="mobile-menu"
                            aria-label="Toggle menu"
                            className="p-2 text-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring md:hidden"
                        >
                            {menuOpen ? (
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                                    <path
                                        d="M6 6l12 12M18 6L6 18"
                                        stroke="currentColor"
                                        strokeWidth="1.8"
                                        strokeLinecap="round"
                                    />
                                </svg>
                            ) : (
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                                    <path
                                        d="M3 6h18M3 12h18M3 18h18"
                                        stroke="currentColor"
                                        strokeWidth="1.8"
                                        strokeLinecap="round"
                                    />
                                </svg>
                            )}
                        </button>
                    </nav>

                    {menuOpen && (
                        <div
                            id="mobile-menu"
                            className="space-y-4 border-t border-border bg-background px-6 py-5 md:hidden"
                        >
                            {navLinks.map((link) => (
                                <a
                                    key={link.href}
                                    href={link.href}
                                    onClick={() => setMenuOpen(false)}
                                    className="block text-muted-foreground transition-colors hover:text-foreground"
                                >
                                    {link.label}
                                </a>
                            ))}
                            <div className="flex flex-col gap-3 pt-3">
                                {auth.user ? (
                                    <Link
                                        href={primaryHref}
                                        onClick={() => setMenuOpen(false)}
                                        className={`${primaryCta} w-full text-center`}
                                    >
                                        Dashboard
                                    </Link>
                                ) : (
                                    <>
                                        <Link
                                            href={login().url}
                                            onClick={() => setMenuOpen(false)}
                                            className={`${ghostCta} w-full text-center`}
                                        >
                                            Log In
                                        </Link>
                                        <Link
                                            href={login().url}
                                            onClick={() => setMenuOpen(false)}
                                            className={`${primaryCta} w-full text-center`}
                                        >
                                            Get Started
                                        </Link>
                                    </>
                                )}
                            </div>
                        </div>
                    )}
                </header>

                {/* ============ HERO ============ */}
                <section id="top" className="grid-texture relative overflow-hidden">
                    <div className="glow-orb absolute -top-40 -left-40 size-[520px] rounded-full bg-primary/15 blur-[90px] dark:bg-primary/20" />
                    <div className="absolute top-40 right-0 size-[420px] rounded-full bg-brand-accent/15 blur-[90px] dark:bg-brand-accent/20" />

                    <div className="relative z-10 mx-auto grid max-w-7xl items-center gap-14 px-6 pt-16 pb-20 lg:grid-cols-[1.05fr_0.95fr] lg:gap-8 lg:px-10 lg:pt-24 lg:pb-28">
                        <div>
                            <div
                                className={`${revealClasses} mb-7 inline-flex items-center gap-2 rounded-full border border-border bg-card/70 px-3.5 py-1.5 text-sm text-muted-foreground`}
                                style={{ animationDelay: '0.05s' }}
                            >
                                <span className="size-1.5 rounded-full bg-primary" />
                                Built for landlords and caretakers across Kenya
                            </div>

                            <h1
                                className={`${revealClasses} font-display max-w-xl text-[2.35rem] leading-[1.1] font-semibold tracking-tight text-foreground sm:text-5xl lg:text-[3.4rem]`}
                                style={{ animationDelay: '0.1s' }}
                            >
                                Every property. Every tenant. One dashboard.
                            </h1>

                            <p
                                className={`${revealClasses} mt-6 max-w-md text-lg leading-relaxed text-muted-foreground`}
                                style={{ animationDelay: '0.15s' }}
                            >
                                MaliManager gives landlords and property managers a single, real-time
                                view across every unit — rent collected through M-Pesa, leases, and
                                maintenance, all in one place.
                            </p>

                            <div
                                className={`${revealClasses} mt-9 flex flex-col gap-3.5 sm:flex-row`}
                                style={{ animationDelay: '0.2s' }}
                            >
                                <Link
                                    href={primaryHref}
                                    className="inline-flex items-center justify-center rounded-full bg-primary px-6 py-3.5 font-semibold text-primary-foreground transition-colors hover:bg-primary/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                                >
                                    {auth.user ? 'Open Dashboard' : 'Get Started Free'}
                                </Link>
                                <a
                                    href="#features"
                                    className="inline-flex items-center justify-center rounded-full border border-border px-6 py-3.5 font-medium text-foreground transition-colors hover:border-muted-foreground/50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                                >
                                    Watch a 2-Minute Demo
                                </a>
                            </div>

                            <div
                                className={`${revealClasses} mt-10 flex items-center gap-6 text-sm text-muted-foreground`}
                                style={{ animationDelay: '0.25s' }}
                            >
                                <span>No card required</span>
                                <span className="size-1 rounded-full bg-border" />
                                <span>Setup in under a day</span>
                            </div>
                        </div>

                        {/* Dashboard mock */}
                        <div className={`${revealClasses} relative`} style={{ animationDelay: '0.3s' }}>
                            <div className="absolute -inset-3 rounded-[2rem] bg-gradient-to-br from-primary/15 to-transparent blur-xl" />
                            <div className="relative rounded-3xl border border-border bg-card p-5 shadow-2xl shadow-foreground/10 sm:p-6">
                                <div className="mb-5 flex items-center justify-between">
                                    <div>
                                        <p className="text-xs text-muted-foreground">
                                            Portfolio overview
                                        </p>
                                        <p className="font-semibold text-foreground">
                                            Kilimani &amp; Westlands Cluster
                                        </p>
                                    </div>
                                    <span className="rounded-full border border-brand-accent/30 bg-brand-accent/10 px-2.5 py-1 text-xs text-brand-accent">
                                        Live
                                    </span>
                                </div>

                                <div className="mb-4 grid grid-cols-2 gap-3">
                                    <div className="rounded-2xl border border-border bg-muted p-4">
                                        <p className="mb-1 text-xs text-muted-foreground">
                                            Occupancy
                                        </p>
                                        <p className="text-2xl font-semibold text-foreground">
                                            <AnimatedStat value={94} suffix="%" />
                                        </p>
                                    </div>
                                    <div className="rounded-2xl border border-border bg-muted p-4">
                                        <p className="mb-1 text-xs text-muted-foreground">
                                            Active leases
                                        </p>
                                        <p className="text-2xl font-semibold text-foreground">
                                            <AnimatedStat value={142} />
                                        </p>
                                    </div>
                                </div>

                                <div className="mb-4 rounded-2xl border border-border bg-muted p-4">
                                    <div className="mb-1 flex items-center justify-between">
                                        <p className="text-xs text-muted-foreground">
                                            Rent collected · this month
                                        </p>
                                        <span className="text-xs font-medium text-status-success">
                                            ↑ 8.2%
                                        </span>
                                    </div>
                                    <p className="text-2xl font-semibold text-foreground">
                                        KES <AnimatedStat value={2438000} format="comma" />
                                    </p>
                                    <div className="mt-3 h-1.5 overflow-hidden rounded-full bg-border">
                                        <div
                                            className="h-full rounded-full bg-primary"
                                            style={{ width: '87%' }}
                                        />
                                    </div>
                                </div>

                                <div className="space-y-2.5">
                                    <p className="mb-1 text-xs text-muted-foreground">
                                        Recent payments
                                    </p>
                                    <div className="flex items-center justify-between border-t border-border py-2 text-sm">
                                        <div>
                                            <p className="text-foreground">
                                                Unit B4 · Kilimani Heights
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                via M-Pesa STK Push
                                            </p>
                                        </div>
                                        <p className="font-medium text-foreground">KES 28,000</p>
                                    </div>
                                    <div className="flex items-center justify-between border-t border-border py-2 text-sm">
                                        <div>
                                            <p className="text-foreground">
                                                Unit A2 · Riverpark Court
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                via M-Pesa STK Push
                                            </p>
                                        </div>
                                        <p className="font-medium text-foreground">KES 32,500</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                {/* ============ FEATURES ============ */}
                <section id="features" className="border-t border-border py-20 lg:py-28">
                    <div className="mx-auto max-w-7xl px-6 lg:px-10">
                        <div className="mb-14 max-w-lg">
                            <h2 className="font-display text-3xl font-semibold tracking-tight text-foreground lg:text-4xl">
                                Built around how Kenyan landlords actually work
                            </h2>
                            <p className="mt-4 leading-relaxed text-muted-foreground">
                                Three tools that replace the spreadsheet, the WhatsApp group, and the
                                notebook of receipts.
                            </p>
                        </div>

                        <div className="grid gap-6 md:grid-cols-3">
                            {features.map((feature, index) => (
                                <div
                                    key={feature.title}
                                    className="rounded-2xl border border-border border-l-2 border-l-primary bg-card p-7"
                                >
                                    <div className="mb-6 flex size-11 items-center justify-center rounded-xl bg-primary/10">
                                        <FeatureIcon kind={index as 0 | 1 | 2} />
                                    </div>
                                    <h3 className="font-display mb-2.5 text-lg font-semibold text-foreground">
                                        {feature.title}
                                    </h3>
                                    <p className="text-[15px] leading-relaxed text-muted-foreground">
                                        {feature.body}
                                    </p>
                                </div>
                            ))}
                        </div>
                    </div>
                </section>

                {/* ============ PORTAL TOGGLE ============ */}
                <section className="border-t border-border bg-muted/40 py-20 lg:py-28">
                    <div className="mx-auto max-w-7xl px-6 lg:px-10">
                        <div className="mb-10 max-w-lg">
                            <h2 className="font-display text-3xl font-semibold tracking-tight text-foreground lg:text-4xl">
                                One app, two experiences
                            </h2>
                            <p className="mt-4 leading-relaxed text-muted-foreground">
                                Landlords see the whole portfolio. Tenants see exactly what matters to
                                them. Nobody has to dig for information.
                            </p>
                        </div>

                        <div
                            className="mb-10 inline-flex rounded-full border border-border bg-card p-1"
                            role="tablist"
                            aria-label="Portal view"
                        >
                            <button
                                type="button"
                                role="tab"
                                aria-selected={portalView === 'landlord'}
                                aria-controls="panel-landlord"
                                onClick={() => setPortalView('landlord')}
                                className={`rounded-full px-5 py-2.5 text-sm font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring ${
                                    portalView === 'landlord'
                                        ? 'bg-primary text-primary-foreground'
                                        : 'text-muted-foreground hover:text-foreground'
                                }`}
                            >
                                Landlord view
                            </button>
                            <button
                                type="button"
                                role="tab"
                                aria-selected={portalView === 'tenant'}
                                aria-controls="panel-tenant"
                                onClick={() => setPortalView('tenant')}
                                className={`rounded-full px-5 py-2.5 text-sm font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring ${
                                    portalView === 'tenant'
                                        ? 'bg-primary text-primary-foreground'
                                        : 'text-muted-foreground hover:text-foreground'
                                }`}
                            >
                                Tenant view
                            </button>
                        </div>

                        <div className="grid items-center gap-10 lg:grid-cols-2">
                            {portalView === 'landlord' ? (
                                <ul
                                    id="panel-landlord"
                                    role="tabpanel"
                                    aria-labelledby="tab-landlord"
                                    className="space-y-4"
                                    key="landlord"
                                >
                                    {landlordView.map((item) => (
                                        <li key={item.title} className="flex gap-3.5">
                                            <span className="mt-1.5 size-1.5 shrink-0 rounded-full bg-primary" />
                                            <div>
                                                <p className="font-medium text-foreground">
                                                    {item.title}
                                                </p>
                                                <p className="text-sm text-muted-foreground">
                                                    {item.body}
                                                </p>
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            ) : (
                                <ul
                                    id="panel-tenant"
                                    role="tabpanel"
                                    aria-labelledby="tab-tenant"
                                    className="space-y-4"
                                    key="tenant"
                                >
                                    {tenantView.map((item) => (
                                        <li key={item.title} className="flex gap-3.5">
                                            <span className="mt-1.5 size-1.5 shrink-0 rounded-full bg-primary" />
                                            <div>
                                                <p className="font-medium text-foreground">
                                                    {item.title}
                                                </p>
                                                <p className="text-sm text-muted-foreground">
                                                    {item.body}
                                                </p>
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            )}

                            {portalView === 'landlord' ? (
                                <div
                                    key="mock-landlord"
                                    className="rounded-3xl border border-border bg-card p-6 shadow-xl shadow-foreground/5"
                                >
                                    <p className="mb-4 text-xs text-muted-foreground">
                                        Landlord · Portfolio
                                    </p>
                                    <div className="space-y-3">
                                        <div className="flex items-center justify-between rounded-xl border border-border bg-muted px-4 py-3">
                                            <p className="text-sm text-foreground">
                                                Riverpark Court
                                            </p>
                                            <p className="text-sm text-status-success">
                                                96% occupied
                                            </p>
                                        </div>
                                        <div className="flex items-center justify-between rounded-xl border border-border bg-muted px-4 py-3">
                                            <p className="text-sm text-foreground">
                                                Kilimani Heights
                                            </p>
                                            <p className="text-sm text-status-success">
                                                100% occupied
                                            </p>
                                        </div>
                                        <div className="flex items-center justify-between rounded-xl border border-border bg-muted px-4 py-3">
                                            <p className="text-sm text-foreground">
                                                Nyali Breeze Apartments
                                            </p>
                                            <p className="text-sm text-muted-foreground">
                                                3 units vacant
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            ) : (
                                <div
                                    key="mock-tenant"
                                    className="rounded-3xl border border-border bg-card p-6 shadow-xl shadow-foreground/5"
                                >
                                    <p className="mb-4 text-xs text-muted-foreground">
                                        Tenant · Unit B4
                                    </p>
                                    <div className="mb-3 rounded-xl border border-border bg-muted p-5">
                                        <p className="mb-1 text-sm text-muted-foreground">
                                            Rent due
                                        </p>
                                        <p className="mb-3 text-xl font-semibold text-foreground">
                                            KES 28,000
                                        </p>
                                        <button
                                            type="button"
                                            className="w-full rounded-full bg-primary py-2.5 text-sm font-semibold text-primary-foreground transition-colors hover:bg-primary/90"
                                        >
                                            Pay with M-Pesa
                                        </button>
                                    </div>
                                    <div className="flex items-center justify-between rounded-xl border border-border bg-muted px-4 py-3">
                                        <p className="text-sm text-foreground">
                                            Leaking tap — kitchen
                                        </p>
                                        <p className="text-xs text-brand-accent">In progress</p>
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>
                </section>

                {/* ============ TESTIMONIALS ============ */}
                <section id="testimonials" className="border-t border-border py-20 lg:py-28">
                    <div className="mx-auto max-w-7xl px-6 lg:px-10">
                        <div className="mb-14 max-w-lg">
                            <h2 className="font-display text-3xl font-semibold tracking-tight text-foreground lg:text-4xl">
                                What managers are saying
                            </h2>
                        </div>

                        <div className="grid gap-6 md:grid-cols-3">
                            {testimonials.map((testimonial) => (
                                <div
                                    key={testimonial.author}
                                    className="rounded-2xl border border-border bg-card p-7 shadow-xl shadow-foreground/5"
                                >
                                    <p className="mb-6 leading-relaxed text-foreground">
                                        “{testimonial.quote}”
                                    </p>
                                    <p className="text-sm font-medium text-foreground">
                                        {testimonial.author}
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        {testimonial.role}
                                    </p>
                                </div>
                            ))}
                        </div>
                    </div>
                </section>

                {/* ============ PRICING ============ */}
                <section id="pricing" className="border-t border-border bg-muted/40 py-20 lg:py-28">
                    <div className="mx-auto max-w-7xl px-6 lg:px-10">
                        <div className="mb-14 max-w-lg">
                            <h2 className="font-display text-3xl font-semibold tracking-tight text-foreground lg:text-4xl">
                                Priced by portfolio, not by promise
                            </h2>
                            <p className="mt-4 leading-relaxed text-muted-foreground">
                                Start free. Upgrade when you add units, not on a timer.
                            </p>
                        </div>

                        <div className="grid gap-6 md:grid-cols-3">
                            {pricingTiers.map((tier) => (
                                <div
                                    key={tier.name}
                                    className={`relative flex flex-col rounded-2xl p-7 ${
                                        tier.featured
                                            ? 'border-2 border-primary bg-card shadow-xl shadow-foreground/5'
                                            : 'border border-border bg-card shadow-xl shadow-foreground/5'
                                    }`}
                                >
                                    {tier.featured && (
                                        <span className="absolute -top-3 left-7 rounded-full bg-primary px-3 py-1 text-xs font-semibold text-primary-foreground">
                                            Most popular
                                        </span>
                                    )}
                                    <p className="mb-1 text-lg font-semibold text-foreground">
                                        {tier.name}
                                    </p>
                                    <p className="mb-5 text-sm text-muted-foreground">
                                        {tier.units}
                                    </p>
                                    <p className="mb-6 font-semibold text-3xl text-foreground">
                                        {tier.price}
                                        {tier.priceNote && (
                                            <span className="text-base font-normal text-muted-foreground">
                                                {tier.priceNote}
                                            </span>
                                        )}
                                    </p>
                                    <ul className="mb-8 flex-1 space-y-2.5 text-sm text-muted-foreground">
                                        {tier.features.map((feature) => (
                                            <li key={feature}>{feature}</li>
                                        ))}
                                    </ul>
                                    <Link
                                        href={login().url}
                                        className={
                                            tier.featured
                                                ? `${primaryCta} w-full text-center`
                                                : `${ghostCta} w-full text-center`
                                        }
                                    >
                                        {tier.cta}
                                    </Link>
                                </div>
                            ))}
                        </div>
                    </div>
                </section>

                {/* ============ FAQ ============ */}
                <section id="faq" className="border-t border-border py-20 lg:py-28">
                    <div className="mx-auto max-w-3xl px-6 lg:px-10">
                        <h2 className="font-display mb-10 text-3xl font-semibold tracking-tight text-foreground lg:text-4xl">
                            Questions, answered
                        </h2>

                        <div className="space-y-3">
                            {faqs.map((faq, index) => {
                                const isOpen = openFaq === index;

                                return (
                                    <div
                                        key={faq.question}
                                        className={`rounded-2xl border bg-card ${
                                            isOpen
                                                ? 'border-primary'
                                                : 'border-border'
                                        }`}
                                    >
                                        <button
                                            type="button"
                                            onClick={() => setOpenFaq(isOpen ? null : index)}
                                            aria-expanded={isOpen}
                                            className="flex w-full items-center justify-between px-6 py-5 text-left focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                                        >
                                            <span
                                                className={`font-medium ${
                                                    isOpen ? 'text-primary' : 'text-foreground'
                                                }`}
                                            >
                                                {faq.question}
                                            </span>
                                            <span
                                                className={`shrink-0 transition-transform duration-300 ${
                                                    isOpen ? 'rotate-180' : ''
                                                }`}
                                            >
                                                <Chevron />
                                            </span>
                                        </button>
                                        <div
                                            className={`grid transition-[grid-template-rows] duration-300 ${
                                                isOpen ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]'
                                            }`}
                                        >
                                            <div className="overflow-hidden">
                                                <div className="px-6">
                                                    <p className="pb-5 text-sm leading-relaxed text-muted-foreground">
                                                        {faq.answer}
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                </section>

                {/* ============ CTA STRIP ============ */}
                <section className="border-t border-border py-20">
                    <div className="mx-auto max-w-4xl px-6 text-center lg:px-10">
                        <h2 className="font-display mb-5 text-3xl font-semibold tracking-tight text-foreground lg:text-4xl">
                            Run your portfolio from your phone, starting today
                        </h2>
                        <p className="mx-auto mb-8 max-w-md text-muted-foreground">
                            No card required to start. Set up your first property in under a day.
                        </p>
                        <Link
                            href={primaryHref}
                            className="inline-flex items-center justify-center rounded-full bg-primary px-7 py-3.5 font-semibold text-primary-foreground transition-colors hover:bg-primary/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                        >
                            {auth.user ? 'Open Dashboard' : 'Get Started Free'}
                        </Link>
                    </div>
                </section>

                {/* ============ FOOTER ============ */}
                <footer className="border-t border-border py-14">
                    <div className="mx-auto max-w-7xl px-6 lg:px-10">
                        <div className="mb-12 grid gap-10 md:grid-cols-[1.2fr_1fr_1fr_1fr]">
                            <div>
                                <div className="mb-4">
                                    <BrandMark name={name} />
                                </div>
                                <p className="max-w-xs text-sm leading-relaxed text-muted-foreground">
                                    Property management built for Kenyan landlords, caretakers, and
                                    estate managers.
                                </p>
                            </div>

                            <div>
                                <p className="mb-4 text-sm font-medium text-foreground">Product</p>
                                <ul className="space-y-2.5 text-sm text-muted-foreground">
                                    <li>
                                        <a href="#features" className="transition-colors hover:text-foreground">
                                            Features
                                        </a>
                                    </li>
                                    <li>
                                        <a href="#pricing" className="transition-colors hover:text-foreground">
                                            Pricing
                                        </a>
                                    </li>
                                    <li>
                                        <a href="#faq" className="transition-colors hover:text-foreground">
                                            FAQ
                                        </a>
                                    </li>
                                </ul>
                            </div>

                            <div>
                                <p className="mb-4 text-sm font-medium text-foreground">Company</p>
                                <ul className="space-y-2.5 text-sm text-muted-foreground">
                                    <li className="hover:text-foreground">About</li>
                                    <li className="hover:text-foreground">Careers</li>
                                    <li className="hover:text-foreground">Contact</li>
                                </ul>
                            </div>

                            <div>
                                <p className="mb-4 text-sm font-medium text-foreground">Legal</p>
                                <ul className="space-y-2.5 text-sm text-muted-foreground">
                                    <li className="hover:text-foreground">Privacy</li>
                                    <li className="hover:text-foreground">Terms</li>
                                </ul>
                            </div>
                        </div>

                        <div className="flex flex-col items-center justify-between gap-5 border-t border-border pt-8 sm:flex-row">
                            <p className="text-sm text-muted-foreground">
                                © 2026 MaliManager. All rights reserved.
                            </p>
                            <div className="flex items-center gap-4">
                                <a
                                    href="#"
                                    aria-label="X / Twitter"
                                    className="text-muted-foreground transition-colors hover:text-foreground"
                                >
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M18.9 2H22l-7.6 8.7L23.3 22h-7l-5.5-6.9L4.5 22H1.4l8.1-9.3L1 2h7.2l5 6.3L18.9 2zm-1.2 18h1.7L7.4 4H5.6l12.1 16z" />
                                    </svg>
                                </a>
                                <a
                                    href="#"
                                    aria-label="LinkedIn"
                                    className="text-muted-foreground transition-colors hover:text-foreground"
                                >
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M4.98 3.5a2.5 2.5 0 11.02 5 2.5 2.5 0 01-.02-5zM3 8.98h4v12H3v-12zm7 0h3.83v1.64h.05c.53-.99 1.83-2.04 3.77-2.04 4.03 0 4.78 2.66 4.78 6.11v6.29h-4v-5.58c0-1.33-.02-3.04-1.85-3.04-1.86 0-2.14 1.45-2.14 2.94v5.68h-4v-12z" />
                                    </svg>
                                </a>
                                <a
                                    href="#"
                                    aria-label="Facebook"
                                    className="text-muted-foreground transition-colors hover:text-foreground"
                                >
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M13.5 22v-8.5H16l.4-3.3h-2.9V8.2c0-1 .3-1.6 1.7-1.6h1.8V3.6C16.6 3.4 15.6 3.3 14.4 3.3c-2.6 0-4.4 1.6-4.4 4.5v2.4H7.5v3.3H10V22h3.5z" />
                                    </svg>
                                </a>
                            </div>
                        </div>
                    </div>
                </footer>
            </div>
        </>
    );
}