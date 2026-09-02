import { Link, usePage } from '@inertiajs/react';
import {
    BarChart3,
    BookOpen,
    Building2,
    CreditCard,
    FileText,
    FolderGit2,
    History,
    LayoutGrid,
    ArrowLeftRight,
    Map,
    ReceiptText,
    ScrollText,
    Sparkles,
    Users,
    UserRound,
    Wand2,
    Wrench,
    ClipboardCheck,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import type { NavGroup } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { usePropertyPicker } from '@/components/property-picker-dialog';
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
import { index as platformExpensesIndex } from '@/routes/platform/expenses';
import { index as plansIndex } from '@/routes/platform/plans';
import { index as rolesIndex } from '@/routes/platform/roles';
import { index as subscriptionPaymentsIndex } from '@/routes/platform/subscription-payments';
import { rentals as searcherRentals } from '@/routes/searcher';
import { index as maintenanceRoute } from '@/routes/searcher/maintenance';
import agreementTemplates, {
    orgIndex as agreementTemplatesIndex,
} from '@/routes/tenant/agreement-templates';
import { edit as aiSettingsEdit } from '@/routes/tenant/ai-settings';
import { index as auditIndex } from '@/routes/tenant/audit';
import { page as draftingPageIndex } from '@/routes/tenant/drafting';
import { index as expensesIndex } from '@/routes/tenant/expenses';
import { index as inspectionsIndex } from '@/routes/tenant/inspections';
import { index as landParcelsIndex } from '@/routes/tenant/land-parcels';
import { index as leasesIndex } from '@/routes/tenant/leases';
import { index as maintenanceIndex } from '@/routes/tenant/maintenance';
import { index as occupantsIndex } from '@/routes/tenant/occupants';
import {
    dashboard as propertyDashboard,
    index as propertiesIndex,
} from '@/routes/tenant/properties';
import { index as reportsNavIndex } from '@/routes/tenant/reports';
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
    const canManageStaff =
        context?.permissions?.includes('staff.manage') ?? false;
    const canManageOccupants =
        context?.permissions?.includes('occupant.manage') ?? false;
    const canManageLandParcels =
        context?.permissions?.includes('land_parcel.manage') ?? false;
    const canManageProperties =
        context?.permissions?.includes('property.manage') ?? false;
    const canManageInspections =
        context?.permissions?.includes('inspection.manage') ?? false;
    const canViewLeases =
        context?.permissions?.includes('lease.manage') ?? false;
    const canManageTemplates =
        context?.permissions?.includes('lease.manage_templates') ?? false;
    const canManageExpenses =
        context?.permissions?.includes('expense.manage') ?? false;
    const isOwner =
        (context as { is_owner?: boolean } | undefined)?.is_owner ?? false;
    const canManageMaintenance =
        context?.permissions?.includes('maintenance.manage') ?? false;
    const canViewReports =
        (context as { is_owner?: boolean } | undefined)?.is_owner ||
        [
            'expense.manage',
            'maintenance.manage',
            'lease.manage',
            'occupant.manage',
        ].some((key) => (context?.permissions ?? []).includes(key));
    const canViewAudit = context?.permissions?.includes('audit.view') ?? false;
    const canRaiseMaintenance =
        usePage().props.auth?.canRaiseMaintenance ?? false;
    const aiEnabled = usePage().props.auth?.ai_enabled ?? false;
    const authPermissions = usePage().props.auth?.permissions ?? [];
    const canManageRoles = authPermissions.includes('manage roles');
    const canViewAnyAudit = authPermissions.includes('view audit');
    const canManageUsers = authPermissions.includes('user.manage');
    const canManageOrganizations = authPermissions.includes(
        'organization.manage',
    );
    const canManagePlans = authPermissions.includes('plan.manage');
    const canManageSubscriptionPayments = authPermissions.includes(
        'subscription.manage',
    );
    const canManagePlatformExpenses = authPermissions.includes(
        'platform-expense.manage',
    );
    const canAccessAdminDashboard = authPermissions.includes(
        'access admin dashboard',
    );
    const { open, hasAssets } = usePropertyPicker();

    const item = (
        title: string,
        href: NavItem['href'],
        icon: NavItem['icon'],
        onClick?: () => void,
    ): NavItem => ({
        title,
        href,
        icon,
        onClick,
    });

    // Opens the cross-organization property picker so an admin can jump to a
    // different property or land parcel. Only shown when they actually have
    // properties to switch between.
    const switchPropertyItem = (): NavItem | null =>
        hasAssets
            ? {
                  title: 'Switch property or land',
                  icon: ArrowLeftRight,
                  onClick: open,
              }
            : null;

    const dropdown = (
        title: string,
        icon: NavItem['icon'],
        children: NavItem[],
    ): NavItem => ({
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
                                      ? [
                                            item(
                                                'Occupants',
                                                occupantsIndex(slug),
                                                UserRound,
                                            ),
                                        ]
                                      : []),
                                  ...(canViewLeases
                                      ? [
                                            item(
                                                'Leases',
                                                leasesIndex({ property: slug }),
                                                ScrollText,
                                            ),
                                        ]
                                      : []),
                                  ...(canManageInspections
                                      ? [
                                            item(
                                                'Inspections',
                                                inspectionsIndex(slug),
                                                ClipboardCheck,
                                            ),
                                        ]
                                      : []),
                              ]),
                          ]
                        : []),
                    ...(canManageLandParcels
                        ? [item('Land parcels', landParcelsIndex(), Map)]
                        : []),
                ],
            },
            {
                label: 'Organization',
                items: [
                    ...(switchPropertyItem() ? [switchPropertyItem()!] : []),
                    ...(canManageStaff
                        ? [
                              dropdown('Team', Users, [
                                  item('Staff', staffIndex(), Users),
                              ]),
                          ]
                        : []),
                    ...(isOwner
                        ? [item('AI settings', aiSettingsEdit(), Sparkles)]
                        : []),
                    ...(canManageTemplates
                        ? [
                              item(
                                  'Agreement templates',
                                  agreementTemplates.propertyIndex({
                                      property: slug,
                                  }),
                                  FileText,
                              ),
                          ]
                        : []),
                    ...(canViewAudit
                        ? [item('Audit log', auditIndex(), History)]
                        : []),
                    ...(canViewAnyAudit
                        ? [
                              item(
                                  'Platform audit',
                                  platformAuditIndex(),
                                  History,
                              ),
                          ]
                        : []),
                    ...(aiEnabled
                        ? [item('AI drafting', draftingPageIndex(), Wand2)]
                        : []),
                    ...(canViewReports
                        ? [item('Reports', reportsNavIndex(), BarChart3)]
                        : []),
                ],
            },
        ];
        navGroups = groups;
    } else if (organization) {
        const groups: NavGroup[] = [
            {
                label: 'Assets',
                items: [
                    ...(switchPropertyItem() ? [switchPropertyItem()!] : []),
                    ...(canManageProperties
                        ? [
                              item(
                                  'Manage properties',
                                  propertiesIndex(),
                                  ArrowLeftRight,
                              ),
                          ]
                        : []),
                    ...(canManageLandParcels
                        ? [item('Land parcels', landParcelsIndex(), Map)]
                        : []),
                ],
            },
            {
                label: 'Operations',
                items: [
                    ...(canManageExpenses
                        ? [item('Expenses', expensesIndex(), ReceiptText)]
                        : []),
                    ...(canManageMaintenance
                        ? [
                              dropdown('Maintenance', Wrench, [
                                  item(
                                      'All requests',
                                      maintenanceIndex(),
                                      Wrench,
                                  ),
                              ]),
                          ]
                        : []),
                    ...(canViewReports
                        ? [item('Reports', reportsNavIndex(), BarChart3)]
                        : []),
                    ...(aiEnabled
                        ? [item('AI drafting', draftingPageIndex(), Wand2)]
                        : []),
                ],
            },
            {
                label: 'Administration',
                items: [
                    ...(canManageStaff
                        ? [item('Staff', staffIndex(), Users)]
                        : []),
                    ...(canManageTemplates
                        ? [
                              item(
                                  'Agreement templates',
                                  agreementTemplatesIndex(),
                                  FileText,
                              ),
                          ]
                        : []),
                    ...(canViewAudit
                        ? [item('Audit log', auditIndex(), History)]
                        : []),
                    ...(canViewAnyAudit
                        ? [
                              item(
                                  'Platform audit',
                                  platformAuditIndex(),
                                  History,
                              ),
                          ]
                        : []),
                ],
            },
        ];
        navGroups = groups;
    } else {
        const adminChildren: NavItem[] = [
            ...(canManageUsers ? [item('Users', usersIndex(), Users)] : []),
            ...(canManageOrganizations
                ? [item('Organizations', organizationsIndex(), Building2)]
                : []),
            ...(canManagePlans
                ? [item('Plans', plansIndex(), CreditCard)]
                : []),
            ...(canManageSubscriptionPayments
                ? [
                      item(
                          'Subscription payments',
                          subscriptionPaymentsIndex(),
                          ReceiptText,
                      ),
                  ]
                : []),
            ...(canManagePlatformExpenses
                ? [item('Platform expenses', platformExpensesIndex(), Wrench)]
                : []),
            ...(canManageRoles ? [item('Roles', rolesIndex(), UserRound)] : []),
            ...(canViewAnyAudit
                ? [item('Platform audit', platformAuditIndex(), History)]
                : []),
        ];

        navGroups = [
            {
                label: 'My account',
                items: [
                    item('Dashboard', centralDashboard(), LayoutGrid),
                    ...(switchPropertyItem() && canAccessAdminDashboard
                        ? [switchPropertyItem()!]
                        : []),
                    item('My rentals', searcherRentals(), Building2),
                    ...(canRaiseMaintenance
                        ? [item('Maintenance', maintenanceRoute(), Wrench)]
                        : []),
                ],
            },
            ...(adminChildren.length > 0
                ? [
                      {
                          label: 'Platform',
                          items: [
                              dropdown(
                                  'Administration',
                                  FolderGit2,
                                  adminChildren,
                              ),
                          ],
                      },
                  ]
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
