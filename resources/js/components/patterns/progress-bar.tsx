import type { Tone } from '@/lib/status';
import { toneFill } from '@/lib/status';
import { cn } from '@/lib/utils';

type Props = {
    value: number;
    label: string;
    tone?: Tone | 'primary';
    className?: string;
};

export function ProgressBar({
    value,
    label,
    tone = 'primary',
    className,
}: Props) {
    const clamped = Math.max(0, Math.min(100, value));

    return (
        <div
            role="meter"
            aria-label={label}
            aria-valuenow={clamped}
            aria-valuemin={0}
            aria-valuemax={100}
            className={cn(
                'bg-muted h-1.5 w-full overflow-hidden rounded-full',
                className,
            )}
        >
            <div
                className={cn(
                    'h-full rounded-full transition-[width] duration-(--motion-slow) ease-out',
                    tone === 'primary' ? 'bg-primary' : toneFill[tone],
                )}
                style={{ width: `${clamped}%` }}
            />
        </div>
    );
}
