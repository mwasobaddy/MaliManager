import { Link, usePage } from '@inertiajs/react';
import { BookOpen, Building2, FolderGit2, LayoutGrid, ArrowLeftRight, Users, UserRound } from 'lucide-react';
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
    const { tenant } = usePage().props;
    const property = tenant?.property;
    const organization = tenant?.organization;
    const canManageStaff = tenant?.permissions?.includes('staff.manage') ?? false;
    const canManageOccupants = tenant?.permissions?.includes('occupant.manage') ?? false;

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
          ]
        : organization
          ? [
                {
                    title: 'Properties',
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
            ]
          : [
                {
                    title: 'Dashboard',
                    href: centralDashboard(),
                    icon: LayoutGrid,
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