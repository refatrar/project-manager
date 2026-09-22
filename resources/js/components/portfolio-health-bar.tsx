import { CircleCheck, CircleX, TriangleAlert } from 'lucide-react';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';
import type { PortfolioHealthCounts } from '@/types';

type Props = {
    counts: PortfolioHealthCounts;
};

const SEGMENTS = [
    {
        key: 'on_track' as const,
        label: 'On track',
        icon: CircleCheck,
        className: 'bg-status-good',
    },
    {
        key: 'at_risk' as const,
        label: 'At risk',
        icon: TriangleAlert,
        className: 'bg-status-warning',
    },
    {
        key: 'off_track' as const,
        label: 'Off track',
        icon: CircleX,
        className: 'bg-status-critical',
    },
];

export default function PortfolioHealthBar({ counts }: Props) {
    const total = counts.on_track + counts.at_risk + counts.off_track;

    return (
        <div className="space-y-3" data-test="portfolio-health-bar">
            {total > 0 ? (
                <div className="bg-muted flex h-3 w-full overflow-hidden rounded-full">
                    {SEGMENTS.map((segment) => {
                        const count = counts[segment.key];

                        if (count === 0) {
                            return null;
                        }

                        const percentage = Math.round((count / total) * 100);

                        return (
                            <Tooltip key={segment.key}>
                                <TooltipTrigger asChild>
                                    <div
                                        className={cn(
                                            'h-full border-r-2 border-white/40 first:rounded-l-full last:rounded-r-full last:border-r-0 dark:border-black/30',
                                            segment.className,
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
                <div className="bg-muted h-3 w-full rounded-full" />
            )}

            <div className="flex flex-wrap gap-4 text-sm">
                {SEGMENTS.map((segment) => (
                    <span
                        key={segment.key}
                        className="flex items-center gap-1.5"
                    >
                        <segment.icon
                            className={cn(
                                'h-4 w-4',
                                segment.key === 'on_track' &&
                                    'text-status-good',
                                segment.key === 'at_risk' &&
                                    'text-status-warning',
                                segment.key === 'off_track' &&
                                    'text-status-critical',
                            )}
                        />
                        <span className="text-muted-foreground">
                            {segment.label}
                        </span>
                        <span className="font-medium tabular-nums">
                            {counts[segment.key]}
                        </span>
                    </span>
                ))}
            </div>
        </div>
    );
}
