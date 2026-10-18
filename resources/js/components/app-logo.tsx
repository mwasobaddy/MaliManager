import AppLogoIcon from '@/components/app-logo-icon';

export default function AppLogo() {
    return (
        <>
            <span className="flex size-9 items-center justify-center text-primary">
                <AppLogoIcon />
            </span>
            <div className="grid flex-1 text-left text-sm">
                <span className="truncate text-xl leading-tight font-medium">
                    <span className="text-brand-primary-600">Mali</span>
                    <span className="text-brand-accent">Manager</span>
                </span>
            </div>
        </>
    );
}
