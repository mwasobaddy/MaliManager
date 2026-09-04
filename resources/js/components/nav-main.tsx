import { Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { useState } from 'react';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    SidebarGroup,
    SidebarGroupContent,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import type { NavItem } from '@/types';

export type NavGroup = {
    label: string;
    items: NavItem[];
};

function NavItemLink({ item }: { item: NavItem }) {
    const { isCurrentUrl } = useCurrentUrl();

    if (item.onClick) {
        return (
            <SidebarMenuButton
                tooltip={{ children: item.title }}
                className="cursor-pointer"
                onClick={item.onClick}
            >
                {item.icon && <item.icon />}
                <span>{item.title}</span>
            </SidebarMenuButton>
        );
    }

    return (
        <SidebarMenuButton
            asChild
            isActive={isCurrentUrl(item.href)}
            tooltip={{ children: item.title }}
        >
            <Link href={item.href} prefetch>
                {item.icon && <item.icon />}
                <span>{item.title}</span>
            </Link>
        </SidebarMenuButton>
    );
}

/**
 * A nav item with children renders as a collapsible dropdown. The parent
 * opens automatically while any of its children is the current page.
 */
function NavDropdown({ item }: { item: NavItem }) {
    const { isCurrentOrParentUrl, isCurrentUrl } = useCurrentUrl();
    const [open, setOpen] = useState(() => isCurrentOrParentUrl(item.href));

    const childActive =
        item.children?.some((child) => isCurrentOrParentUrl(child.href)) ??
        false;

    return (
        <Collapsible
            open={open || childActive}
            onOpenChange={setOpen}
            className="group/collapsible"
        >
            <SidebarMenuItem>
                <CollapsibleTrigger asChild>
                    <SidebarMenuButton tooltip={{ children: item.title }}>
                        {item.icon && <item.icon />}
                        <span>{item.title}</span>
                        <ChevronRight className="ml-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90" />
                    </SidebarMenuButton>
                </CollapsibleTrigger>
                <CollapsibleContent>
                    <SidebarMenuSub>
                        {(item.children ?? []).map((child) => (
                            <SidebarMenuSubItem key={child.title}>
                                <SidebarMenuSubButton
                                    asChild
                                    isActive={isCurrentUrl(child.href)}
                                >
                                    <Link href={child.href} prefetch>
                                        <span>{child.title}</span>
                                    </Link>
                                </SidebarMenuSubButton>
                            </SidebarMenuSubItem>
                        ))}
                    </SidebarMenuSub>
                </CollapsibleContent>
            </SidebarMenuItem>
        </Collapsible>
    );
}

export function NavMain({ groups = [] }: { groups?: NavGroup[] }) {
    return (
        <>
            {groups.map((group) => (
                <SidebarGroup key={group.label} className="px-2 py-0">
                    <SidebarGroupLabel>{group.label}</SidebarGroupLabel>
                    <SidebarGroupContent>
                        <SidebarMenu>
                            {group.items.map((item) => (
                                <div key={item.title}>
                                    {item.children &&
                                    item.children.length > 0 ? (
                                        <NavDropdown item={item} />
                                    ) : (
                                        <SidebarMenuItem>
                                            <NavItemLink item={item} />
                                        </SidebarMenuItem>
                                    )}
                                </div>
                            ))}
                        </SidebarMenu>
                    </SidebarGroupContent>
                </SidebarGroup>
            ))}
        </>
    );
}
