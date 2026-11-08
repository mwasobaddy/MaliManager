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
    /** When present, the item renders as a collapsible dropdown of sub-items. */
    children?: NavItem[];
    /** When present, the item renders as a button that runs this handler instead of navigating. */
    onClick?: () => void;
};
