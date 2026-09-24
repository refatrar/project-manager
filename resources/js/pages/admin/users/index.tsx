import { Head, Link, router, useHttp } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { toast } from 'sonner';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { destroy, store, update } from '@/routes/admin/users';
import {
    destroy as removeFromTeam,
    store as assignToTeam,
} from '@/routes/admin/users/teams';
import type { Paginated } from '@/types';

type TeamOption = {
    id: number;
    name: string;
};

type RoleOption = {
    value: string;
    label: string;
};

type UserTeam = {
    id: number;
    name: string;
    slug: string;
    role: string;
    role_label: string;
};

type TeamUser = {
    id: number;
    name: string;
    email: string;
    created_at: string | null;
    teams: UserTeam[];
};

type Props = {
    users: Paginated<TeamUser>;
    teams: TeamOption[];
    roles: RoleOption[];
};

type SavedResponse = { message: string };

function CreateUserForm() {
    const form = useHttp<
        { name: string; email: string; password: string },
        SavedResponse
    >({
        name: '',
        email: '',
        password: '',
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        void form.post(store.url(), {
            onSuccess: () => {
                form.setData('name', '');
                form.setData('email', '');
                form.setData('password', '');
                router.reload({ only: ['users'] });
            },
        });
    };

    return (
        <form onSubmit={submit} className="grid w-full min-w-0 gap-4 *:min-w-0 sm:grid-cols-2">
            <div className="grid gap-2">
                <Label htmlFor="user-name">Name</Label>
                <Input
                    id="user-name"
                    value={form.data.name}
                    onChange={(event) =>
                        form.setData('name', event.target.value)
                    }
                    data-test="admin-user-name"
                />
                <InputError message={form.errors.name} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor="user-email">Email</Label>
                <Input
                    id="user-email"
                    type="email"
                    value={form.data.email}
                    onChange={(event) =>
                        form.setData('email', event.target.value)
                    }
                    data-test="admin-user-email"
                />
                <InputError message={form.errors.email} />
            </div>
            <div className="grid gap-2 sm:col-span-2">
                <Label htmlFor="user-password">Password</Label>
                <Input
                    id="user-password"
                    type="password"
                    value={form.data.password}
                    onChange={(event) =>
                        form.setData('password', event.target.value)
                    }
                    data-test="admin-user-password"
                />
                <InputError message={form.errors.password} />
            </div>
            <div>
                <Button
                    type="submit"
                    disabled={form.processing}
                    data-test="admin-user-create"
                >
                    Create user
                </Button>
            </div>
        </form>
    );
}

function EditUserForm({
    user,
    onSaved,
}: {
    user: TeamUser;
    onSaved: () => void;
}) {
    const form = useHttp<
        { name: string; email: string; password: string },
        SavedResponse
    >({
        name: user.name,
        email: user.email,
        password: '',
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        void form.patch(update.url(user.id), {
            onSuccess: (response) => {
                toast.success(response.message);
                onSaved();
                router.reload({ only: ['users'] });
            },
        });
    };

    return (
        <form onSubmit={submit} className="grid w-full min-w-0 gap-3 *:min-w-0 sm:grid-cols-2">
            <div className="grid gap-1">
                <Label htmlFor={`user-${user.id}-name`}>Name</Label>
                <Input
                    id={`user-${user.id}-name`}
                    value={form.data.name}
                    onChange={(event) =>
                        form.setData('name', event.target.value)
                    }
                    data-test="admin-user-edit-name"
                />
                <InputError message={form.errors.name} />
            </div>
            <div className="grid gap-1">
                <Label htmlFor={`user-${user.id}-email`}>Email</Label>
                <Input
                    id={`user-${user.id}-email`}
                    type="email"
                    value={form.data.email}
                    onChange={(event) =>
                        form.setData('email', event.target.value)
                    }
                />
                <InputError message={form.errors.email} />
            </div>
            <div className="grid gap-1 sm:col-span-2">
                <Label htmlFor={`user-${user.id}-password`}>
                    New password
                </Label>
                <Input
                    id={`user-${user.id}-password`}
                    type="password"
                    value={form.data.password}
                    placeholder="Leave blank to keep the current password"
                    onChange={(event) =>
                        form.setData('password', event.target.value)
                    }
                />
                <InputError message={form.errors.password} />
            </div>
            <div>
                <Button
                    type="submit"
                    size="sm"
                    disabled={form.processing}
                    data-test="admin-user-save"
                >
                    Save
                </Button>
            </div>
        </form>
    );
}

function AssignTeamForm({
    user,
    teams,
    roles,
}: {
    user: TeamUser;
    teams: TeamOption[];
    roles: RoleOption[];
}) {
    // Start from the user's existing membership, so the picker shows the
    // role they actually hold rather than the first option (Team Lead).
    const roleOn = (teamId: string): string | undefined =>
        user.teams.find((team) => String(team.id) === teamId)?.role;
    const fallbackRole =
        roles.find((role) => role.value === 'member')?.value ??
        roles[0]?.value ??
        'member';
    const initialTeamId = String(user.teams[0]?.id ?? teams[0]?.id ?? '');

    const form = useHttp<{ team_id: string; role: string }, SavedResponse>({
        team_id: initialTeamId,
        role: roleOn(initialTeamId) ?? fallbackRole,
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.transform((data) => ({
            team_id: Number(data.team_id),
            role: data.role,
        }));
        void form.post(assignToTeam.url(user.id), {
            onSuccess: (response) => {
                toast.success(response.message);
                router.reload({ only: ['users'] });
            },
        });
    };

    if (teams.length === 0) {
        return (
            <p className="text-muted-foreground text-sm">
                Create a team before assigning this person.
            </p>
        );
    }

    return (
        <form onSubmit={submit} className="flex flex-wrap items-end gap-2">
            <div className="grid gap-1">
                <Label htmlFor={`assign-team-${user.id}`}>Team</Label>
                <Select
                    value={form.data.team_id}
                    onValueChange={(value) => {
                        form.setData('team_id', value);
                        form.setData('role', roleOn(value) ?? fallbackRole);
                    }}
                >
                    <SelectTrigger
                        id={`assign-team-${user.id}`}
                        className="w-48"
                        data-test="admin-user-team"
                    >
                        <SelectValue placeholder="Team" />
                    </SelectTrigger>
                    <SelectContent>
                        {teams.map((team) => (
                            <SelectItem key={team.id} value={String(team.id)}>
                                {team.name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <InputError message={form.errors.team_id} />
            </div>
            <div className="grid gap-1">
                <Label htmlFor={`assign-role-${user.id}`}>Role</Label>
                <Select
                    value={form.data.role}
                    onValueChange={(value) => form.setData('role', value)}
                >
                    <SelectTrigger
                        id={`assign-role-${user.id}`}
                        className="w-40"
                        data-test="admin-user-role"
                    >
                        <SelectValue placeholder="Role" />
                    </SelectTrigger>
                    <SelectContent>
                        {roles.map((role) => (
                            <SelectItem key={role.value} value={role.value}>
                                {role.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <InputError message={form.errors.role} />
            </div>
            <Button
                type="submit"
                size="sm"
                disabled={form.processing}
                data-test="admin-user-assign"
            >
                Assign
            </Button>
        </form>
    );
}

function UserRow({
    user,
    teams,
    roles,
}: {
    user: TeamUser;
    teams: TeamOption[];
    roles: RoleOption[];
}) {
    const [editing, setEditing] = useState(false);
    const removeForm = useHttp<Record<string, never>, SavedResponse>({});

    const removeTeam = (team: UserTeam) => {
        void removeForm.delete(removeFromTeam.url([user.id, team.slug]), {
            onSuccess: () => router.reload({ only: ['users'] }),
        });
    };

    const removeUser = () => {
        if (!window.confirm(`Delete ${user.name}?`)) {
            return;
        }

        router.delete(destroy.url(user.id), { preserveScroll: true });
    };

    return (
        <div data-test="admin-user-row" className="rounded-lg border p-4">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <div className="flex flex-wrap items-center gap-2">
                        <span className="font-medium">{user.name}</span>
                        <span className="text-muted-foreground text-xs">
                            {user.email}
                        </span>
                    </div>
                    <p className="text-muted-foreground mt-1 text-xs">
                        {user.created_at
                            ? `Registered ${user.created_at}`
                            : 'Registered'}
                    </p>
                </div>
                <div className="flex items-center gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() => setEditing((value) => !value)}
                    >
                        {editing ? 'Cancel' : 'Edit'}
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={removeUser}
                        data-test="admin-user-delete"
                    >
                        Delete
                    </Button>
                </div>
            </div>

            {editing ? (
                <div className="mt-4">
                    <EditUserForm
                        user={user}
                        onSaved={() => setEditing(false)}
                    />
                </div>
            ) : null}

            <div className="mt-4 space-y-3">
                <div className="flex flex-wrap gap-2">
                    {user.teams.length > 0 ? (
                        user.teams.map((team) => (
                            <div
                                key={team.id}
                                className="flex items-center gap-2"
                            >
                                <Badge variant="outline">
                                    {team.name} · {team.role_label}
                                </Badge>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => removeTeam(team)}
                                    data-test="admin-user-unassign"
                                >
                                    Remove
                                </Button>
                            </div>
                        ))
                    ) : (
                        <p className="text-muted-foreground text-sm">
                            Not on a team yet.
                        </p>
                    )}
                </div>
                <AssignTeamForm user={user} teams={teams} roles={roles} />
            </div>
        </div>
    );
}

export default function AdminUsersIndex({ users, teams, roles }: Props) {
    return (
        <>
            <Head title="Users" />

            <div className="flex flex-col gap-6">
                <Heading
                    title="Users"
                    description="Everyone who registers is listed here. Create accounts, update them, and assign people to teams."
                />

                <Card>
                    <CardHeader>
                        <CardTitle>New user</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <CreateUserForm />
                    </CardContent>
                </Card>

                <div className="space-y-3">
                    {users.data.map((user) => (
                        <UserRow
                            key={user.id}
                            user={user}
                            teams={teams}
                            roles={roles}
                        />
                    ))}
                    {users.data.length === 0 ? (
                        <p className="text-muted-foreground py-8 text-center text-sm">
                            No users yet.
                        </p>
                    ) : null}
                </div>

                {users.last_page > 1 ? (
                    <div className="flex flex-wrap items-center justify-center gap-2">
                        {users.links.map((link, linkIndex) =>
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
