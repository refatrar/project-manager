import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';
import type { TeamCapacityMember } from '@/types';

type Props = {
    members: TeamCapacityMember[];
};

const DAY_LABELS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

const LEGEND_STEPS: { bucket: 1 | 2 | 3 | 4 | 5 | 6 | 7; label: string }[] = [
    { bucket: 1, label: '≤15%' },
    { bucket: 2, label: '≤30%' },
    { bucket: 3, label: '≤45%' },
    { bucket: 4, label: '≤60%' },
    { bucket: 5, label: '≤75%' },
    { bucket: 6, label: '≤90%' },
    { bucket: 7, label: '>90%' },
];

const BUCKET_CLASS: Record<number, string> = {
    1: 'bg-heatmap-1',
    2: 'bg-heatmap-2',
    3: 'bg-heatmap-3',
    4: 'bg-heatmap-4',
    5: 'bg-heatmap-5',
    6: 'bg-heatmap-6',
    7: 'bg-heatmap-7',
};

const HATCH_STYLE = {
    backgroundImage:
        'repeating-linear-gradient(45deg, var(--muted), var(--muted) 3px, var(--border) 3px, var(--border) 4px)',
};

type Bucket = 1 | 2 | 3 | 4 | 5 | 6 | 7 | 'zero' | 'off';

function dayLabel(date: string): string {
    const weekday = new Date(`${date}T00:00:00`).getDay();

    return DAY_LABELS[(weekday + 6) % 7];
}

/**
 * Buckets occupancy into the validated 7-step sequential ramp (see the
 * `--heatmap-1..7` tokens in app.css). `zero` (a working day, nobody
 * scheduled) and `off` (not a working day at all) are deliberately kept
 * out of the color ramp and rendered with distinct, non-color treatments
 * — a 0%-busy day and a day that was never bookable are different facts
 * and must stay tellable apart without relying on hue.
 */
function bucketOf(occupiedHours: number, capacityHours: number): Bucket {
    if (capacityHours <= 0) {
        return 'off';
    }

    const percent = (occupiedHours / capacityHours) * 100;

    if (percent <= 0) return 'zero';
    if (percent <= 15) return 1;
    if (percent <= 30) return 2;
    if (percent <= 45) return 3;
    if (percent <= 60) return 4;
    if (percent <= 75) return 5;
    if (percent <= 90) return 6;

    return 7;
}

function Swatch({ bucket, className }: { bucket: Bucket; className?: string }) {
    if (bucket === 'off') {
        return (
            <div
                className={cn('border-border rounded-md border', className)}
                style={HATCH_STYLE}
            />
        );
    }

    if (bucket === 'zero') {
        return (
            <div
                className={cn(
                    'border-border bg-muted/40 rounded-md border',
                    className,
                )}
            />
        );
    }

    return (
        <div className={cn('rounded-md', BUCKET_CLASS[bucket], className)} />
    );
}

function Cell({
    date,
    capacityHours,
    occupiedHours,
    availableHours,
}: {
    date: string;
    capacityHours: number;
    occupiedHours: number;
    availableHours: number;
}) {
    const bucket = bucketOf(occupiedHours, capacityHours);
    const percent =
        capacityHours > 0
            ? Math.round((occupiedHours / capacityHours) * 100)
            : null;

    const description =
        bucket === 'off'
            ? `${date}: non-working day`
            : `${date}: ${occupiedHours}h of ${capacityHours}h occupied (${percent}%) · ${availableHours}h available`;

    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <div
                    role="img"
                    aria-label={description}
                    className="inline-flex p-px"
                    data-test="team-capacity-cell"
                >
                    <Swatch bucket={bucket} className="size-7" />
                </div>
            </TooltipTrigger>
            <TooltipContent>{description}</TooltipContent>
        </Tooltip>
    );
}

export default function TeamCapacityHeatmap({ members }: Props) {
    // Columns come from the members' own day data (already correct,
    // server-computed date strings) rather than re-deriving them from
    // `from`/`to` on the client — see the Date/toISOString timezone trap
    // fixed in the parent page's `shiftDate`.
    const dates = members[0]?.days.map((day) => day.date) ?? [];
    const secondWeekStart = 7;

    return (
        <TooltipProvider delayDuration={150}>
            <div className="space-y-4">
                <table
                    className="border-collapse text-sm"
                    data-test="team-capacity-grid"
                >
                    <thead>
                        <tr className="border-b text-left">
                            <th className="py-2 pr-4 font-medium">Member</th>
                            {dates.map((date, index) => (
                                <th
                                    key={date}
                                    className={cn(
                                        'w-9 py-2 text-center text-xs font-medium',
                                        index === secondWeekStart &&
                                            'border-l pl-2',
                                    )}
                                >
                                    {dayLabel(date)}
                                    <br />
                                    <span className="text-muted-foreground font-normal">
                                        {date.slice(8)}
                                    </span>
                                </th>
                            ))}
                            <th className="w-28 py-2 pl-4 text-right font-medium">
                                2-week total
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {members.map((member) => {
                            const totalOccupied = member.days.reduce(
                                (sum, day) => sum + day.occupied_hours,
                                0,
                            );
                            const totalCapacity = member.days.reduce(
                                (sum, day) => sum + day.capacity_hours,
                                0,
                            );

                            return (
                                <tr
                                    key={member.id}
                                    data-test="team-capacity-row"
                                    className="border-b last:border-0"
                                >
                                    <td className="py-1 pr-4 font-medium whitespace-nowrap">
                                        {member.name}
                                    </td>
                                    {member.days.map((day, index) => (
                                        <td
                                            key={day.date}
                                            className={cn(
                                                'text-center',
                                                index === secondWeekStart &&
                                                    'border-l',
                                            )}
                                        >
                                            <Cell
                                                date={day.date}
                                                capacityHours={
                                                    day.capacity_hours
                                                }
                                                occupiedHours={
                                                    day.occupied_hours
                                                }
                                                availableHours={
                                                    day.available_hours
                                                }
                                            />
                                        </td>
                                    ))}
                                    <td
                                        className="text-muted-foreground py-1 pl-4 text-right text-xs tabular-nums"
                                        data-test="team-capacity-total"
                                    >
                                        {totalOccupied}h / {totalCapacity}h
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>

                <div
                    className="flex flex-wrap items-center gap-3 text-xs"
                    data-test="team-capacity-legend"
                >
                    <span className="text-muted-foreground">Occupied:</span>
                    <span className="flex items-center gap-1">
                        <Swatch bucket="zero" className="size-4" />
                        <span className="text-muted-foreground">0%</span>
                    </span>
                    {LEGEND_STEPS.map((step) => (
                        <span
                            key={step.bucket}
                            className="flex items-center gap-1"
                        >
                            <div
                                className={cn(
                                    'size-4 rounded',
                                    BUCKET_CLASS[step.bucket],
                                )}
                            />
                            <span className="text-muted-foreground">
                                {step.label}
                            </span>
                        </span>
                    ))}
                    <span className="ml-2 flex items-center gap-1">
                        <Swatch bucket="off" className="size-4" />
                        <span className="text-muted-foreground">
                            Non-working day
                        </span>
                    </span>
                </div>
            </div>
        </TooltipProvider>
    );
}
