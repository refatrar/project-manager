import { Link, router, usePage } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn } from '@/lib/utils';
import { dashboard, logout } from '@/routes/admin';
import { index as adminsIndex } from '@/routes/admin/admins';
import { index as rolesIndex } from '@/routes/admin/roles';
import { index as teamsIndex } from '@/routes/admin/teams';
import type { NavItem } from '@/types';

const navItems: NavItem[] = [
    { title: 'Dashboard', href: dashboard() },
    { title: 'Teams', href: teamsIndex() },
    { title: 'Roles', href: rolesIndex() },
    { title: 'Admins', href: adminsIndex() },
];

export default function AdminLayout({ children }: PropsWithChildren) {
    const { isCurrentOrParentUrl } = useCurrentUrl();
    const admin = usePage<{ admin?: { name: string } }>().props.admin;

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
                                    isCurrentOrParentUrl(item.href) &&
                                        'text-foreground font-medium',
                                )}
                            >
                                {item.title}
                            </Link>
                        ))}
                    </nav>
                </div>

                <div className="flex items-center gap-4">
                    {admin ? (
                        <span className="text-muted-foreground text-sm">
                            {admin.name}
                        </span>
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
