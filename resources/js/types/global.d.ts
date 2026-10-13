import type { Auth } from '@/types/auth';

declare module 'react' {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            context: {
                organization: { id: number; name: string; slug: string } | null;
                property: { id: number; name: string; slug: string } | null;
                permissions: string[];
            };
            sidebarOpen: boolean;
            centralUrl?: string;
            autoOpenPropertyPicker?: boolean;
            /**
             * Set while a platform admin is browsing as an organization owner.
             * `can_return` is false when the admin is gone or no longer an
             * admin, so the banner can hide the button rather than offering one
             * that would fail.
             */
            impersonation: {
                admin: { name: string; email: string };
                organization: string;
                started_at: string | null;
                can_return: boolean;
            } | null;
            [key: string]: unknown;
        };
    }
}
