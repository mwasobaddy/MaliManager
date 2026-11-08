import { router, usePage } from '@inertiajs/react';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { dashboard } from '@/routes';
import { home as guestHome } from '@/routes/guest';

/**
 * Mode switch between browsing listings (Home) and managing them
 * (Dashboard), rendered at the top of both pages in the app's underline
 * tab style. Active state derives from the URL, so the two full-page
 * visits stay in sync with no shared state.
 *
 * Both tabs always render, even for brand-new accounts with nothing to
 * manage yet: stable chrome the user learns once beats chrome that
 * appears and disappears, and the dashboard's empty state already
 * explains what fills it in.
 */
export function HomeDashboardTabs() {
    const { url } = usePage();

    const homeUrl = guestHome.url();
    const dashboardUrl = dashboard().url;
    const onDashboard = url.startsWith(dashboardUrl);

    return (
        <Tabs
            value={onDashboard ? dashboardUrl : homeUrl}
            onValueChange={(value) => router.visit(value)}
        >
            <TabsList aria-label="Switch between Home and Dashboard">
                <TabsTrigger value={homeUrl}>Home</TabsTrigger>
                <TabsTrigger value={dashboardUrl}>Dashboard</TabsTrigger>
            </TabsList>
        </Tabs>
    );
}
