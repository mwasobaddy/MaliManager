import { Link, router, usePage } from '@inertiajs/react';
import {
    BarChart3,
    Bookmark,
    Building2,
    Banknote,
    CreditCard,
    DoorOpen,
    FileText,
    FolderGit2,
    Gauge,
    History,
    LayoutGrid,
    ArrowLeftRight,
    Map,
    Plus,
    ReceiptText,
    ScrollText,
    Sparkles,
    Tags,
    Undo2,
    Users,
    UserRound,
    Wand2,
    Wrench,
    ClipboardCheck,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
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
import { home as guestHome } from '@/routes/guest';
import { index as platformAuditIndex } from '@/routes/platform/audit';
import { index as platformExpensesIndex } from '@/routes/platform/expenses';
import { index as organizationsIndex } from '@/routes/platform/organizations';
import { index as plansIndex } from '@/routes/platform/plans';
import { index as propertyTypesIndex } from '@/routes/platform/property-types';
import { index as rolesIndex } from '@/routes/platform/roles';
import { index as subscriptionPaymentsIndex } from '@/routes/platform/subscription-payments';
import { index as usersIndex } from '@/routes/platform/users';
import { rentals as searcherRentals } from '@/routes/searcher';
import { index as maintenanceRoute } from '@/routes/searcher/maintenance';
import { overview as overviewIndex } from '@/routes/tenant';
import agreementTemplates, {
    orgIndex as agreementTemplatesIndex,
} from '@/routes/tenant/agreement-templates';
import { edit as aiSettingsEdit } from '@/routes/tenant/ai-settings';
import { index as auditIndex } from '@/routes/tenant/audit';
import { show as billingShow } from '@/routes/tenant/billing';
import { index as depositRefundsIndex } from '@/routes/tenant/deposit-refunds';
import { page as draftingPageIndex } from '@/routes/tenant/drafting';
import { index as expensesIndex } from '@/routes/tenant/expenses';
import { index as inspectionsIndex } from '@/routes/tenant/inspections';
import {
    index as landParcelsIndex,
    create as landParcelCreate,
} from '@/routes/tenant/land-parcels';
import { index as leasesIndex } from '@/routes/tenant/leases';
import { index as maintenanceIndex } from '@/routes/tenant/maintenance';
import { index as meterReadingsIndex } from '@/routes/tenant/meter-readings';
import { index as occupantsIndex } from '@/routes/tenant/occupants';
import { index as paymentsIndex } from '@/routes/tenant/payments';
import {
    dashboard as propertyDashboard,
    index as propertiesIndex,
} from '@/routes/tenant/properties';
import { index as reportsNavIndex } from '@/routes/tenant/reports';
import { index as staffIndex } from '@/routes/tenant/staff';
import { index as unitsIndex } from '@/routes/tenant/units';
import type { NavItem } from '@/types';

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
    const canCreateLandParcel =
        context?.permissions?.includes('land_parcel.create') ?? false;
    const canManageProperties =
        context?.permissions?.includes('property.manage') ?? false;

    const canManageUnits =
        context?.permissions?.includes('unit.manage') ?? false;
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
    const canManagePayments =
        context?.permissions?.includes('payment.manage') ?? false;
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
    const canBecomeLandlord = usePage().props.auth?.canBecomeLandlord ?? false;

    type WorkspaceLink = {
        organization_id: number;
        organization_name: string;
        role: string;
        url: string;
    };
    type RecentSearch = {
        id: number;
        name: string;
        filters: Record<string, string | number | null>;
    };
    const workspaces =
        (usePage().props.workspaces as WorkspaceLink[] | undefined) ?? [];
    const recentSearches =
        (usePage().props.recentSearches as RecentSearch[] | undefined) ?? [];

    const runSavedSearch = (search: RecentSearch) => {
        router.get(
            guestHome.url({ query: search.filters }),
            {},
            { preserveScroll: true },
        );
    };
    const aiEnabled = usePage().props.auth?.ai_enabled ?? false;
    const authPermissions = usePage().props.auth?.permissions ?? [];
    const canManageRoles = authPermissions.includes('manage roles');
    const canManagePropertyTypes = authPermissions.includes(
        'property-type.manage',
    );
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
                    item(
                        'Units',
                        `${propertyDashboard(slug).url}#units`,
                        Building2,
                    ),
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
                        ? [
                              item('Land parcels', landParcelsIndex(), Map),
                              ...(canCreateLandParcel
                                  ? [
                                        item(
                                            'Add land parcel',
                                            landParcelCreate(),
                                            Plus,
                                        ),
                                    ]
                                  : []),
                          ]
                        : []),
                ],
            },
            {
                label: 'Operations',
                items: [
                    ...(canManageExpenses
                        ? [item('Expenses', expensesIndex(), ReceiptText)]
                        : []),
                    ...(canManagePayments
                        ? [item('Payments', paymentsIndex(), Banknote)]
                        : []),
                    ...(canManageMaintenance
                        ? [item('Maintenance', maintenanceIndex(), Wrench)]
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
                    ...(canManageUnits
                        ? [
                              item('Units', unitsIndex(), DoorOpen),
                              item(
                                  'Meter readings',
                                  meterReadingsIndex(),
                                  Gauge,
                              ),
                          ]
                        : []),
                    ...(canManageLandParcels
                        ? [
                              item('Land parcels', landParcelsIndex(), Map),
                              ...(canCreateLandParcel
                                  ? [
                                        item(
                                            'Add land parcel',
                                            landParcelCreate(),
                                            Plus,
                                        ),
                                    ]
                                  : []),
                          ]
                        : []),
                ],
            },
            {
                label: 'Operations',
                items: [
                    ...(canManageExpenses
                        ? [item('Expenses', expensesIndex(), ReceiptText)]
                        : []),
                    ...(canManagePayments
                        ? [item('Payments', paymentsIndex(), Banknote)]
                        : []),
                    ...(canViewLeases
                        ? [
                              item(
                                  'Deposit refunds',
                                  depositRefundsIndex(),
                                  Undo2,
                              ),
                          ]
                        : []),
                    ...(canManageMaintenance
                        ? [item('Maintenance', maintenanceIndex(), Wrench)]
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
                    ...(isOwner
                        ? [item('Billing', billingShow(), CreditCard)]
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
            ...(canManagePropertyTypes
                ? [item('Property types', propertyTypesIndex(), Tags)]
                : []),
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
                    // The upgrade path stays visible until they own an
                    // organization; the page header keeps the louder CTA.
                    ...(canBecomeLandlord
                        ? [item('Become a landlord', '/become-landlord', Plus)]
                        : []),
                ],
            },
            // Dynamic sections: user-created content with a cap, per the
            // sidebar pattern — workspaces to jump between orgs, recent
            // searches to re-run in one tap. Full management lives on the
            // guest home, which no longer duplicates these as cards.
            ...(workspaces.length > 0
                ? [
                      {
                          label: 'Your workspaces',
                          items: workspaces.map((workspace) =>
                              item(
                                  workspace.organization_name,
                                  workspace.url,
                                  Building2,
                              ),
                          ),
                      },
                  ]
                : []),
            ...(recentSearches.length > 0
                ? [
                      {
                          label: 'Saved searches',
                          items: recentSearches.map((search) => ({
                              title: search.name,
                              icon: Bookmark,
                              onClick: () => runSavedSearch(search),
                          })),
                      },
                  ]
                : []),
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
          ? overviewIndex()
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
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
