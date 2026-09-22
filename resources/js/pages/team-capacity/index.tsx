import { Head, router, usePage } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import Heading from '@/components/heading';
import TeamCapacityHeatmap from '@/components/team-capacity/heatmap';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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

export default function TeamCapacityIndex({ from, to, members }: Props) {
    const teamSlug = usePage().props.currentTeam?.slug;

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
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <Heading
                        title="Team Capacity"
                        description="Two-week occupancy across the team — who has room, who's stretched thin."
                    />

                    <div className="flex items-center gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => goToRange(shiftDate(from, -14))}
                            data-test="team-capacity-prev"
                        >
                            <ChevronLeft className="h-4 w-4" /> Previous
                        </Button>
                        <span
                            className="text-sm font-medium"
                            data-test="team-capacity-range"
                        >
                            {from} – {to}
                        </span>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => goToRange(shiftDate(from, 14))}
                            data-test="team-capacity-next"
                        >
                            Next <ChevronRight className="h-4 w-4" />
                        </Button>
                    </div>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Occupancy heatmap</CardTitle>
                    </CardHeader>
                    <CardContent className="overflow-x-auto">
                        {members.length > 0 ? (
                            <TeamCapacityHeatmap members={members} />
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
