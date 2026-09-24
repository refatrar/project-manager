import { Head, router, usePage } from '@inertiajs/react';
import {
    CalendarClock,
    ChevronLeft,
    ChevronRight,
    Clock,
    Gauge,
    TriangleAlert,
} from 'lucide-react';
import type { ReactNode } from 'react';
import Heading from '@/components/heading';
import TeamCapacityHeatmap from '@/components/team-capacity/heatmap';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { cn } from '@/lib/utils';
import { index as teamCapacityIndex } from '@/routes/team-capacity';
import type { TeamCapacityMember } from '@/types';

type Props = {
    from: string;
    to: string;
    members: TeamCapacityMember[];
};

// Built with Date.UTC() rather than parsing the string into a local-time
// Date and reading it back with toISOString() — that round-trip silently
// shifts the date in any timezone ahead of UTC (this app runs in UTC).
function shiftDate(date: string, days: number): string {
    const [year, month, day] = date.split('-').map(Number);

    return new Date(Date.UTC(year, month - 1, day + days))
        .toISOString()
        .slice(0, 10);
}

function parseDate(date: string): Date {
    const [year, month, day] = date.split('-').map(Number);

    return new Date(Date.UTC(year, month - 1, day));
}

function formatRange(from: string, to: string): string {
    const start = parseDate(from);
    const end = parseDate(to);
    const sameYear = start.getUTCFullYear() === end.getUTCFullYear();
    const format = (date: Date, withYear: boolean) =>
        date.toLocaleDateString('en-GB', {
            day: 'numeric',
            month: 'short',
            ...(withYear ? { year: 'numeric' } : {}),
            timeZone: 'UTC',
        });

    return `${format(start, !sameYear)} – ${format(end, true)}`;
}

// The viewer's own calendar day, as a YYYY-MM-DD string.
function localToday(): string {
    const now = new Date();

    return [
        now.getFullYear(),
        String(now.getMonth() + 1).padStart(2, '0'),
        String(now.getDate()).padStart(2, '0'),
    ].join('-');
}

function formatHours(hours: number): string {
    return `${Math.round(hours).toLocaleString()}h`;
}

function StatTile({
    icon,
    label,
    value,
    detail,
    tone = 'default',
    testId,
}: {
    icon: ReactNode;
    label: string;
    value: string;
    detail: string;
    tone?: 'default' | 'critical';
    testId: string;
}) {
    return (
        <Card className="gap-2 py-4" data-test={testId}>
            <CardContent className="px-4">
                <div className="text-muted-foreground flex items-center gap-2 text-xs font-medium">
                    {icon}
                    {label}
                </div>
                <p
                    className={cn(
                        'mt-2 text-2xl font-semibold tabular-nums',
                        tone === 'critical' && 'text-destructive-foreground',
                    )}
                >
                    {value}
                </p>
                <p className="text-muted-foreground mt-0.5 text-xs">{detail}</p>
            </CardContent>
        </Card>
    );
}

export default function TeamCapacityIndex({ from, to, members }: Props) {
    const teamSlug = usePage().props.currentTeam?.slug;
    const today = localToday();
    const isCurrentRange = today >= from && today <= to;

    const days = members.flatMap((member) => member.days);
    const capacity = days.reduce((sum, day) => sum + day.capacity_hours, 0);
    const booked = days.reduce((sum, day) => sum + day.occupied_hours, 0);
    const timeOff = days.reduce((sum, day) => sum + day.unavailable_hours, 0);
    const free = days.reduce((sum, day) => sum + day.available_hours, 0);
    const utilisation =
        capacity > 0 ? Math.round((booked / capacity) * 100) : 0;
    const overbookedMembers = members.filter((member) =>
        member.days.some(
            (day) =>
                day.capacity_hours > 0 &&
                day.occupied_hours > day.capacity_hours - day.unavailable_hours,
        ),
    ).length;

    const goToRange = (nextFrom: string) => {
        if (!teamSlug) {
            return;
        }

        router.get(
            teamCapacityIndex(teamSlug),
            { from: nextFrom },
            { preserveState: true, preserveScroll: true },
        );
    };

    return (
        <>
            <Head title="Team Capacity" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-x-4 gap-y-3 [&>header]:mb-0">
                    <Heading
                        title="Team Capacity"
                        description="Two-week occupancy across the team — who has room, who's stretched thin."
                    />

                    <div className="flex w-full items-center gap-2 sm:w-auto">
                        <div className="flex flex-1 items-center rounded-md border sm:flex-none">
                            <Button
                                variant="ghost"
                                size="sm"
                                className="rounded-r-none"
                                onClick={() => goToRange(shiftDate(from, -14))}
                                data-test="team-capacity-prev"
                                aria-label="Previous two weeks"
                            >
                                <ChevronLeft className="h-4 w-4" />
                            </Button>
                            <span
                                className="flex-1 border-x px-3 text-center text-sm font-medium whitespace-nowrap tabular-nums"
                                data-test="team-capacity-range"
                            >
                                {formatRange(from, to)}
                            </span>
                            <Button
                                variant="ghost"
                                size="sm"
                                className="rounded-l-none"
                                onClick={() => goToRange(shiftDate(from, 14))}
                                data-test="team-capacity-next"
                                aria-label="Next two weeks"
                            >
                                <ChevronRight className="h-4 w-4" />
                            </Button>
                        </div>
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={isCurrentRange}
                            onClick={() => goToRange(today)}
                            data-test="team-capacity-today"
                        >
                            Today
                        </Button>
                    </div>
                </div>

                {members.length > 0 ? (
                    <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                        <StatTile
                            icon={<CalendarClock className="size-3.5" />}
                            label="Capacity"
                            value={formatHours(capacity)}
                            detail={`${members.length} member${members.length === 1 ? '' : 's'} · ${formatHours(timeOff)} time off`}
                            testId="team-capacity-stat-capacity"
                        />
                        <StatTile
                            icon={<Gauge className="size-3.5" />}
                            label="Booked"
                            value={`${utilisation}%`}
                            detail={`${formatHours(booked)} of ${formatHours(capacity)}`}
                            testId="team-capacity-stat-booked"
                        />
                        <StatTile
                            icon={<Clock className="size-3.5" />}
                            label="Free"
                            value={formatHours(free)}
                            detail="After bookings and time off"
                            testId="team-capacity-stat-free"
                        />
                        <StatTile
                            icon={<TriangleAlert className="size-3.5" />}
                            label="Over-booked"
                            value={String(overbookedMembers)}
                            detail={
                                overbookedMembers === 1
                                    ? 'Member above capacity on some day'
                                    : 'Members above capacity on some day'
                            }
                            tone={
                                overbookedMembers > 0 ? 'critical' : 'default'
                            }
                            testId="team-capacity-stat-overbooked"
                        />
                    </div>
                ) : null}

                <Card>
                    <CardHeader>
                        <CardTitle>Occupancy heatmap</CardTitle>
                        <CardDescription>
                            Hours booked per day against each member's schedule.
                            Hover a day for the details.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {members.length > 0 ? (
                            <TeamCapacityHeatmap
                                members={members}
                                today={today}
                            />
                        ) : (
                            <p className="text-muted-foreground py-8 text-center text-sm">
                                No team members yet.
                            </p>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

TeamCapacityIndex.layout = (props: {
    currentTeam?: { slug: string } | null;
}) => ({
    breadcrumbs: [
        {
            title: 'Team Capacity',
            href: props.currentTeam
                ? teamCapacityIndex(props.currentTeam.slug)
                : '/',
        },
    ],
});
