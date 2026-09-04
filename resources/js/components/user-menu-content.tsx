import { Link, router } from '@inertiajs/react';
import {
    ArrowLeftRight,
    KeyRound,
    LogOut,
    Settings,
    Sparkles,
    Sun,
    User as UserIcon,
} from 'lucide-react';
import { usePropertyPicker } from '@/components/property-picker-dialog';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import { UserInfo } from '@/components/user-info';
import { useMobileNavigation } from '@/hooks/use-mobile-navigation';
import { logout } from '@/routes';
import { edit } from '@/routes/appearance';
import { edit as editProfile } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import { edit as editPersonalAi } from '@/routes/settings/personal-ai';
import type { User } from '@/types';

type Props = {
    user: User;
};

export function UserMenuContent({ user }: Props) {
    const cleanup = useMobileNavigation();
    const { open, hasAssets } = usePropertyPicker();

    const handleLogout = () => {
        cleanup();
        router.flushAll();
    };

    return (
        <>
            <DropdownMenuLabel className="p-0 font-normal">
                <div className="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
                    <UserInfo user={user} showEmail={true} />
                </div>
            </DropdownMenuLabel>
            <DropdownMenuSeparator />
            {hasAssets && (
                <DropdownMenuGroup>
                    <DropdownMenuItem
                        className="cursor-pointer"
                        onSelect={() => {
                            cleanup();
                            open();
                        }}
                    >
                        <ArrowLeftRight className="mr-2" />
                        Switch property or land
                    </DropdownMenuItem>
                </DropdownMenuGroup>
            )}
            <DropdownMenuGroup>
                <DropdownMenuItem asChild>
                    <Link
                        className="block w-full cursor-pointer"
                        href={editProfile()}
                        prefetch
                        onClick={cleanup}
                    >
                        <UserIcon className="mr-2" />
                        Profile
                    </Link>
                </DropdownMenuItem>
                <DropdownMenuItem asChild>
                    <Link
                        className="block w-full cursor-pointer"
                        href={editSecurity()}
                        prefetch
                        onClick={cleanup}
                    >
                        <Settings className="mr-2" />
                        Security
                    </Link>
                </DropdownMenuItem>
                <DropdownMenuItem asChild>
                    <Link
                        className="block w-full cursor-pointer"
                        href={edit()}
                        prefetch
                        onClick={cleanup}
                    >
                        <Sun className="mr-2" />
                        Appearance
                    </Link>
                </DropdownMenuItem>
                <DropdownMenuItem asChild>
                    <Link
                        className="block w-full cursor-pointer"
                        href={editPersonalAi()}
                        prefetch
                        onClick={cleanup}
                    >
                        <Sparkles className="mr-2" />
                        Personal AI
                    </Link>
                </DropdownMenuItem>
                <DropdownMenuItem asChild>
                    <Link
                        className="block w-full cursor-pointer"
                        href={editSecurity()}
                        prefetch
                        onClick={cleanup}
                    >
                        <KeyRound className="mr-2" />
                        Passkeys
                    </Link>
                </DropdownMenuItem>
            </DropdownMenuGroup>
            <DropdownMenuSeparator />
            <DropdownMenuItem asChild>
                <Link
                    className="block w-full cursor-pointer"
                    href={logout()}
                    as="button"
                    onClick={handleLogout}
                    data-test="logout-button"
                >
                    <LogOut className="mr-2" />
                    Log out
                </Link>
            </DropdownMenuItem>
        </>
    );
}
