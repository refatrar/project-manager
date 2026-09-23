import { Head, router, useHttp } from '@inertiajs/react';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store } from '@/routes/admin/admins';
import type { AdminAccount } from '@/types';

type Props = {
    admins: AdminAccount[];
};

type CreatedResponse = { message: string };

function CreateAdminForm() {
    const form = useHttp<
        { name: string; email: string; password: string },
        CreatedResponse
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
                router.reload({ only: ['admins'] });
            },
        });
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

export default function AdminAdminsIndex({ admins }: Props) {
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
                        <CreateAdminForm />
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
                        </div>
                    ))}
                </div>
            </div>
        </>
    );
}
