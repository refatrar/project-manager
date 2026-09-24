import { Plane, TriangleAlert } from 'lucide-react';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useInitials } from '@/hooks/use-initials';
import { cn } from '@/lib/utils';
import type { AvailabilityDay, TeamCapacityMember } from '@/types';

type Props = {
    members: TeamCapacityMember[];
    today: string;
};

const DAY_LABELS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

const LEGEND_STEPS: { bucket: Step; label: string }[] = [
    { bucket: 1, label: '≤15%' },
    { bucket: 2, label: '≤30%' },
    { bucket: 3, label: '≤45%' },
    { bucket: 4, label: '≤60%' },
    { bucket: 5, label: '≤75%' },
    { bucket: 6, label: '≤90%' },
    { bucket: 7, label: '>90%' },
];

const BUCKET_CLASS: Record<Step, string> = {
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

type Step = 1 | 2 | 3 | 4 | 5 | 6 | 7;
type Bucket = Step | 'zero' | 'off' | 'leave';

// Parsed as UTC so the weekday never shifts with the viewer's timezone.
function weekdayIndex(date: string): number {
    const [year, month, day] = date.split('-').map(Number);

    return (new Date(Date.UTC(year, month - 1, day)).getUTCDay() + 6) % 7;
}

function formatHours(hours: number): string {
    return `${Number.isInteger(hours) ? hours : hours.toFixed(1)}h`;
}

/**
 * Buckets occupancy into the validated 7-step sequential ramp (see the
 * `--heatmap-1..7` tokens in app.css). `zero` (a working day, nobody
 * scheduled), `off` (not a working day at all) and `leave` (a working day
 * fully covered by approved time off, even if someone still booked it) are kept out of the color ramp and
 * rendered with distinct, non-color treatments, so each stays tellable
 * apart without relying on hue.
 */
function bucketOf(day: AvailabilityDay): Bucket {
    if (day.capacity_hours <= 0) {
        return 'off';
    }

    if (day.unavailable_hours >= day.capacity_hours) {
        return 'leave';
    }

    const percent = (day.occupied_hours / day.capacity_hours) * 100;

    if (percent <= 0) return 'zero';
    if (percent <= 15) return 1;
    if (percent <= 30) return 2;
    if (percent <= 45) return 3;
    if (percent <= 60) return 4;
    if (percent <= 75) return 5;
    if (percent <= 90) return 6;

    return 7;
}

/** Booked beyond what the day can hold, once time off is taken out. */
function isOverbooked(day: AvailabilityDay): boolean {
    return (
        day.capacity_hours > 0 &&
        day.occupied_hours > day.capacity_hours - day.unavailable_hours
    );
}

function Swatch({
    bucket,
    overbooked = false,
    className,
}: {
    bucket: Bucket;
    overbooked?: boolean;
    className?: string;
}) {
    if (bucket === 'off') {
        return (
            <div
                className={cn('border-border rounded-md border', className)}
                style={HATCH_STYLE}
            />
        );
    }

    if (bucket === 'leave') {
        return (
            <div
                className={cn(
                    'border-border bg-muted text-muted-foreground flex items-center justify-center rounded-md border border-dashed',
                    className,
                )}
            >
                {/* Booked while on leave: the warning replaces the plane. */}
                {overbooked ? (
                    <TriangleAlert
                        className="text-destructive-foreground size-3.5"
                        aria-hidden
                    />
                ) : (
                    <Plane className="size-3.5" aria-hidden />
                )}
            </div>
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
        <div
            className={cn(
                'text-background flex items-center justify-center rounded-md',
                BUCKET_CLASS[bucket],
                className,
            )}
        >
            {overbooked ? (
                <TriangleAlert className="size-3.5" aria-hidden />
            ) : null}
        </div>
    );
}

function describe(day: AvailabilityDay, bucket: Bucket): string[] {
    if (bucket === 'off') {
        return ['Non-working day'];
    }

    const percent = Math.round((day.occupied_hours / day.capacity_hours) * 100);
    const lines = [
        `${formatHours(day.occupied_hours)} of ${formatHours(day.capacity_hours)} booked (${percent}%)`,
    ];

    if (day.unavailable_hours > 0) {
        lines.push(`${formatHours(day.unavailable_hours)} time off`);
    }

    lines.push(
        isOverbooked(day)
            ? `Over-booked by ${formatHours(day.occupied_hours - (day.capacity_hours - day.unavailable_hours))}`
            : `${formatHours(day.available_hours)} free`,
    );

    return lines;
}

function Cell({
    day,
    isWeekend,
}: {
    day: AvailabilityDay;
    isWeekend: boolean;
}) {
    const bucket = bucketOf(day);
    const overbooked = isOverbooked(day);
    const lines = describe(day, bucket);
    const dateLabel = `${DAY_LABELS[weekdayIndex(day.date)]} ${day.date}`;

    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <div
                    role="img"
                    aria-label={`${dateLabel}: ${lines.join(', ')}`}
                    className="flex justify-center p-0.5"
                    data-test="team-capacity-cell"
                >
                    <Swatch
                        bucket={bucket}
                        overbooked={overbooked}
                        className={cn('h-8', isWeekend ? 'w-5' : 'w-8')}
                    />
                </div>
            </TooltipTrigger>
            <TooltipContent>
                <p className="font-medium">{dateLabel}</p>
                {lines.map((line) => (
                    <p key={line}>{line}</p>
                ))}
            </TooltipContent>
        </Tooltip>
    );
}

function UtilisationBar({
    occupied,
    capacity,
}: {
    occupied: number;
    capacity: number;
}) {
    const percent = capacity > 0 ? Math.round((occupied / capacity) * 100) : 0;
    const over = percent > 100;

    return (
        <div className="flex min-w-36 flex-col items-end gap-1">
            <div className="flex items-center gap-1 text-xs tabular-nums">
                {over ? (
                    <TriangleAlert
                        className="text-destructive-foreground size-3"
                        aria-label="Over capacity"
                    />
                ) : null}
                <span className="font-medium">{percent}%</span>
                <span className="text-muted-foreground">
                    {formatHours(occupied)} / {formatHours(capacity)}
                </span>
            </div>
            <div className="bg-muted h-1.5 w-full overflow-hidden rounded-full">
                <div
                    className={cn(
                        'h-full rounded-full',
                        over ? 'bg-destructive-foreground' : 'bg-heatmap-4',
                    )}
                    style={{ width: `${Math.min(percent, 100)}%` }}
                />
            </div>
        </div>
    );
}

export default function TeamCapacityHeatmap({ members, today }: Props) {
    const getInitials = useInitials();

    // Columns come from the members' own day data (already correct,
    // server-computed date strings) rather than re-deriving them from
    // `from`/`to` on the client — see the Date/toISOString timezone trap
    // fixed in the parent page's `shiftDate`.
    const dates = members[0]?.days.map((day) => day.date) ?? [];
    const secondWeekStart = 7;
    const isWeekend = (date: string) => weekdayIndex(date) >= 5;

    return (
        <TooltipProvider delayDuration={150}>
            <div className="space-y-5">
                <div className="-mx-6 overflow-x-auto px-6">
                    <table
                        className="w-full border-separate border-spacing-0 text-sm"
                        data-test="team-capacity-grid"
                    >
                        <thead>
                            <tr>
                                <th className="bg-card sticky left-0 z-10 border-b py-2 pr-4 text-left text-xs font-medium text-muted-foreground">
                                    Member
                                </th>
                                {dates.map((date, index) => {
                                    const isToday = date === today;

                                    return (
                                        <th
                                            key={date}
                                            className={cn(
                                                'border-b px-0 py-2 text-center text-xs font-medium',
                                                index === secondWeekStart &&
                                                    'border-l',
                                                isWeekend(date) &&
                                                    'text-muted-foreground',
                                            )}
                                        >
                                            <span className="block">
                                                {isWeekend(date)
                                                    ? DAY_LABELS[
                                                          weekdayIndex(date)
                                                      ].charAt(0)
                                                    : DAY_LABELS[
                                                          weekdayIndex(date)
                                                      ]}
                                            </span>
                                            <span
                                                className={cn(
                                                    'mt-0.5 inline-flex size-6 items-center justify-center rounded-full font-normal tabular-nums',
                                                    isToday
                                                        ? 'bg-primary text-primary-foreground font-semibold'
                                                        : 'text-muted-foreground',
                                                )}
                                                aria-label={
                                                    isToday
                                                        ? `${date} (today)`
                                                        : undefined
                                                }
                                            >
                                                {Number(date.slice(8))}
                                            </span>
                                        </th>
                                    );
                                })}
                                <th className="border-b py-2 pl-6 text-right text-xs font-medium text-muted-foreground">
                                    2-week load
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
                                const overDays =
                                    member.days.filter(isOverbooked).length;
                                const leaveDays = member.days.filter(
                                    (day) => day.unavailable_hours > 0,
                                ).length;

                                return (
                                    <tr
                                        key={member.id}
                                        data-test="team-capacity-row"
                                        className="group"
                                    >
                                        <td className="bg-card group-hover:bg-muted/40 sticky left-0 z-10 border-b py-2 pr-4 group-last:border-b-0">
                                            <div className="flex items-center gap-2.5">
                                                <Avatar className="hidden size-7 sm:flex">
                                                    <AvatarFallback className="text-[11px] font-medium">
                                                        {getInitials(
                                                            member.name,
                                                        )}
                                                    </AvatarFallback>
                                                </Avatar>
                                                <div className="min-w-0">
                                                    <p
                                                        className="max-w-28 truncate font-medium sm:max-w-44"
                                                        title={member.name}
                                                    >
                                                        {member.name}
                                                    </p>
                                                    {overDays > 0 ||
                                                    leaveDays > 0 ? (
                                                        <p className="text-muted-foreground truncate text-xs">
                                                            {[
                                                                overDays > 0
                                                                    ? `${overDays} day${overDays === 1 ? '' : 's'} over`
                                                                    : null,
                                                                leaveDays > 0
                                                                    ? `${leaveDays} day${leaveDays === 1 ? '' : 's'} off`
                                                                    : null,
                                                            ]
                                                                .filter(Boolean)
                                                                .join(' · ')}
                                                        </p>
                                                    ) : null}
                                                </div>
                                            </div>
                                        </td>
                                        {member.days.map((day, index) => (
                                            <td
                                                key={day.date}
                                                className={cn(
                                                    'group-hover:bg-muted/40 border-b group-last:border-b-0',
                                                    index === secondWeekStart &&
                                                        'border-l',
                                                    day.date === today &&
                                                        'bg-primary/5',
                                                )}
                                            >
                                                <Cell
                                                    day={day}
                                                    isWeekend={isWeekend(
                                                        day.date,
                                                    )}
                                                />
                                            </td>
                                        ))}
                                        <td
                                            className="group-hover:bg-muted/40 border-b py-2 pl-6 group-last:border-b-0"
                                            data-test="team-capacity-total"
                                        >
                                            <UtilisationBar
                                                occupied={totalOccupied}
                                                capacity={totalCapacity}
                                            />
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>

                <div
                    className="text-muted-foreground flex flex-wrap items-center gap-x-5 gap-y-2 border-t pt-4 text-xs"
                    data-test="team-capacity-legend"
                >
                    <span className="flex items-center gap-2">
                        <span>Booked</span>
                        <Swatch bucket="zero" className="size-4" />
                        <span>0%</span>
                        <span className="flex gap-0.5">
                            {LEGEND_STEPS.map((step) => (
                                <span
                                    key={step.bucket}
                                    title={step.label}
                                    className={cn(
                                        'h-4 w-4 first:rounded-l last:rounded-r',
                                        BUCKET_CLASS[step.bucket],
                                    )}
                                />
                            ))}
                        </span>
                        <span>&gt;90%</span>
                    </span>
                    <span className="flex items-center gap-1.5">
                        <Swatch
                            bucket={7}
                            overbooked
                            className="size-4 [&_svg]:size-2.5"
                        />
                        Over-booked
                    </span>
                    <span className="flex items-center gap-1.5">
                        <Swatch
                            bucket="leave"
                            className="size-4 [&_svg]:size-2.5"
                        />
                        Time off
                    </span>
                    <span className="flex items-center gap-1.5">
                        <Swatch bucket="off" className="size-4" />
                        Non-working day
                    </span>
                </div>
            </div>
        </TooltipProvider>
    );
}
