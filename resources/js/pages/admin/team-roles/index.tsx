import { Head, router, useHttp } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { destroy, store, update } from '@/routes/admin/team-roles';

type PermissionOption = {
    id: number;
    name: string;
    label: string | null;
    module: string | null;
};

type TeamRoleRow = {
    id: number;
    name: string;
    slug: string | null;
    description: string | null;
    is_system: boolean;
    accounts_count: number;
    permission_ids: number[];
};

type Props = {
    roles: TeamRoleRow[];
    permissions: PermissionOption[];
};

type SavedResponse = { message: string };

function groupPermissions(
    permissions: PermissionOption[],
): Map<string, PermissionOption[]> {
    const groups = new Map<string, PermissionOption[]>();
    for (const permission of permissions) {
        const module = permission.module ?? 'Other';
        const list = groups.get(module) ?? [];
        list.push(permission);
        groups.set(module, list);
    }
    return groups;
}

function PermissionChecklist({
    permissions,
    selected,
    onChange,
    idPrefix,
}: {
    permissions: PermissionOption[];
    selected: number[];
    onChange: (ids: number[]) => void;
    idPrefix: string;
}) {
    const toggle = (id: number, checked: boolean) => {
        onChange(
            checked
                ? [...selected, id]
                : selected.filter((existing) => existing !== id),
        );
    };

    return (
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            {Array.from(groupPermissions(permissions)).map(([group, items]) => (
                <div key={group} className="space-y-2 rounded-lg border p-3">
                    <p className="text-sm font-medium">{group}</p>
                    {items.map((permission) => (
                        <div
                            key={permission.id}
                            className="flex items-start gap-2"
                        >
                            <Checkbox
                                id={`${idPrefix}-${permission.id}`}
                                checked={selected.includes(permission.id)}
                                onCheckedChange={(checked) =>
                                    toggle(permission.id, checked === true)
                                }
                            />
                            <Label
                                htmlFor={`${idPrefix}-${permission.id}`}
                                className="text-sm leading-snug font-normal"
                            >
                                {permission.label ?? permission.name}
                            </Label>
                        </div>
                    ))}
                </div>
            ))}
        </div>
    );
}

function CreateRoleForm({ permissions }: { permissions: PermissionOption[] }) {
    const form = useHttp<
        { name: string; description: string; permissions: number[] },
        SavedResponse
    >({
        name: '',
        description: '',
        permissions: [],
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        void form.post(store.url(), {
            onSuccess: () => {
                form.setData('name', '');
                form.setData('description', '');
                form.setData('permissions', []);
                router.reload({ only: ['roles'] });
            },
        });
    };

    return (
        <form onSubmit={submit} className="space-y-4">
            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="role-name">Role name</Label>
                    <Input
                        id="role-name"
                        value={form.data.name}
                        onChange={(event) =>
                            form.setData('name', event.target.value)
                        }
                        data-test="admin-team-role-name"
                    />
                    {form.errors.name ? (
                        <p className="text-destructive text-sm">
                            {form.errors.name}
                        </p>
                    ) : null}
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="role-description">Description</Label>
                    <Input
                        id="role-description"
                        value={form.data.description}
                        onChange={(event) =>
                            form.setData('description', event.target.value)
                        }
                    />
                </div>
            </div>
            <PermissionChecklist
                permissions={permissions}
                selected={form.data.permissions}
                onChange={(ids) => form.setData('permissions', ids)}
                idPrefix="create"
            />
            <Button
                type="submit"
                disabled={form.processing}
                data-test="admin-team-role-create"
            >
                Create role
            </Button>
        </form>
    );
}

function RoleCard({
    role,
    permissions,
}: {
    role: TeamRoleRow;
    permissions: PermissionOption[];
}) {
    const [editing, setEditing] = useState(false);
    const form = useHttp<
        { name: string; description: string; permissions: number[] },
        SavedResponse
    >({
        name: role.name,
        description: role.description ?? '',
        permissions: role.permission_ids,
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        void form.patch(update.url(role.id), {
            onSuccess: () => {
                setEditing(false);
                router.reload({ only: ['roles'] });
            },
        });
    };

    const remove = () => {
        router.delete(destroy.url(role.id), {
            preserveScroll: true,
        });
    };

    return (
        <Card data-test="admin-team-role-row">
            <CardHeader className="flex flex-row items-start justify-between gap-4 space-y-0">
                <div>
                    <CardTitle className="text-base">{role.name}</CardTitle>
                    <p className="text-muted-foreground mt-1 text-sm">
                        {role.description}
                    </p>
                    <p className="text-muted-foreground mt-2 text-xs">
                        {role.accounts_count}{' '}
                        {role.accounts_count === 1
                            ? 'team account'
                            : 'team accounts'}
                    </p>
                </div>
                <div className="flex items-center gap-2">
                    {role.is_system ? (
                        <Badge variant="secondary">Built in</Badge>
                    ) : null}
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => setEditing((value) => !value)}
                    >
                        {editing ? 'Cancel' : 'Edit'}
                    </Button>
                    {role.is_system ? null : (
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={remove}
                            data-test="admin-team-role-delete"
                        >
                            Delete
                        </Button>
                    )}
                </div>
            </CardHeader>
            {editing ? (
                <CardContent>
                    <form onSubmit={submit} className="space-y-4">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor={`role-${role.id}-name`}>
                                    Name
                                </Label>
                                <Input
                                    id={`role-${role.id}-name`}
                                    value={form.data.name}
                                    onChange={(event) =>
                                        form.setData('name', event.target.value)
                                    }
                                    data-test="admin-team-role-edit-name"
                                />
                                {form.errors.name ? (
                                    <p className="text-destructive text-sm">
                                        {form.errors.name}
                                    </p>
                                ) : null}
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor={`role-${role.id}-description`}>
                                    Description
                                </Label>
                                <Input
                                    id={`role-${role.id}-description`}
                                    value={form.data.description}
                                    onChange={(event) =>
                                        form.setData(
                                            'description',
                                            event.target.value,
                                        )
                                    }
                                />
                            </div>
                        </div>
                        <PermissionChecklist
                            permissions={permissions}
                            selected={form.data.permissions}
                            onChange={(ids) =>
                                form.setData('permissions', ids)
                            }
                            idPrefix={`role-${role.id}`}
                        />
                        <Button
                            type="submit"
                            size="sm"
                            disabled={form.processing}
                            data-test="admin-team-role-save"
                        >
                            Save
                        </Button>
                    </form>
                </CardContent>
            ) : null}
        </Card>
    );
}

export default function AdminTeamRolesIndex({ roles, permissions }: Props) {
    return (
        <>
            <Head title="Team roles" />

            <div className="flex flex-col gap-6">
                <Heading
                    title="Team roles"
                    description="Assign what each role can do in the team panel. Every team account with that role follows these permissions."
                />

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">New role</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <CreateRoleForm permissions={permissions} />
                    </CardContent>
                </Card>

                <div className="space-y-4">
                    {roles.map((role) => (
                        <RoleCard
                            key={role.id}
                            role={role}
                            permissions={permissions}
                        />
                    ))}
                </div>
            </div>
        </>
    );
}
