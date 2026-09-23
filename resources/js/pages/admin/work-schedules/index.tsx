import { Head, router, useHttp } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { toast } from 'sonner';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store } from '@/routes/admin/work-schedules';
import type { WorkScheduleDay, WorkScheduleVersion } from '@/types';

type Props = {
    versions: WorkScheduleVersion[];
};

type FormDay = {
    day_of_week: number;
    is_working_day: boolean;
    start_time: string;
    end_time: string;
    break_minutes: number;
    capacity_hours: number;
};

type FormData = {
    effective_from: string;
    days: FormDay[];
};

type SavedResponse = {
    versions: WorkScheduleVersion[];
    message: string;
};

const DAY_LABELS = [
    'Monday',
    'Tuesday',
    'Wednesday',
    'Thursday',
    'Friday',
    'Saturday',
    'Sunday',
];

function toFormDay(
    day: WorkScheduleDay | undefined,
    dayOfWeek: number,
): FormDay {
    if (day) {
        return {
            day_of_week: dayOfWeek,
            is_working_day: day.is_working_day,
            start_time: day.start_time?.slice(0, 5) ?? '09:00',
            end_time: day.end_time?.slice(0, 5) ?? '18:00',
            break_minutes: day.break_minutes,
            capacity_hours: day.capacity_hours,
        };
    }

    const isWorkingDay = dayOfWeek <= 5;

    return {
        day_of_week: dayOfWeek,
        is_working_day: isWorkingDay,
        start_time: '09:00',
        end_time: '18:00',
        break_minutes: isWorkingDay ? 60 : 0,
        capacity_hours: isWorkingDay ? 8 : 0,
    };
}

function todayIsoDate(): string {
    return new Date().toISOString().slice(0, 10);
}

function weeklyHours(
    days: { is_working_day: boolean; capacity_hours: number }[],
): number {
    return days.reduce(
        (total, day) => total + (day.is_working_day ? day.capacity_hours : 0),
        0,
    );
}

