import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

type Props = {
    admin: {
        name: string;
        email: string;
    };
    teamCount: number;
    leaderlessTeamCount: number;
};

export default function AdminDashboard({
    admin,
    teamCount,
    leaderlessTeamCount,
}: Props) {
    return (
        <>
            <Head title="Admin Dashboard" />

            <div className="flex flex-col gap-6">
                <Heading
                    title="Admin Dashboard"
                    description={`Signed in as ${admin.name}`}
                />

                <div className="grid gap-4 sm:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Teams</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-2xl font-semibold">
                                {teamCount}
                            </p>
                            <p className="text-muted-foreground text-sm">
                                total
                            </p>
                        </CardContent>
                    </Card>

                    <Card data-test="admin-leaderless-teams">
                        <CardHeader>
                            <CardTitle>Awaiting a leader</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-2xl font-semibold">
                                {leaderlessTeamCount}
                            </p>
                            <p className="text-muted-foreground text-sm">
                                no members yet
                            </p>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}
