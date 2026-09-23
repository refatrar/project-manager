import { Form, Head } from '@inertiajs/react';
import { useRef } from 'react';
import ProfileController from '@/actions/App/Http/Controllers/Admin/ProfileController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Props = {
    profile: {
        name: string;
        email: string;
        roles: string[];
    };
    passwordRules: string;
};

export default function EditAdminProfile({ profile, passwordRules }: Props) {
    const passwordInput = useRef<HTMLInputElement>(null);
    const currentPasswordInput = useRef<HTMLInputElement>(null);

    return (
        <>
            <Head title="Profile" />

            <div className="mx-auto flex max-w-xl flex-col gap-10">
                <Heading
                    title="Profile"
                    description="Update your name, email, and password. Roles stay as they are."
                />

                <section className="space-y-4">
                    <Heading
                        variant="small"
                        title="Account"
                        description="Your name and email are the only fields this page can change."
                    />

                    <Form
                        {...ProfileController.update.form()}
                        options={{ preserveScroll: true }}
                        className="space-y-6"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="name">Name</Label>
                                    <Input
                                        id="name"
                                        name="name"
                                        className="mt-1 block w-full"
                                        defaultValue={profile.name}
                                        required
                                        autoComplete="name"
                                        data-test="admin-profile-name"
                                    />
                                    <InputError message={errors.name} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="email">Email address</Label>
                                    <Input
                                        id="email"
                                        type="email"
                                        name="email"
                                        className="mt-1 block w-full"
                                        defaultValue={profile.email}
                                        required
                                        autoComplete="username"
                                        data-test="admin-profile-email"
                                    />
                                    <InputError message={errors.email} />
                                </div>

                                <Button
                                    disabled={processing}
                                    data-test="update-admin-profile-button"
                                >
                                    Save profile
                                </Button>
                            </>
                        )}
                    </Form>
                </section>

                <section className="space-y-4">
                    <Heading
                        variant="small"
                        title="Roles"
                        description="Assigned by someone with admin-management access. This page cannot grant or remove them."
                    />

                    {profile.roles.length > 0 ? (
                        <ul className="flex flex-wrap gap-2">
                            {profile.roles.map((role) => (
                                <li key={role}>
                                    <Badge variant="secondary">{role}</Badge>
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <p className="text-muted-foreground text-sm">
                            No roles assigned.
                        </p>
                    )}
                </section>

                <section className="space-y-4">
                    <Heading
                        variant="small"
                        title="Password"
                        description="Confirm your current password before choosing a new one."
                    />

                    <Form
                        {...ProfileController.updatePassword.form()}
                        options={{ preserveScroll: true }}
                        resetOnError={[
                            'password',
                            'password_confirmation',
                            'current_password',
                        ]}
                        resetOnSuccess
                        onError={(errors) => {
                            if (errors.password) {
                                passwordInput.current?.focus();
                            }

                            if (errors.current_password) {
                                currentPasswordInput.current?.focus();
                            }
                        }}
                        className="space-y-6"
                    >
                        {({ errors, processing }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="current_password">
                                        Current password
                                    </Label>
                                    <PasswordInput
                                        id="current_password"
                                        ref={currentPasswordInput}
                                        name="current_password"
                                        className="mt-1 block w-full"
                                        autoComplete="current-password"
                                        placeholder="Current password"
                                        data-test="admin-current-password"
                                    />
                                    <InputError
                                        message={errors.current_password}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="password">
                                        New password
                                    </Label>
                                    <PasswordInput
                                        id="password"
                                        ref={passwordInput}
                                        name="password"
                                        className="mt-1 block w-full"
                                        autoComplete="new-password"
                                        placeholder="New password"
                                        passwordrules={passwordRules}
                                        data-test="admin-new-password"
                                    />
                                    <InputError message={errors.password} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="password_confirmation">
                                        Confirm password
                                    </Label>
                                    <PasswordInput
                                        id="password_confirmation"
                                        name="password_confirmation"
                                        className="mt-1 block w-full"
                                        autoComplete="new-password"
                                        placeholder="Confirm password"
                                        data-test="admin-password-confirmation"
                                    />
                                    <InputError
                                        message={errors.password_confirmation}
                                    />
                                </div>

                                <Button
                                    disabled={processing}
                                    data-test="update-admin-password-button"
                                >
                                    Update password
                                </Button>
                            </>
                        )}
                    </Form>
                </section>
            </div>
        </>
    );
}
