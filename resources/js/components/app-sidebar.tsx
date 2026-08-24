import { Link, usePage } from '@inertiajs/react';
import { BookOpen, Building2, FolderGit2, History, LayoutGrid, ArrowLeftRight, Map, Users, UserRound } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
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
import { index as platformAuditIndex } from '@/routes/platform/audit';
import { rentals as searcherRentals } from '@/routes/searcher';
import { index as rolesIndex } from '@/routes/settings/roles';
import { index as auditIndex } from '@/routes/tenant/audit';
import { index as landParcelsIndex } from '@/routes/tenant/land-parcels';
import { index as occupantsIndex } from '@/routes/tenant/occupants';
import { dashboard as propertyDashboard, index as propertiesIndex } from '@/routes/tenant/properties';
import { index as staffIndex } from '@/routes/tenant/staff';
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
    const canViewAudit = context?.permissions?.includes('audit.view') ?? false;
    const authUser = (
            usePage().props.auth as unknown as { user?: { can(gate: string): boolean } | null }
        )?.user;
    const canManageRoles = authUser?.can('manageRoles') ?? false;
    const canViewAnyAudit = authUser?.can('viewAnyAudit') ?? false;

    const mainNavItems: NavItem[] = property
        ? [
              {
                  title: 'Dashboard',
                  href: propertyDashboard(property.slug),
                  icon: LayoutGrid,
              },
              ...(canManageOccupants
                   ? [
                         {
                             title: 'Occupants',
                             href: occupantsIndex(property.slug),
                             icon: UserRound,
                         },
                     ]
                   : []),
               ...(canManageLandParcels
                   ? [
                         {
                             title: 'Land parcels',
                             href: landParcelsIndex(),
                             icon: Map,
                         },
                     ]
                   : []),
               {
                   title: 'Switch property',
                   href: propertiesIndex(),
                   icon: ArrowLeftRight,
               },
                ...(canManageStaff
                    ? [
                          {
                              title: 'Staff',
                              href: staffIndex(),
                              icon: Users,
                          },
                      ]
                    : []),
                ...(canViewAudit
                    ? [
                          {
                              title: 'Audit log',
                              href: auditIndex(),
                              icon: History,
                          },
                      ]
                    : []),
           ]
           : organization
            ? [
                 {
                     title: 'Properties',
                     href: propertiesIndex(),
                     icon: ArrowLeftRight,
                 },
                 ...(canManageLandParcels
                     ? [
                           {
                               title: 'Land parcels',
                               href: landParcelsIndex(),
                               icon: Map,
                           },
                       ]
                     : []),
                  ...(canManageStaff
                        ? [
                              {
                                  title: 'Staff',
                                  href: staffIndex(),
                                  icon: Users,
                              },
                          ]
                        : []),
                  ...(canViewAudit
                        ? [
                              {
                                  title: 'Audit log',
                                  href: auditIndex(),
                                  icon: History,
                              },
                          ]
                        : []),
              ]
            : [
                 ...(canManageRoles
                     ? [
                           {
                               title: 'Roles',
                               href: rolesIndex(),
                               icon: UserRound,
                           },
                       ]
                     : []),
                 ...(canViewAnyAudit
                     ? [
                           {
                               title: 'Platform audit',
                               href: platformAuditIndex(),
                               icon: History,
                           },
                       ]
                     : []),
                  {
                      title: 'Dashboard',
                      href: centralDashboard(),
                      icon: LayoutGrid,
                  },
                  {
                      title: 'My rentals',
                      href: searcherRentals(),
                      icon: Building2,
                  },
              ];

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
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}