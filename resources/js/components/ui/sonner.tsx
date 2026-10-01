import { CircleAlert, CircleCheck, Info, TriangleAlert } from 'lucide-react';
import { Toaster as Sonner, type ToasterProps } from 'sonner';
import { useAppearance } from '@/hooks/use-appearance';
import { useFlashToast } from '@/hooks/use-flash-toast';
import { useIsMobile } from '@/hooks/use-mobile';

function Toaster({ ...props }: ToasterProps) {
    const { appearance } = useAppearance();
    const isMobile = useIsMobile();

    useFlashToast();

    return (
        <Sonner
            theme={appearance}
            className="toaster group"
            position={isMobile ? 'bottom-center' : 'bottom-right'}
            icons={{
                success: <CircleCheck className="text-success size-4" />,
                error: <CircleAlert className="text-destructive size-4" />,
                warning: <TriangleAlert className="text-warning size-4" />,
                info: <Info className="text-info size-4" />,
            }}
            toastOptions={{
                classNames: {
                    toast: 'font-sans !rounded-lg !shadow-lg !gap-2.5',
                    title: '!font-medium',
                    description: '!text-muted-foreground',
                },
            }}
            style={
                {
                    '--normal-bg': 'var(--popover)',
                    '--normal-text': 'var(--popover-foreground)',
                    '--normal-border': 'var(--border)',
                } as React.CSSProperties
            }
            {...props}
        />
    );
}

export { Toaster };
