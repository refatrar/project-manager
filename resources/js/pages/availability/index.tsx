import { Head, router, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index as availabilityIndex } from '@/routes/availability';
import type { AvailableUser } from '@/types';

type Props = {
    filters: {
        from?: string;
        to?: string;
        hours_per_day?: string;
    };
    results: AvailableUser[] | null;
};

export default function AvailabilityIndex({ filters, results }: Props) {
    const teamSlug = usePage().props.currentTeam?.slug;
    const [from, setFrom] = useState(filters.from ?? '');
    const [to, setTo] = useState(filters.to ?? '');
    const [hoursPerDay, setHoursPerDay] = useState(filters.hours_per_day ?? '');

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (!teamSlug) {
            return;
        }

        router.get(
            availabilityIndex(teamSlug),
            { from, to, hours_per_day: hoursPerDay },
            { preserveState: true, preserveScroll: true, only: ['filters', 'results'] },
        );
    };

    return (
        <>
            <Head title="Find Available People" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Find Available People"
                    description="Who has enough free capacity for a given number of hours per day, over a date range."
                />

                <Card>
                    <CardHeader>
                        <CardTitle>Search</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="flex flex-wrap items-end gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="availability-from">From</Label>
                                <Input
                                    id="availability-from"
                                    type="date"
                                    value={from}
                                    onChange={(event) => setFrom(event.target.value)}
                                    data-test="availability-from"
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="availability-to">To</Label>
                                <Input
                                    id="availability-to"
                                    type="date"
                                    value={to}
                                    onChange={(event) => setTo(event.target.value)}
                                    data-test="availability-to"
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="availability-hours">Hours per day</Label>
                                <Input
                                    id="availability-hours"
                                    type="number"
                                    min="0.5"
                                    max="24"
                                    step="0.5"
                                    className="w-32"
                                    value={hoursPerDay}
                                    onChange={(event) => setHoursPerDay(event.target.value)}
                                    data-test="availability-hours"
                                />
                            </div>
                            <Button type="submit" data-test="availability-search">
                                Search
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                {results !== null ? (
                    <Card>
                        <CardHeader>
                            <CardTitle>Available</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {results.length > 0 ? (
                                <ul className="space-y-2">
                                    {results.map((available) => (
                                        <li
                                            key={available.id}
                                            data-test="availability-result-row"
                                            className="rounded-lg border p-3 text-sm"
                                        >
                                            <span className="font-medium">{available.name}</span>
                                            <span className="text-muted-foreground"> · {available.email}</span>
                                        </li>
                                    ))}
                                </ul>
                            ) : (
                                <p className="text-muted-foreground py-4 text-center text-sm">
                                    Nobody has enough free capacity for that window.
                                </p>
                            )}
                        </CardContent>
                    </Card>
                ) : null}
            </div>
        </>
    );
}

AvailabilityIndex.layout = (props: { currentTeam?: { slug: string } | null }) => ({
    breadcrumbs: [
        {
            title: 'Find Available People',
            href: props.currentTeam ? availabilityIndex(props.currentTeam.slug) : '/',
        },
    ],
});
