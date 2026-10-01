import { Badge } from '@/components/ui/badge';
import { statusMeta, toneText } from '@/lib/status';
import type { StatusKind } from '@/lib/status';
import { cn } from '@/lib/utils';

type Props = {
    kind: StatusKind;
    value: string | null | undefined;
    label: string;
    className?: string;
    'data-test'?: string;
};

/**
 * Tinted badge with an icon and the server-provided label. Used for every
 * status-like enum so the same value looks the same on every page.
 */
export function StatusBadge({
    kind,
    value,
    label,
    className,
    ...props
}: Props) {
    const { tone, icon: Icon } = statusMeta(kind, value);

    return (
        <Badge variant={tone} className={className} {...props}>
            <Icon aria-hidden="true" />
            {label}
        </Badge>
    );
}

/**
 * Priority reads as metadata, not as a status: neutral text with a tinted
 * signal icon, so a board full of "High" cards stays calm.
 */
export function PriorityIndicator({
    value,
    label,
    showLabel = true,
    className,
}: {
    value: string;
    label: string;
    showLabel?: boolean;
    className?: string;
}) {
    const { tone, icon: Icon } = statusMeta('priority', value);

    return (
        <span
            className={cn(
                'text-muted-foreground inline-flex items-center gap-1 text-xs font-medium whitespace-nowrap',
                className,
            )}
            title={showLabel ? undefined : `${label} priority`}
        >
            <Icon
                className={cn('size-3.5', toneText[tone])}
                aria-hidden="true"
            />
            {showLabel ? (
                label
            ) : (
                <span className="sr-only">{label} priority</span>
            )}
        </span>
    );
}
