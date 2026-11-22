import type { InertiaLinkProps } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';

export type BreadcrumbItem = {
    title: string;
    // The current page is the last crumb and renders as text, so it never
    // needs an href. The type used to require one, which pushed pages into
    // untyped layout objects where a `label` typo shipped an empty link.
    href?: NonNullable<InertiaLinkProps['href']>;
};

export type NavItem = {
    title: string;
    href?: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon | null;
    isActive?: boolean;
    /**
     * Path prefix that keeps the item highlighted on child pages
     * (show, create, edit) whose URLs extend the section root. Exact
     * matching alone never lights these up — and a query string on the
     * href (e.g. `/units?property=5`) defeats even the index page.
     */
    activePrefix?: string;
    /** When present, the item renders as a collapsible dropdown of sub-items. */
    children?: NavItem[];
    /** When present, the item renders as a button that runs this handler instead of navigating. */
    onClick?: () => void;
};
