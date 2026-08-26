import { Link, usePage } from '@inertiajs/react';
import { BookOpen, Building2, FileText, FolderGit2, History, LayoutGrid, ArrowLeftRight, Map, ReceiptText, ScrollText, Users, UserRound, Wrench } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import type { NavGroup } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard as centralDashboard } from '@/routes';
import { index as organizationsIndex } from '@/routes/organizations';
import { index as platformAuditIndex } from '@/routes/platform/audit';
import { rentals as searcherRentals } from '@/routes/searcher';
import { index as maintenanceRoute } from '@/routes/searcher/maintenance';
import { index as rolesIndex } from '@/routes/settings/roles';
import agreementTemplates, { orgIndex as agreementTemplatesIndex } from '@/routes/tenant/agreement-templates';
import { index as auditIndex } from '@/routes/tenant/audit';
import { index as expensesIndex } from '@/routes/tenant/expenses';
import { index as landParcelsIndex } from '@/routes/tenant/land-parcels';
import { index as leasesIndex } from '@/routes/tenant/leases';
import { index as maintenanceIndex } from '@/routes/tenant/maintenance';
import { index as occupantsIndex } from '@/routes/tenant/occupants';
import { dashboard as propertyDashboard, index as propertiesIndex } from '@/routes/tenant/properties';
import { index as staffIndex } from '@/routes/tenant/staff';
import { index as usersIndex } from '@/routes/users';
import type { NavItem } from '@/types';

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/react-starter-kit',
        icon: FolderGit2,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#react',
        icon: BookOpen,
    },
];

export function AppSidebar() {
    const { context } = usePage().props;
    const property = context?.property;
    const organization = context?.organization;
    const canManageStaff = context?.permissions?.includes('staff.manage') ?? false;
    const canManageOccupants = context?.permissions?.includes('occupant.manage') ?? false;
    const canManageLandParcels = context?.permissions?.includes('land_parcel.manage') ?? false;
    const canViewLeases = context?.permissions?.includes('lease.manage') ?? false;
    const canManageTemplates = context?.permissions?.includes('lease.manage_templates') ?? false;
    const canManageExpenses = context?.permissions?.includes('expense.manage') ?? false;
    const canManageMaintenance = context?.permissions?.includes('maintenance.manage') ?? false;
    const canViewAudit = context?.permissions?.includes('audit.view') ?? false;
    const canRaiseMaintenance = usePage().props.auth?.canRaiseMaintenance ?? false;
    const authPermissions = usePage().props.auth?.permissions ?? [];
    const canManageRoles = authPermissions.includes('manage roles');
    const canViewAnyAudit = authPermissions.includes('view audit');
    const canManageUsers = authPermissions.includes('user.manage');
    const canManageOrganizations = authPermissions.includes('organization.manage');

    const item = (title: string, href: NavItem['href'], icon: NavItem['icon']): NavItem => ({
        title,
        href,
        icon,
    });

    const dropdown = (title: string, icon: NavItem['icon'], children: NavItem[]): NavItem => ({
        title,
        href: children[0]?.href ?? '#',
        icon,
        children,
    });

    let navGroups: NavGroup[] = [];

    if (property) {
        const slug = property.slug;
        const groups: NavGroup[] = [
            {
                label: 'Property',
                items: [
                    item('Dashboard', propertyDashboard(slug), LayoutGrid),
                    ...(canManageOccupants || canViewLeases
                        ? [
                              dropdown('Tenancy', UserRound, [
                                  ...(canManageOccupants
                                      ? [item('Occupants', occupantsIndex(slug), UserRound)]
                                      : []),
                                  ...(canViewLeases
                                      ? [item('Leases', leasesIndex({ property: slug }), ScrollText)]
                                      : []),
                              ]),
                          ]
                        : []),
                    ...(canManageLandParcels ? [item('Land parcels', landParcelsIndex(), Map)] : []),
                ],
            },
            {
                label: 'Organization',
                items: [
                    item('Switch property', propertiesIndex(), ArrowLeftRight),
                    ...(canManageStaff ? [dropdown('Team', Users, [item('Staff', staffIndex(), Users)])] : []),
                    ...(canManageTemplates
                        ? [
                              item(
                                  'Agreement templates',
                                  agreementTemplates.propertyIndex({ property: slug }),
                                  FileText,
                              ),
                          ]
                        : []),
                    ...(canViewAudit ? [item('Audit log', auditIndex(), History)] : []),
                ],
            },
        ];
        navGroups = groups;
    } else if (organization) {
        const groups: NavGroup[] = [
            {
                label: 'Assets',
                items: [
                    item('Properties', propertiesIndex(), ArrowLeftRight),
                    ...(canManageLandParcels ? [item('Land parcels', landParcelsIndex(), Map)] : []),
                ],
            },
            {
                label: 'Operations',
                items: [
                    ...(canManageExpenses ? [item('Expenses', expensesIndex(), ReceiptText)] : []),
                    ...(canManageMaintenance
                        ? [dropdown('Maintenance', Wrench, [item('All requests', maintenanceIndex(), Wrench)])]
                        : []),
                ],
            },
            {
                label: 'Administration',
                items: [
                    ...(canManageStaff ? [item('Staff', staffIndex(), Users)] : []),
                    ...(canManageTemplates
                        ? [item('Agreement templates', agreementTemplatesIndex(), FileText)]
                        : []),
                    ...(canViewAudit ? [item('Audit log', auditIndex(), History)] : []),
                ],
            },
        ];
        navGroups = groups;
    } else {
        const adminChildren: NavItem[] = [
            ...(canManageUsers ? [item('Users', usersIndex(), Users)] : []),
            ...(canManageOrganizations ? [item('Organizations', organizationsIndex(), Building2)] : []),
            ...(canManageRoles ? [item('Roles', rolesIndex(), UserRound)] : []),
            ...(canViewAnyAudit ? [item('Platform audit', platformAuditIndex(), History)] : []),
        ];

        navGroups = [
            {
                label: 'My account',
                items: [
                    item('Dashboard', centralDashboard(), LayoutGrid),
                    item('My rentals', searcherRentals(), Building2),
                    ...(canRaiseMaintenance
                        ? [item('Maintenance', maintenanceRoute(), Wrench)]
                        : []),
                ],
            },
            ...(adminChildren.length > 0
                ? [{ label: 'Platform', items: [dropdown('Administration', FolderGit2, adminChildren)] }]
                : []),
        ];
    }

    const homeHref = property
        ? propertyDashboard(property.slug)
        : organization
          ? propertiesIndex()
          : centralDashboard();

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={homeHref} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                {property && (
                    <SidebarMenu>
                        <SidebarMenuItem>
                            <SidebarMenuButton asChild className="text-sm">
                                <Link href={propertyDashboard(property.slug)}>
                                    <Building2 className="size-4" />
                                    {property.name}
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                )}
                <NavMain groups={navGroups} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}