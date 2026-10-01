import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { statusMeta, toneFill, toneText } from '@/lib/status';
import { cn } from '@/lib/utils';
import type { PortfolioHealthCounts } from '@/types';

type Props = {
    counts: PortfolioHealthCounts;
};

const SEGMENTS = [
    { key: 'on_track' as const, label: 'On track' },
    { key: 'at_risk' as const, label: 'At risk' },
    { key: 'off_track' as const, label: 'Off track' },
];

export default function PortfolioHealthBar({ counts }: Props) {
    const total = counts.on_track + counts.at_risk + counts.off_track;

    return (
        <div className="space-y-4" data-test="portfolio-health-bar">
            {total > 0 ? (
                <div
                    className="flex h-2.5 w-full gap-0.5 overflow-hidden rounded-full"
                    role="img"
                    aria-label={SEGMENTS.map(
                        (segment) =>
                            `${counts[segment.key]} ${segment.label.toLowerCase()}`,
                    ).join(', ')}
                >
                    {SEGMENTS.map((segment) => {
                        const count = counts[segment.key];

                        if (count === 0) {
                            return null;
                        }

                        const percentage = Math.round((count / total) * 100);
                        const { tone } = statusMeta('health', segment.key);

                        return (
                            <Tooltip key={segment.key}>
                                <TooltipTrigger asChild>
                                    <div
                                        className={cn(
                                            'h-full first:rounded-l-full last:rounded-r-full',
                                            toneFill[tone],
                                        )}
                                        style={{
                                            width: `${(count / total) * 100}%`,
                                        }}
                                        data-test={`portfolio-health-segment-${segment.key}`}
                                    />
                                </TooltipTrigger>
                                <TooltipContent>
                                    {count} {segment.label.toLowerCase()} (
                                    {percentage}%)
                                </TooltipContent>
                            </Tooltip>
                        );
                    })}
                </div>
            ) : (
                <div className="bg-muted h-2.5 w-full rounded-full" />
            )}

            <dl className="grid grid-cols-3 gap-2">
                {SEGMENTS.map((segment) => {
                    const { tone, icon: Icon } = statusMeta(
                        'health',
                        segment.key,
                    );

                    return (
                        <div
                            key={segment.key}
                            className="bg-background/60 rounded-md border px-3 py-2"
                        >
                            <dt className="text-muted-foreground flex items-center gap-1.5 text-xs font-medium">
                                <Icon
                                    className={cn('size-3.5', toneText[tone])}
                                    aria-hidden="true"
                                />
                                {segment.label}
                            </dt>
                            <dd className="mt-0.5 text-lg font-semibold tabular-nums">
                                {counts[segment.key]}
                            </dd>
                        </div>
                    );
                })}
            </dl>
        </div>
    );
}
