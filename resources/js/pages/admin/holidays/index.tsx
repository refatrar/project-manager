import { Head, router, useHttp } from '@inertiajs/react';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { destroy, index, store } from '@/routes/admin/holidays';
import type { Holiday } from '@/types';

type Props = {
    year: number;
    holidays: Holiday[];
};

type CreatedResponse = { message: string };

function CreateHolidayForm({ year }: { year: number }) {
    const form = useHttp<{ name: string; date: string }, CreatedResponse>({
        name: '',
        date: `${year}-01-01`,
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        void form.post(store.url(), {
            onSuccess: () => {
                form.setData('name', '');
                router.reload({ only: ['holidays'] });
            },
        });
    };

    return (
        <form onSubmit={submit} className="flex flex-wrap items-end gap-4">
            <div className="grid gap-2">
                <Label htmlFor="holiday-name">Name</Label>
                <Input
                    id="holiday-name"
                    value={form.data.name}
                    onChange={(event) =>
                        form.setData('name', event.target.value)
                    }
                    data-test="holiday-name"
                />
                <InputError message={form.errors.name} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor="holiday-date">Date</Label>
                <Input
                    id="holiday-date"
                    type="date"
                    value={form.data.date}
                    onChange={(event) =>
                        form.setData('date', event.target.value)
                    }
                    data-test="holiday-date"
                />
                <InputError message={form.errors.date} />
            </div>
            <Button
                type="submit"
                disabled={form.processing}
                data-test="holiday-create"
            >
                Add holiday
            </Button>
        </form>
    );
}

function DeleteHolidayButton({ holiday }: { holiday: Holiday }) {
    const form = useHttp<Record<string, never>, CreatedResponse>({});

    const submit = () => {
        void form.delete(destroy.url(holiday.id), {
            onSuccess: () => router.reload({ only: ['holidays'] }),
        });
    };

    return (
        <Button
            type="button"
            size="sm"
            variant="outline"
            disabled={form.processing}
            onClick={submit}
            data-test="holiday-delete"
        >
            Remove
        </Button>
    );
}

export default function AdminHolidaysIndex({ year, holidays }: Props) {
    const changeYear = (nextYear: number) => {
        router.get(index(), { year: nextYear }, { preserveScroll: true });
    };

    return (
        <>
            <Head title="Holidays" />

            <div className="flex flex-col gap-6">
                <Heading
                    title="Holidays"
                    description="Specific dated public holidays. Every user's availability shows zero capacity on these dates, regardless of the weekly schedule."
                />

                <Card>
                    <CardHeader>
                        <CardTitle>New holiday</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <CreateHolidayForm year={year} />
                    </CardContent>
                </Card>

                <div className="flex items-center justify-between">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() => changeYear(year - 1)}
                        data-test="holidays-prev-year"
                    >
                        {year - 1}
                    </Button>
                    <span
                        className="text-sm font-medium"
                        data-test="holidays-year"
                    >
                        {year}
                    </span>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() => changeYear(year + 1)}
                        data-test="holidays-next-year"
                    >
                        {year + 1}
                    </Button>
                </div>

                <div className="space-y-2">
                    {holidays.map((holiday) => (
                        <div
                            key={holiday.id}
                            data-test="holiday-row"
                            className="flex items-center justify-between gap-4 rounded-lg border p-4"
                        >
                            <div>
                                <span className="font-medium">
                                    {holiday.name}
                                </span>
                                <span className="text-muted-foreground ml-2 text-sm">
                                    {holiday.date}
                                </span>
                            </div>
                            <DeleteHolidayButton holiday={holiday} />
                        </div>
                    ))}

                    {holidays.length === 0 ? (
                        <p className="text-muted-foreground py-8 text-center text-sm">
                            No holidays for {year}.
                        </p>
                    ) : null}
                </div>
            </div>
        </>
    );
}
