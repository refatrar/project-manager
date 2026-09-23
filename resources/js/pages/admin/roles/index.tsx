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
import {
    destroy as destroyPermission,
    store as storePermission,
    update as updatePermission,
} from '@/routes/admin/permissions';
import type { AdminRole, PermissionOption } from '@/types';

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
                                {permission.label ?? permission.name}
                            </Label>
                        </div>
                    ))}
                </div>
            ))}
        </div>
    );
}

function CreatePermissionForm() {
    const form = useHttp<
        { name: string; module: string; label: string },
        SavedResponse
    >({ name: '', module: '', label: '' });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        void form.post(storePermission.url(), {
            onSuccess: () => {
                form.setData('name', '');
                form.setData('module', '');
                form.setData('label', '');
                router.reload({ only: ['permissions'] });
            },
        });
    };

    return (
        <form onSubmit={submit} className="grid gap-4 sm:grid-cols-4">
            <div className="grid gap-2">
                <Label htmlFor="permission-name">Key</Label>
                <Input
                    id="permission-name"
                    placeholder="reports.export"
                    value={form.data.name}
                    onChange={(event) =>
                        form.setData('name', event.target.value)
                    }
                    data-test="admin-permission-name"
                />
                {form.errors.name ? (
                    <p className="text-destructive text-sm">
                        {form.errors.name}
                    </p>
                ) : null}
            </div>
            <div className="grid gap-2">
                <Label htmlFor="permission-module">Module</Label>
                <Input
                    id="permission-module"
                    value={form.data.module}
                    onChange={(event) =>
                        form.setData('module', event.target.value)
                    }
                    data-test="admin-permission-module"
                />
                {form.errors.module ? (
                    <p className="text-destructive text-sm">
                        {form.errors.module}
                    </p>
                ) : null}
            </div>
            <div className="grid gap-2">
                <Label htmlFor="permission-label">Label</Label>
                <Input
                    id="permission-label"
                    value={form.data.label}
                    onChange={(event) =>
                        form.setData('label', event.target.value)
                    }
                    data-test="admin-permission-label"
                />
                {form.errors.label ? (
                    <p className="text-destructive text-sm">
                        {form.errors.label}
                    </p>
                ) : null}
            </div>
            <div className="flex items-end">
                <Button
                    type="submit"
                    disabled={form.processing}
                    data-test="admin-permission-create"
                >
                    Add permission
                </Button>
            </div>
        </form>
    );
}

function PermissionRow({ permission }: { permission: PermissionOption }) {
    const [editing, setEditing] = useState(false);
    const form = useHttp<{ module: string; label: string }, SavedResponse>({
        module: permission.module ?? '',
        label: permission.label ?? '',
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        void form.patch(updatePermission.url(permission.id), {
            onSuccess: () => {
                setEditing(false);
                router.reload({ only: ['permissions'] });
            },
        });
    };

    const remove = () => {
        router.delete(destroyPermission.url(permission.id), {
            preserveScroll: true,
        });
    };

    return (
        <div data-test="admin-permission-row" className="rounded-lg border p-3">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <span className="font-medium">
                        {permission.label ?? permission.name}
                    </span>
                    <span className="text-muted-foreground ml-2 text-xs">
                        {permission.name} · {permission.module ?? 'Other'}
                    </span>
                </div>
                {permission.is_built_in ? (
                    <Badge variant="secondary">Built in</Badge>
                ) : (
                    <div className="flex items-center gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => setEditing((value) => !value)}
                        >
                            {editing ? 'Cancel' : 'Edit'}
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={remove}
                            data-test="admin-permission-delete"
                        >
                            Delete
                        </Button>
                    </div>
                )}
            </div>

            {editing ? (
                <form
                    onSubmit={submit}
                    className="mt-3 grid gap-3 border-t pt-3 sm:grid-cols-3"
                >
                    <div className="grid gap-2">
                        <Label htmlFor={`permission-${permission.id}-module`}>
                            Module
                        </Label>
                        <Input
                            id={`permission-${permission.id}-module`}
                            value={form.data.module}
                            onChange={(event) =>
                                form.setData('module', event.target.value)
                            }
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor={`permission-${permission.id}-label`}>
                            Label
                        </Label>
                        <Input
                            id={`permission-${permission.id}-label`}
                            value={form.data.label}
                            onChange={(event) =>
                                form.setData('label', event.target.value)
                            }
                        />
                    </div>
                    <div className="flex items-end">
                        <Button
                            type="submit"
                            size="sm"
                            disabled={form.processing}
                            data-test="admin-permission-save"
                        >
                            Save
                        </Button>
                    </div>
                </form>
            ) : null}
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
                        <CardTitle>Permissions</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <CreatePermissionForm />
                        <div className="space-y-2">
                            {permissions.map((permission) => (
                                <PermissionRow
                                    key={permission.id}
                                    permission={permission}
                                />
                            ))}
                        </div>
                    </CardContent>
                </Card>

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
