import { usePage } from '@inertiajs/react';

import AppLogoIcon from '@/components/app-logo-icon';

export default function AppLogo() {
    const { name } = usePage().props;

    return (
        <>
            <div className="flex aspect-square size-10 p-1 items-center justify-center rounded-full border-2 border-[#C37750] text-[#C37750] shadow-sm">
                <AppLogoIcon className="size-20" />
            </div>
            <div className="grid flex-1 text-left text-sm">
                <span className="truncate leading-tight font-medium text-[#C37750] text-xl">
                    {name}
                </span>
            </div>
        </>
    );
}
