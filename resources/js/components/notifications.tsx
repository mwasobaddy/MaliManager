import { Bell } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';

export function Notifications() {
    return (
        <DropdownMenu>
            <DropdownMenuLabel asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className="group h-9 w-9 cursor-pointer"
                    aria-label="Notifications"
                >
                    <Bell className="!size-5 opacity-80 group-hover:opacity-100" />
                </Button>
            </DropdownMenuLabel>
            <DropdownMenuContent className="w-80" align="end">
                <DropdownMenuLabel className="font-normal">
                    <p className="text-sm font-medium">Notifications</p>
                    <p className="text-xs text-muted-foreground">
                        You're all caught up.
                    </p>
                </DropdownMenuLabel>
                <DropdownMenuSeparator />
                <p className="px-2 py-6 text-center text-sm text-muted-foreground">
                    No notifications yet.
                </p>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
