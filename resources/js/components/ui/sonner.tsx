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
            /*
             * §6.6: advisory toasts clear at 8s, errors persist. The per-type
             * override lives in `useFlashToast`, which is where every toast in
             * the app is raised; this is the fallback for anything raised
             * outside that path. `closeButton` means a persistent toast can
             * always be dismissed, and `richColors` is off because the palette
             * below already carries the semantic background.
             */
            duration={8000}
            closeButton
            style={
                {
                    '--normal-bg': 'var(--popover)',
                    '--normal-text': 'var(--popover-foreground)',
                    '--normal-border': 'var(--border)',
                    '--success-bg': 'color-mix(in oklab, var(--success) 16%, var(--popover))',
                    '--success-text': 'var(--status-success-fg)',
                    '--success-border': 'color-mix(in oklab, var(--success) 40%, var(--border))',
                    '--success-icon': 'var(--status-success-fg)',
                    '--error-bg': 'color-mix(in oklab, var(--destructive) 16%, var(--popover))',
                    '--error-text': 'var(--status-danger-fg)',
                    '--error-border': 'color-mix(in oklab, var(--destructive) 40%, var(--border))',
                    '--error-icon': 'var(--status-danger-fg)',
                    '--info-bg': 'color-mix(in oklab, var(--info) 16%, var(--popover))',
                    '--info-text': 'var(--status-info-fg)',
                    '--info-border': 'color-mix(in oklab, var(--info) 40%, var(--border))',
                    '--info-icon': 'var(--status-info-fg)',
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
