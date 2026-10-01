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
import { index as usersIndex } from '@/routes/admin/users';
import { index as workSchedulesIndex } from '@/routes/admin/work-schedules';
import type { NavItem } from '@/types';

const navItems: NavItem[] = [
    { title: 'Dashboard', href: dashboard() },
    { title: 'Users', href: usersIndex() },
    { title: 'Teams', href: teamsIndex() },
    { title: 'Work schedules', href: workSchedulesIndex() },
    { title: 'Holidays', href: holidaysIndex() },
    { title: 'Team roles', href: teamRolesIndex() },
    { title: 'Admins', href: adminsIndex() },
];

export default function AdminLayout({ children }: PropsWithChildren) {
    const { mostSpecificCurrentIndex } = useCurrentUrl();
    const activeIndex = mostSpecificCurrentIndex(
        navItems.map((item) => item.href),
    );
    const { auth } = usePage().props;

    return (
        <div className="bg-background min-h-svh">
            <a
                href="#admin-main"
                className="bg-primary text-primary-foreground sr-only z-50 rounded-md px-3 py-2 text-sm font-medium focus:not-sr-only focus:fixed focus:top-3 focus:left-3"
            >
                Skip to main content
            </a>
            <header className="bg-card/95 sticky top-0 z-30 border-b backdrop-blur">
                <div className="mx-auto flex h-14 max-w-[1600px] items-center justify-between gap-4 px-4 md:px-6 2xl:px-8">
                    <Link
                        href={dashboard()}
                        className="focus-visible:ring-ring flex items-center gap-2.5 rounded-md font-semibold tracking-tight focus-visible:ring-2 focus-visible:outline-none"
                    >
                        <span className="bg-primary text-primary-foreground flex size-8 items-center justify-center rounded-lg">
                            <AppLogoIcon className="size-[1.125rem]" />
                        </span>
                        Admin Panel
                    </Link>

                    <div className="flex min-w-0 items-center gap-2 sm:gap-3">
                        {auth.user ? (
                            <Link
                                href={editProfile()}
                                className="text-muted-foreground hover:text-foreground focus-visible:ring-ring max-w-32 truncate rounded-md px-2 py-1 text-sm transition-colors focus-visible:ring-2 focus-visible:outline-none sm:max-w-48"
                                data-test="admin-profile-link"
                            >
                                {auth.user.name}
                            </Link>
                        ) : null}
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() =>
                                router.post(
                                    logout(),
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                            data-test="admin-logout"
                        >
                            Log out
                        </Button>
                    </div>
                </div>

                <nav
                    aria-label="Admin"
                    className="mx-auto flex max-w-[1600px] [scrollbar-width:none] gap-1 overflow-x-auto px-2 md:px-4 2xl:px-6 [&::-webkit-scrollbar]:hidden"
                >
                    {navItems.map((item, index) => (
                        <Link
                            key={item.title}
                            href={item.href}
                            aria-current={
                                index === activeIndex ? 'page' : undefined
                            }
                            className={cn(
                                'focus-visible:ring-ring -mb-px shrink-0 border-b-2 px-3 py-2.5 text-sm font-medium whitespace-nowrap transition-colors focus-visible:rounded-t-md focus-visible:ring-2 focus-visible:outline-none focus-visible:ring-inset',
                                index === activeIndex
                                    ? 'border-primary text-foreground'
                                    : 'text-muted-foreground hover:text-foreground hover:border-border border-transparent',
                            )}
                        >
                            {item.title}
                        </Link>
                    ))}
                </nav>
            </header>

            <main
                id="admin-main"
                tabIndex={-1}
                className="mx-auto w-full max-w-[1600px] p-4 focus:outline-none md:p-6 2xl:p-8"
            >
                {children}
            </main>
        </div>
    );
}
