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
import { destroy, store, update } from '@/routes/admin/roles';

type PermissionOption = {
    id: number;
    key: string;
    label: string;
    group: string;
};
type AdminRole = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    is_system: boolean;
    admins_count: number;
    permission_ids: number[];
};

type Props = {
    roles: AdminRole[];
    permissions: PermissionOption[];
};

type SavedResponse = { message: string };

function groupPermissions(
    permissions: PermissionOption[],
): Map<string, PermissionOption[]> {
    const groups = new Map<string, PermissionOption[]>();
    for (const permission of permissions) {
        const list = groups.get(permission.group) ?? [];
        list.push(permission);
        groups.set(permission.group, list);
    }
    return groups;
}

function PermissionChecklist({
    permissions,
    selected,
    onChange,
    disabled,
}: {
    permissions: PermissionOption[];
    selected: number[];
    onChange: (ids: number[]) => void;
    disabled?: boolean;
}) {
    const toggle = (id: number, checked: boolean) => {
        onChange(
            checked
                ? [...selected, id]
                : selected.filter((existing) => existing !== id),
        );
    };

    return (
        <div className="grid gap-3 sm:grid-cols-2">
            {Array.from(groupPermissions(permissions)).map(([group, items]) => (
                <div key={group} className="space-y-2">
                    <p className="text-muted-foreground text-xs font-medium">
                        {group}
                    </p>
                    {items.map((permission) => (
                        <div
                            key={permission.id}
                            className="flex items-center gap-2"
                        >
                            <Checkbox
                                id={`permission-${permission.id}`}
                                checked={selected.includes(permission.id)}
                                disabled={disabled}
                                onCheckedChange={(checked) =>
                                    toggle(permission.id, checked === true)
                                }
                            />
                            <Label
                                htmlFor={`permission-${permission.id}`}
                                className="text-sm font-normal"
                            >
                                {permission.label}
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
            <div className="grid gap-2">
                <Label htmlFor="role-name">Role name</Label>
                <Input
                    id="role-name"
                    value={form.data.name}
                    onChange={(event) =>
                        form.setData('name', event.target.value)
                    }
                    data-test="admin-role-name"
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
            <PermissionChecklist
                permissions={permissions}
                selected={form.data.permissions}
                onChange={(ids) => form.setData('permissions', ids)}
            />
            <Button
                type="submit"
                disabled={form.processing}
                data-test="admin-role-create"
            >
                Create role
            </Button>
        </form>
    );
}

function RoleRow({
    role,
    permissions,
}: {
    role: AdminRole;
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
        router.delete(destroy.url(role.id), { preserveScroll: true });
    };

    return (
        <div data-test="admin-role-row" className="rounded-lg border p-4">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <div className="flex items-center gap-2">
                    <span className="font-medium">{role.name}</span>
                    {role.is_system ? (
                        <Badge variant="secondary">System</Badge>
                    ) : null}
                    <Badge variant="outline">{role.admins_count} admins</Badge>
                </div>
                <div className="flex items-center gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => setEditing((value) => !value)}
                    >
                        {editing ? 'Cancel' : 'Edit'}
                    </Button>
                    {!role.is_system ? (
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={remove}
                            data-test="admin-role-delete"
                        >
                            Delete
                        </Button>
                    ) : null}
                </div>
            </div>
            {role.description ? (
                <p className="text-muted-foreground mt-1 text-sm">
                    {role.description}
                </p>
            ) : null}

            {editing ? (
                <form
                    onSubmit={submit}
                    className="mt-4 space-y-4 border-t pt-4"
                >
                    <div className="grid gap-2">
                        <Label htmlFor={`role-${role.id}-name`}>
                            Role name
                        </Label>
                        <Input
                            id={`role-${role.id}-name`}
                            value={form.data.name}
                            onChange={(event) =>
                                form.setData('name', event.target.value)
                            }
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor={`role-${role.id}-description`}>
                            Description
                        </Label>
                        <Input
                            id={`role-${role.id}-description`}
                            value={form.data.description}
                            onChange={(event) =>
                                form.setData('description', event.target.value)
                            }
                        />
                    </div>
                    <PermissionChecklist
                        permissions={permissions}
                        selected={form.data.permissions}
                        disabled={role.is_system}
                        onChange={(ids) => form.setData('permissions', ids)}
                    />
                    {role.is_system ? (
                        <p className="text-muted-foreground text-xs">
                            This role's permissions are protected and always
                            include everything.
                        </p>
                    ) : null}
                    <Button
                        type="submit"
                        size="sm"
                        disabled={form.processing}
                        data-test="admin-role-save"
                    >
                        Save
                    </Button>
                </form>
            ) : null}
        </div>
    );
}

export default function AdminRolesIndex({ roles, permissions }: Props) {
    return (
        <>
            <Head title="Roles" />

            <div className="flex flex-col gap-6">
                <Heading
                    title="Roles"
                    description="Manage admin-panel roles and their permissions."
                />

                <Card>
                    <CardHeader>
                        <CardTitle>New role</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <CreateRoleForm permissions={permissions} />
                    </CardContent>
                </Card>

                <div className="space-y-3">
                    {roles.map((role) => (
                        <RoleRow
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
