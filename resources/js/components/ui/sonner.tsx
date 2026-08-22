import { useFlashToast } from '@/hooks/use-flash-toast';
import { useAppearance } from '@/hooks/use-appearance';
import { Toaster as Sonner, type ToasterProps } from 'sonner';

function Toaster({ ...props }: ToasterProps) {
    const { appearance } = useAppearance();

    useFlashToast();

    return (
        <Sonner
            theme={appearance}
            className="toaster group"
            position="bottom-right"
            style={
                {
                    '--normal-bg': 'var(--popover)',
                    '--normal-text': 'var(--popover-foreground)',
                    '--normal-border': 'var(--border)',
                    '--success-bg': 'color-mix(in oklab, var(--success) 16%, var(--popover))',
                    '--success-text': 'var(--success)',
                    '--success-border': 'color-mix(in oklab, var(--success) 40%, var(--border))',
                    '--success-icon': 'var(--success)',
                    '--error-bg': 'color-mix(in oklab, var(--destructive) 16%, var(--popover))',
                    '--error-text': 'var(--destructive)',
                    '--error-border': 'color-mix(in oklab, var(--destructive) 40%, var(--border))',
                    '--error-icon': 'var(--destructive)',
                    '--info-bg': 'color-mix(in oklab, var(--info) 16%, var(--popover))',
                    '--info-text': 'var(--info)',
                    '--info-border': 'color-mix(in oklab, var(--info) 40%, var(--border))',
                    '--info-icon': 'var(--info)',
                    '--warning-bg': 'color-mix(in oklab, var(--warning) 18%, var(--popover))',
                    '--warning-text': 'var(--warning-foreground)',
                    '--warning-border': 'color-mix(in oklab, var(--warning) 45%, var(--border))',
                    '--warning-icon': 'var(--warning)',
                } as React.CSSProperties
            }
            {...props}
        />
    );
}

export { Toaster };
