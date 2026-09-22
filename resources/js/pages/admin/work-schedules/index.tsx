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
import { cn } from '@/lib/utils';
import { index, store } from '@/routes/admin/work-schedules';
import type { AvailabilityDay, WorkScheduleDay, WorkScheduleVersion } from '@/types';

type ScheduleUser = {
    id: number;
    name: string;
    email: string;
};

type Props = {
    users: ScheduleUser[];
    selectedUserId: number | null;
    versions: WorkScheduleVersion[];
    availability: AvailabilityDay[];
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
    user_id: number;
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

function UserList({
    users,
    selectedUserId,
}: {
    users: ScheduleUser[];
    selectedUserId: number | null;
}) {
    const [query, setQuery] = useState('');
    const needle = query.trim().toLowerCase();
    const filtered = needle
        ? users.filter((user) =>
              `${user.name} ${user.email}`.toLowerCase().includes(needle),
          )
        : users;

    return (
        <Card>
            <CardHeader>
                <CardTitle>Users</CardTitle>
            </CardHeader>
            <CardContent className="space-y-3">
                <Input
                    value={query}
                    onChange={(event) => setQuery(event.target.value)}
                    placeholder="Search by name or email"
                    data-test="work-schedule-user-search"
                />
                <ul className="max-h-[32rem] space-y-1 overflow-y-auto">
                    {filtered.map((user) => (
                        <li key={user.id}>
                            <button
                                type="button"
                                data-test="work-schedule-user-row"
                                onClick={() =>
                                    router.get(
                                        index(),
                                        { user: user.id },
                                        { preserveScroll: true },
                                    )
                                }
                                className={cn(
                                    'hover:bg-muted w-full rounded-md px-3 py-2 text-left text-sm',
                                    user.id === selectedUserId &&
                                        'bg-muted font-medium',
                                )}
                            >
                                <span className="block">{user.name}</span>
                                <span className="text-muted-foreground block text-xs">
                                    {user.email}
                                </span>
                            </button>
                        </li>
                    ))}
                    {filtered.length === 0 ? (
                        <li className="text-muted-foreground py-6 text-center text-sm">
                            No matching users.
                        </li>
                    ) : null}
                </ul>
            </CardContent>
        </Card>
    );
}

function ScheduleEditor({
    userId,
    versions,
    availability,
}: {
    userId: number;
    versions: WorkScheduleVersion[];
    availability: AvailabilityDay[];
}) {
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
        user_id: userId,
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
                router.reload({ only: ['versions', 'availability'] });
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

            <Card>
                <CardHeader>
                    <CardTitle>Availability (next 14 days)</CardTitle>
                </CardHeader>
                <CardContent>
                    <ul className="grid gap-1 sm:grid-cols-2">
                        {availability.map((day) => (
                            <li
                                key={day.date}
                                data-test="availability-day-row"
                                className="text-muted-foreground flex justify-between rounded-lg border px-3 py-2 text-sm"
                            >
                                <span>{day.date}</span>
                                <span data-test="availability-day-free">
                                    {day.available_hours}h free
                                    {day.occupied_hours > 0
                                        ? ` · ${day.occupied_hours}h booked`
                                        : ''}
                                    {day.unavailable_hours > 0 ? ' · off' : ''}
                                </span>
                            </li>
                        ))}
                    </ul>
                </CardContent>
            </Card>
        </div>
    );
}

export default function AdminWorkSchedulesIndex({
    users,
    selectedUserId,
    versions,
    availability,
}: Props) {
    const selected =
        users.find((user) => user.id === selectedUserId) ?? null;

    return (
        <>
            <Head title="Work schedules" />

            <div className="flex flex-col gap-6">
                <Heading
                    title="Work schedules"
                    description="Set each person's recurring weekly capacity — the baseline every availability and booking calculation is measured against."
                />

                <div className="grid items-start gap-6 lg:grid-cols-[18rem_1fr]">
                    <UserList
                        users={users}
                        selectedUserId={selectedUserId}
                    />

                    {selected ? (
                        <ScheduleEditor
                            key={selected.id}
                            userId={selected.id}
                            versions={versions}
                            availability={availability}
                        />
                    ) : (
                        <Card>
                            <CardContent className="text-muted-foreground py-12 text-center text-sm">
                                Choose a user to view and edit their weekly
                                schedule.
                            </CardContent>
                        </Card>
                    )}
                </div>
            </div>
        </>
    );
}
