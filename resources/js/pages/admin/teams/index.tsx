import { Head, Link, router, useHttp } from '@inertiajs/react';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { assignLeader, removeLeader, store, update } from '@/routes/admin/teams';
import type { Paginated } from '@/types';

type AdminTeam = {
    id: number;
    name: string;
    slug: string;
    members_count: number;
    leader: { id: number; name: string; email: string } | null;
};

type Props = {
    teams: Paginated<AdminTeam>;
};

type CreatedResponse = { message: string };

function CreateTeamForm() {
    const form = useHttp<{ name: string }, CreatedResponse>({ name: '' });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        void form.post(store.url(), {
            onSuccess: () => {
                form.setData('name', '');
                router.reload({ only: ['teams'] });
            },
        });
    };

    return (
        <form onSubmit={submit} className="flex flex-wrap items-end gap-4">
            <div className="grid gap-2">
                <Label htmlFor="team-name">Team name</Label>
                <Input
                    id="team-name"
                    value={form.data.name}
                    onChange={(event) =>
                        form.setData('name', event.target.value)
                    }
                    data-test="admin-team-name"
                />
                <InputError message={form.errors.name} />
            </div>
            <Button
                type="submit"
                disabled={form.processing}
                data-test="admin-team-create"
            >
                Create team
            </Button>
        </form>
    );
}

function RenameTeamForm({ team }: { team: AdminTeam }) {
    const form = useHttp<{ name: string }, CreatedResponse>({
        name: team.name,
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        void form.patch(update.url(team.slug), {
            onSuccess: () => router.reload({ only: ['teams'] }),
        });
    };

    return (
        <form onSubmit={submit} className="flex flex-wrap items-end gap-2">
            <div className="grid gap-1">
                <Label htmlFor={`team-name-${team.id}`} className="sr-only">
                    Team name
                </Label>
                <Input
                    id={`team-name-${team.id}`}
                    value={form.data.name}
                    onChange={(event) =>
                        form.setData('name', event.target.value)
                    }
                    className="h-8 w-56 text-sm"
                    data-test="admin-team-rename"
                />
                <InputError message={form.errors.name} />
            </div>
            <Button
                type="submit"
                size="sm"
                variant="outline"
                disabled={form.processing || form.data.name === team.name}
                data-test="admin-team-rename-submit"
            >
                Save name
            </Button>
        </form>
    );
}

function RemoveLeaderButton({ team }: { team: AdminTeam }) {
    const form = useHttp<Record<string, never>, CreatedResponse>({});

    const submit = () => {
        void form.delete(removeLeader.url(team.slug), {
            onSuccess: () => router.reload({ only: ['teams'] }),
        });
    };

    return (
        <Button
            type="button"
            size="sm"
            variant="outline"
            disabled={form.processing}
            onClick={submit}
            data-test="admin-remove-leader"
        >
            Remove lead
        </Button>
    );
}

function AssignLeaderForm({ team }: { team: AdminTeam }) {
    const form = useHttp<{ email: string }, CreatedResponse>({ email: '' });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        void form.post(assignLeader.url(team.slug), {
            onSuccess: () => {
                form.setData('email', '');
                router.reload({ only: ['teams'] });
            },
        });
    };

    return (
        <form onSubmit={submit} className="flex flex-wrap items-end gap-2">
            <div className="grid gap-1">
                <Input
                    type="email"
                    placeholder="user@example.com"
                    value={form.data.email}
                    onChange={(event) =>
                        form.setData('email', event.target.value)
                    }
                    className="h-8 w-56 text-sm"
                    data-test="admin-assign-leader-email"
                />
                <InputError message={form.errors.email} />
            </div>
            <Button
                type="submit"
                size="sm"
                disabled={form.processing}
                data-test="admin-assign-leader-submit"
            >
                {team.leader ? 'Reassign' : 'Assign leader'}
            </Button>
        </form>
    );
}

export default function AdminTeamsIndex({ teams }: Props) {
    return (
        <>
            <Head title="Teams" />

            <div className="flex flex-col gap-6">
                <Heading
                    title="Teams"
                    description="Create teams, rename them, and assign or remove their leader."
                />

                <Card>
                    <CardHeader>
                        <CardTitle>New team</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <CreateTeamForm />
                    </CardContent>
                </Card>

                <div className="space-y-2">
                    {teams.data.map((team) => (
                        <div
                            key={team.id}
                            data-test="admin-team-row"
                            className="flex flex-wrap items-center justify-between gap-4 rounded-lg border p-4"
                        >
                            <div className="min-w-0">
                                <div className="flex items-center gap-2">
                                    <span className="font-medium">
                                        {team.name}
                                    </span>
                                    <span className="text-muted-foreground text-xs">
                                        {team.slug}
                                    </span>
                                    <Badge variant="outline">
                                        {team.members_count}{' '}
                                        {team.members_count === 1
                                            ? 'member'
                                            : 'members'}
                                    </Badge>
                                </div>
                                <p
                                    className="text-muted-foreground mt-1 text-sm"
                                    data-test="admin-team-leader"
                                >
                                    {team.leader
                                        ? `Led by ${team.leader.name} (${team.leader.email})`
                                        : 'No leader assigned yet'}
                                </p>
                            </div>

                            <div className="flex flex-col items-end gap-2">
                                <RenameTeamForm team={team} />
                                <div className="flex flex-wrap items-end gap-2">
                                    <AssignLeaderForm team={team} />
                                    {team.leader ? (
                                        <RemoveLeaderButton team={team} />
                                    ) : null}
                                </div>
                            </div>
                        </div>
                    ))}

                    {teams.data.length === 0 ? (
                        <p className="text-muted-foreground py-8 text-center text-sm">
                            No teams yet.
                        </p>
                    ) : null}
                </div>

                {teams.last_page > 1 ? (
                    <div className="flex flex-wrap items-center justify-center gap-2">
                        {teams.links.map((link, linkIndex) =>
                            link.url ? (
                                <Button
                                    key={`${link.label}-${linkIndex}`}
                                    variant={
                                        link.active ? 'default' : 'outline'
                                    }
                                    size="sm"
                                    asChild
                                >
                                    <Link href={link.url} preserveState>
                                        <span
                                            dangerouslySetInnerHTML={{
                                                __html: link.label,
                                            }}
                                        />
                                    </Link>
                                </Button>
                            ) : (
                                <Button
                                    key={`${link.label}-${linkIndex}`}
                                    variant="outline"
                                    size="sm"
                                    disabled
                                >
                                    <span
                                        dangerouslySetInnerHTML={{
                                            __html: link.label,
                                        }}
                                    />
                                </Button>
                            ),
                        )}
                    </div>
                ) : null}
            </div>
        </>
    );
}
