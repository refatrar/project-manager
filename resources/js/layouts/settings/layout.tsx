import { Link } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn, toUrl } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { edit } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import { index as teams } from '@/routes/teams';
import type { NavItem } from '@/types';

const sidebarNavItems: NavItem[] = [
    {
        title: 'Profile',
        href: edit(),
        icon: null,
    },
    {
        title: 'Security',
        href: editSecurity(),
        icon: null,
    },
    {
        title: 'Teams',
        href: teams(),
        icon: null,
    },
    {
        title: 'Appearance',
        href: editAppearance(),
        icon: null,
    },
];

export default function SettingsLayout({ children }: PropsWithChildren) {
    const { mostSpecificCurrentIndex } = useCurrentUrl();
    const activeIndex = mostSpecificCurrentIndex(
        sidebarNavItems.map((item) => item.href),
    );

    return (
        <div className="mx-auto w-full max-w-[1600px] space-y-6 p-4 md:p-6 2xl:p-8">
            <Heading
                as="h2"
                title="Settings"
                description="Manage your profile and account settings"
            />

            <div className="flex flex-col gap-6 lg:flex-row lg:gap-12">
                <aside className="-mx-4 border-b px-4 lg:mx-0 lg:w-52 lg:shrink-0 lg:border-b-0 lg:px-0">
                    <nav
                        className="flex [scrollbar-width:none] gap-1 overflow-x-auto pb-2 lg:flex-col lg:pb-0 [&::-webkit-scrollbar]:hidden"
                        aria-label="Settings"
                    >
                        {sidebarNavItems.map((item, index) => (
                            <Button
                                key={`${toUrl(item.href)}-${index}`}
                                size="sm"
                                variant="ghost"
                                asChild
                                className={cn(
                                    'shrink-0 justify-start lg:w-full',
                                    index === activeIndex &&
                                        'bg-accent text-foreground font-semibold',
                                )}
                            >
                                <Link
                                    href={item.href}
                                    aria-current={
                                        index === activeIndex
                                            ? 'page'
                                            : undefined
                                    }
                                >
                                    {item.icon && <item.icon />}
                                    {item.title}
                                </Link>
                            </Button>
                        ))}
                    </nav>
                </aside>

                <div className="flex-1 md:max-w-2xl">
                    <section className="max-w-xl space-y-12">
                        {children}
                    </section>
                </div>
            </div>
        </div>
    );
}
