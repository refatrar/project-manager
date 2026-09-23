import { Head, router, useHttp } from '@inertiajs/react';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store } from '@/routes/admin/admins';
import type { AdminAccount, RoleOption } from '@/types';

type Props = {
    admins: AdminAccount[];
    roles: RoleOption[];
};

type CreatedResponse = { message: string };

function CreateAdminForm({ roles }: { roles: RoleOption[] }) {
    const form = useHttp<
        { name: string; email: string; password: string; roles: number[] },
        CreatedResponse
    >({
        name: '',
        email: '',
        password: '',
        roles: [],
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        void form.post(store.url(), {
            onSuccess: () => {
                form.setData('name', '');
                form.setData('email', '');
                form.setData('password', '');
                form.setData('roles', []);
                router.reload({ only: ['admins'] });
            },
        });
    };

    const toggleRole = (id: number, checked: boolean) => {
        form.setData(
            'roles',
            checked
                ? [...form.data.roles, id]
                : form.data.roles.filter((existing) => existing !== id),
        );
    };

    return (
        <form onSubmit={submit} className="space-y-4">
            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="admin-name">Name</Label>
                    <Input
                        id="admin-name"
                        value={form.data.name}
                        onChange={(event) =>
                            form.setData('name', event.target.value)
                        }
                        data-test="admin-account-name"
                    />
                    {form.errors.name ? (
                        <p className="text-destructive text-sm">
                            {form.errors.name}
                        </p>
                    ) : null}
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="admin-email">Email</Label>
                    <Input
                        id="admin-email"
                        type="email"
                        value={form.data.email}
                        onChange={(event) =>
                            form.setData('email', event.target.value)
                        }
                        data-test="admin-account-email"
                    />
                    {form.errors.email ? (
                        <p className="text-destructive text-sm">
                            {form.errors.email}
                        </p>
                    ) : null}
                </div>
            </div>
            <div className="grid gap-2">
                <Label htmlFor="admin-password">Password</Label>
                <Input
                    id="admin-password"
                    type="password"
                    value={form.data.password}
                    onChange={(event) =>
                        form.setData('password', event.target.value)
                    }
                    data-test="admin-account-password"
                />
                {form.errors.password ? (
                    <p className="text-destructive text-sm">
                        {form.errors.password}
                    </p>
                ) : null}
            </div>
            <div className="grid gap-2">
                <p className="text-sm font-medium">Roles</p>
                <div className="flex flex-wrap gap-4">
                    {roles.map((role) => (
                        <div key={role.id} className="flex items-center gap-2">
                            <Checkbox
                                id={`admin-role-${role.id}`}
                                checked={form.data.roles.includes(role.id)}
                                onCheckedChange={(checked) =>
                                    toggleRole(role.id, checked === true)
                                }
                            />
                            <Label
                                htmlFor={`admin-role-${role.id}`}
                                className="text-sm font-normal"
                            >
                                {role.name}
                            </Label>
                        </div>
                    ))}
                </div>
            </div>
            <Button
                type="submit"
                disabled={form.processing}
                data-test="admin-account-create"
            >
                Create admin
            </Button>
        </form>
    );
}

export default function AdminAdminsIndex({ admins, roles }: Props) {
    return (
        <>
            <Head title="Admins" />

            <div className="flex flex-col gap-6">
                <Heading
                    title="Admins"
                    description="Manage other platform admin accounts."
                />

                <Card>
                    <CardHeader>
                        <CardTitle>New admin</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <CreateAdminForm roles={roles} />
                    </CardContent>
                </Card>

                <div className="space-y-2">
                    {admins.map((admin) => (
                        <div
                            key={admin.id}
                            data-test="admin-account-row"
                            className="rounded-lg border p-4"
                        >
                            <div className="flex flex-wrap items-center gap-2">
                                <span className="font-medium">
                                    {admin.name}
                                </span>
                                <span className="text-muted-foreground text-xs">
                                    {admin.email}
                                </span>
                            </div>
                            <div className="mt-1 flex flex-wrap gap-1">
                                {admin.roles.length > 0 ? (
                                    admin.roles.map((role) => (
                                        <Badge key={role} variant="outline">
                                            {role}
                                        </Badge>
                                    ))
                                ) : (
                                    <span className="text-muted-foreground text-xs">
                                        No roles
                                    </span>
                                )}
                            </div>
                        </div>
                    ))}
                </div>
            </div>
        </>
    );
}
