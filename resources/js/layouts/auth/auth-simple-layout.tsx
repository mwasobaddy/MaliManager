import { Link } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    return (
        <div className="flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6 md:p-10">
            <div className="w-full max-w-sm">
                <div className="flex flex-col gap-8">
                    <div className="flex flex-col items-center gap-4">
                        <Link
                            href={home()}
                            className="flex flex-col items-center gap-2 font-medium"
                        >
                            <div className="flex items-center gap-2">
                                <div className="flex aspect-square size-10 p-1 items-center justify-center rounded-full border-2 border-[#C37750] text-[#C37750] shadow-sm">
                                    <AppLogoIcon className="size-20" />
                                </div>
                                <div className="grid flex-1 text-left text-sm">
                                    <span className="truncate leading-tight font-medium text-[#C37750] text-xl">
                                        {import.meta.env.VITE_APP_NAME ?? 'MaliManager'}
                                    </span>
                                </div>
                            </div>
                            <span className="sr-only">{title}</span>
                        </Link>

                        <div className="space-y-2 text-center">
                            <h1 className="text-xl font-medium">{title}</h1>
                            <p className="text-center text-sm text-muted-foreground">
                                {description}
                            </p>
                        </div>
                    </div>
                    {children}
                </div>
            </div>
        </div>
    );
}
