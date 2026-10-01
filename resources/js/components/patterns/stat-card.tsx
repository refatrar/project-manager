import { Link } from '@inertiajs/react';
import type { InertiaLinkProps } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import type { Tone } from '@/lib/status';
import { toneText } from '@/lib/status';
import { cn } from '@/lib/utils';

type Props = {
    label: string;
    value: ReactNode;
    hint?: ReactNode;
    icon?: LucideIcon;
    /** Colours the icon and value; use only when the number needs attention. */
    tone?: Tone;
    href?: NonNullable<InertiaLinkProps['href']>;
    className?: string;
    'data-test'?: string;
};

export function StatCard({
    label,
    value,
    hint,
    icon: Icon,
    tone = 'neutral',
    href,
    className,
    ...props
}: Props) {
    const body = (
        <>
            <div className="flex items-center justify-between gap-2">
                <p className="text-muted-foreground text-[0.8125rem] font-medium">
                    {label}
                </p>
                {Icon ? (
                    <Icon
                        className={cn('size-4', toneText[tone])}
                        aria-hidden="true"
                    />
                ) : null}
            </div>
            <p
                className={cn(
                    'text-lg leading-6 font-semibold tracking-[-0.02em] tabular-nums',
                    tone !== 'neutral' && toneText[tone],
                )}
            >
                {value}
            </p>
            {hint ? (
                <p className="text-subtle-foreground text-xs">{hint}</p>
            ) : null}
        </>
    );

    const classes = cn(
        'flex flex-col gap-0.5 py-1',
        href &&
            'hover:text-foreground focus-visible:ring-ring rounded-md transition-colors focus-visible:ring-2 focus-visible:outline-none',
        className,
    );

    return href ? (
        <Link href={href} className={classes} {...props}>
            {body}
        </Link>
    ) : (
        <div className={classes} {...props}>
            {body}
        </div>
    );
}