function ScheduleEditor({ versions }: { versions: WorkScheduleVersion[] }) {
    const current = versions.find((version) => version.is_current) ?? null;
    const history = versions.filter((version) => !version.is_current);

    const [days, setDays] = useState<FormDay[]>(() =>
        DAY_LABELS.map((_, index) =>
            toFormDay(
                current?.days.find((day) => day.day_of_week === index + 1),
                index + 1,
            ),
        ),
    );

    const form = useHttp<FormData, SavedResponse>(store(), {
        effective_from: todayIsoDate(),
        days,
    });

    const updateDay = (dayIndex: number, changes: Partial<FormDay>) => {
        const next = days.map((day, index) =>
            index === dayIndex ? { ...day, ...changes } : day,
        );
        setDays(next);
        form.setData('days', next);
    };

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        void form.submit({
            onSuccess: (response) => {
                toast.success(response.message);
                router.reload({ only: ['versions'] });
            },
        });
    };

    const dayError = Object.entries(form.errors).find(([key]) =>
        key.startsWith('days.'),
    )?.[1];
    const dayErrorMessage = Array.isArray(dayError) ? dayError[0] : dayError;

    return (
        <div className="flex flex-col gap-6">
            <Card>
                <CardHeader>
                    <CardTitle>Edit schedule</CardTitle>
                </CardHeader>
                <CardContent>
                    <form onSubmit={submit} className="space-y-6">
                        <div className="grid max-w-xs gap-2">
                            <Label htmlFor="effective-from">
                                Effective from
                            </Label>
                            <Input
                                id="effective-from"
                                type="date"
                                min={todayIsoDate()}
                                value={form.data.effective_from}
                                onChange={(event) =>
                                    form.setData(
                                        'effective_from',
                                        event.target.value,
                                    )
                                }
                                data-test="work-schedule-effective-from"
                            />
                            <InputError message={form.errors.effective_from} />
                            <InputError message={dayErrorMessage} />
                        </div>

                        <div className="space-y-3">
                            {days.map((day, dayIndex) => (
                                <div
                                    key={day.day_of_week}
                                    data-test="work-schedule-day-row"
                                    className="flex flex-wrap items-center gap-4 rounded-lg border p-3"
                                >
                                    <div className="flex w-36 shrink-0 items-center gap-2">
                                        <Checkbox
                                            checked={day.is_working_day}
                                            onCheckedChange={(checked) =>
                                                updateDay(dayIndex, {
                                                    is_working_day:
                                                        checked === true,
                                                })
                                            }
                                            data-test="work-schedule-day-toggle"
                                        />
                                        <span className="text-sm font-medium">
                                            {DAY_LABELS[dayIndex]}
                                        </span>
                                    </div>

                                    {day.is_working_day ? (
                                        <>
                                            <div className="flex items-center gap-1">
                                                <Input
                                                    type="time"
                                                    className="w-28"
                                                    value={day.start_time}
                                                    onChange={(event) =>
                                                        updateDay(dayIndex, {
                                                            start_time:
                                                                event.target
                                                                    .value,
                                                        })
                                                    }
                                                    data-test="work-schedule-start-time"
                                                />
                                                <span className="text-muted-foreground text-sm">
                                                    to
                                                </span>
                                                <Input
                                                    type="time"
                                                    className="w-28"
                                                    value={day.end_time}
                                                    onChange={(event) =>
                                                        updateDay(dayIndex, {
                                                            end_time:
                                                                event.target
                                                                    .value,
                                                        })
                                                    }
                                                    data-test="work-schedule-end-time"
                                                />
                                            </div>

                                            <div className="flex items-center gap-1">
                                                <Input
                                                    type="number"
                                                    min={0}
                                                    max={1440}
                                                    className="w-20"
                                                    value={day.break_minutes}
                                                    onChange={(event) =>
                                                        updateDay(dayIndex, {
                                                            break_minutes:
                                                                Number(
                                                                    event.target
                                                                        .value,
                                                                ),
                                                        })
                                                    }
                                                    data-test="work-schedule-break-minutes"
                                                />
                                                <span className="text-muted-foreground text-sm">
                                                    min break
                                                </span>
                                            </div>

                                            <div className="flex items-center gap-1">
                                                <Input
                                                    type="number"
                                                    min={0}
                                                    max={24}
                                                    step={0.5}
                                                    className="w-20"
                                                    value={day.capacity_hours}
                                                    onChange={(event) =>
                                                        updateDay(dayIndex, {
                                                            capacity_hours:
                                                                Number(
                                                                    event.target
                                                                        .value,
                                                                ),
                                                        })
                                                    }
                                                    data-test="work-schedule-capacity-hours"
                                                />
                                                <span className="text-muted-foreground text-sm">
                                                    h capacity
                                                </span>
                                            </div>
                                        </>
                                    ) : (
                                        <span className="text-muted-foreground text-sm">
                                            Not a working day
                                        </span>
                                    )}
                                </div>
                            ))}
                        </div>

                        <p className="text-muted-foreground text-sm">
                            {weeklyHours(days)}h total per week
                        </p>

                        <Button
                            type="submit"
                            disabled={form.processing}
                            data-test="work-schedule-save"
                        >
                            Save schedule
                        </Button>
                    </form>
                </CardContent>
            </Card>

            {history.length > 0 ? (
                <Card>
                    <CardHeader>
                        <CardTitle>History</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <ul className="space-y-2">
                            {history.map((version) => (
                                <li
                                    key={version.effective_from}
                                    data-test="work-schedule-history-row"
                                    className="text-muted-foreground flex justify-between rounded-lg border p-3 text-sm"
                                >
                                    <span>
                                        {version.effective_from}
                                        {' – '}
                                        {version.effective_until ?? 'present'}
                                    </span>
                                    <span>
                                        {weeklyHours(version.days)}h/week
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </CardContent>
                </Card>
            ) : null}
        </div>
    );
}

export default function AdminWorkSchedulesIndex({ versions }: Props) {
    return (
        <>
            <Head title="Work schedules" />

            <div className="flex flex-col gap-6">
                <Heading
                    title="Work schedules"
                    description="Set the platform's recurring weekly capacity — the single baseline every user's availability and booking calculation is measured against."
                />

                <ScheduleEditor versions={versions} />
            </div>
        </>
    );
}
