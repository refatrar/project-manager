import { Head, router, useHttp, usePage } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { toast } from 'sonner';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { index as timesheetIndex, submit } from '@/routes/timesheet';
import type { TimesheetRow, TimesheetStatusCounts } from '@/types';

type Props = {
    weekStart: string;
    weekEnd: string;
    days: string[];
    rows: TimesheetRow[];
    dayTotals: Record<string, number>;
    weekTotal: number;
    statusCounts: TimesheetStatusCounts;
    canSubmit: boolean;
    hasRunningTimer: boolean;
};

type SubmittedResponse = {
    message: string;
};

const DAY_LABELS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

// Built with Date.UTC() rather than parsing the string into a local-time
// Date and reading it back with toISOString() — that round-trip silently
// shifts the date backward in any timezone ahead of UTC (this app runs in
// UTC), sending the wrong `week` to the server on every Previous/Next click.
function shiftWeek(weekStart: string, days: number): string {
    const [year, month, day] = weekStart.split('-').map(Number);

    return new Date(Date.UTC(year, month - 1, day + days))
        .toISOString()
        .slice(0, 10);
}

export default function TimesheetIndex({
    weekStart,
    weekEnd,
    days,
    rows,
    dayTotals,
    weekTotal,
    statusCounts,
    canSubmit,
    hasRunningTimer,
}: Props) {
    const teamSlug = usePage().props.currentTeam?.slug;
    const form = useHttp<{ week: string }, SubmittedResponse>({
        week: weekStart,
    });

    const goToWeek = (nextWeekStart: string) => {
        if (!teamSlug) {
            return;
        }

        router.get(
            timesheetIndex(teamSlug),
            { week: nextWeekStart },
            { preserveState: true, preserveScroll: true },
        );
    };

    const submitWeek = () => {
        if (!teamSlug) {
            return;
        }

        form.transform(() => ({ week: weekStart }));

        void form.post(submit.url(teamSlug), {
            onSuccess: (response) => {
                toast.success(response.message);
                router.reload({ only: ['rows', 'statusCounts', 'canSubmit'] });
            },
        });
    };

    return (
        <>
            <Head title="Timesheet" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <Heading
                        title="Timesheet"
                        description="Your logged time for the week, grouped by project and activity."
                    />

                    <div className="flex items-center gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => goToWeek(shiftWeek(weekStart, -7))}
                            data-test="timesheet-prev-week"
                        >
                            <ChevronLeft className="h-4 w-4" /> Previous
                        </Button>
                        <span
                            className="text-sm font-medium"
                            data-test="timesheet-week-range"
                        >
                            {weekStart} – {weekEnd}
                        </span>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => goToWeek(shiftWeek(weekStart, 7))}
                            data-test="timesheet-next-week"
                        >
                            Next <ChevronRight className="h-4 w-4" />
                        </Button>
                    </div>
                </div>

                <Card>
                    <CardHeader className="flex flex-row flex-wrap items-center justify-between gap-4">
                        <CardTitle>Week grid</CardTitle>
                        <div className="flex flex-wrap items-center gap-2">
                            {statusCounts.pending > 0 ? (
                                <Badge variant="secondary">
                                    {statusCounts.pending} pending
                                </Badge>
                            ) : null}
                            {statusCounts.submitted > 0 ? (
                                <Badge variant="outline">
                                    {statusCounts.submitted} submitted
                                </Badge>
                            ) : null}
                            {statusCounts.approved > 0 ? (
                                <Badge>{statusCounts.approved} approved</Badge>
                            ) : null}
                            {statusCounts.rejected > 0 ? (
                                <Badge variant="destructive">
                                    {statusCounts.rejected} rejected
                                </Badge>
                            ) : null}
                            <Button
                                type="button"
                                size="sm"
                                disabled={
                                    !canSubmit || form.processing || !teamSlug
                                }
                                onClick={submitWeek}
                                data-test="timesheet-submit"
                            >
                                Submit week
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent className="overflow-x-auto">
                        {hasRunningTimer ? (
                            <p className="text-muted-foreground mb-3 text-sm">
                                A running timer this week won't be included
                                until it's stopped.
                            </p>
                        ) : null}

                        {rows.length > 0 ? (
                            <table
                                className="w-full min-w-180 border-collapse text-sm"
                                data-test="timesheet-grid"
                            >
                                <thead>
                                    <tr className="border-b text-left">
                                        <th className="py-2 pr-4 font-medium">
                                            Project · Activity
                                        </th>
                                        {days.map((day, index) => (
                                            <th
                                                key={day}
                                                className="w-20 py-2 text-right font-medium"
                                            >
                                                {DAY_LABELS[index]}
                                                <br />
                                                <span className="text-muted-foreground font-normal">
                                                    {day.slice(5)}
                                                </span>
                                            </th>
                                        ))}
                                        <th className="w-20 py-2 text-right font-medium">
                                            Total
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {rows.map((row) => (
                                        <tr
                                            key={`${row.project?.id ?? 'none'}-${row.activity_type}`}
                                            data-test="timesheet-row"
                                            className="border-b"
                                        >
                                            <td className="py-2 pr-4">
                                                {row.project
                                                    ? `${row.project.code} · `
                                                    : ''}
                                                <span className="capitalize">
                                                    {row.activity_type.replace(
                                                        '_',
                                                        ' ',
                                                    )}
                                                </span>
                                            </td>
                                            {days.map((day) => (
                                                <td
                                                    key={day}
                                                    className="text-right tabular-nums"
                                                >
                                                    {row.days[day] > 0
                                                        ? row.days[day]
                                                        : '–'}
                                                </td>
                                            ))}
                                            <td className="text-right font-medium tabular-nums">
                                                {row.total}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                                <tfoot>
                                    <tr className="font-medium">
                                        <td className="pt-2 pr-4">Total</td>
                                        {days.map((day) => (
                                            <td
                                                key={day}
                                                className="pt-2 text-right tabular-nums"
                                            >
                                                {dayTotals[day] > 0
                                                    ? dayTotals[day]
                                                    : '–'}
                                            </td>
                                        ))}
                                        <td
                                            className="pt-2 text-right tabular-nums"
                                            data-test="timesheet-week-total"
                                        >
                                            {weekTotal}
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        ) : (
                            <p className="text-muted-foreground py-8 text-center text-sm">
                                Nothing logged this week.
                            </p>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

TimesheetIndex.layout = (props: { currentTeam?: { slug: string } | null }) => ({
    breadcrumbs: [
        {
            title: 'Timesheet',
            href: props.currentTeam
                ? timesheetIndex(props.currentTeam.slug)
                : '/',
        },
    ],
});
