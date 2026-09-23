import { Link, router, usePage } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn } from '@/lib/utils';
import { dashboard, logout } from '@/routes/admin';
import { index as adminsIndex } from '@/routes/admin/admins';
import { index as holidaysIndex } from '@/routes/admin/holidays';
import { edit as editProfile } from '@/routes/admin/profile';
import { index as teamRolesIndex } from '@/routes/admin/team-roles';
import { index as teamsIndex } from '@/routes/admin/teams';
import { index as workSchedulesIndex } from '@/routes/admin/work-schedules';
import type { NavItem } from '@/types';

const navItems: NavItem[] = [
    { title: 'Dashboard', href: dashboard() },
    { title: 'Teams', href: teamsIndex() },
    { title: 'Work schedules', href: workSchedulesIndex() },
    { title: 'Holidays', href: holidaysIndex() },
    { title: 'Team roles', href: teamRolesIndex() },
    { title: 'Admins', href: adminsIndex() },
];

export default function AdminLayout({ children }: PropsWithChildren) {
    const { isCurrentUrl } = useCurrentUrl();
    const { auth } = usePage().props;

    return (
        <div className="bg-background min-h-svh">
            <header className="flex items-center justify-between border-b px-6 py-3">
                <div className="flex items-center gap-6">
                    <Link
                        href={dashboard()}
                        className="flex items-center gap-2 font-medium"
                    >
                        <AppLogoIcon className="size-6 fill-current" />
                        Admin Panel
                    </Link>

                    <nav className="flex items-center gap-4 text-sm">
                        {navItems.map((item) => (
                            <Link
                                key={item.title}
                                href={item.href}
                                className={cn(
                                    'text-muted-foreground hover:text-foreground',
                                    isCurrentUrl(item.href) &&
                                        'text-foreground font-medium',
                                )}
                            >
                                {item.title}
                            </Link>
                        ))}
                    </nav>
                </div>

                <div className="flex items-center gap-4">
                    {auth.user ? (
                        <Link
                            href={editProfile()}
                            className="text-muted-foreground hover:text-foreground text-sm"
                            data-test="admin-profile-link"
                        >
                            {auth.user.name}
                        </Link>
                    ) : null}
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() =>
                            router.post(logout(), {}, { preserveScroll: true })
                        }
                        data-test="admin-logout"
                    >
                        Log out
                    </Button>
                </div>
            </header>

            <main className="p-6">{children}</main>
        </div>
    );
}
