import { Link, usePage } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    const { name } = usePage().props;

    return (
        <div className="bg-background flex min-h-svh flex-col">
            <header className="flex items-center px-6 py-5 sm:px-10">
                <Link
                    href={home()}
                    className="focus-visible:ring-ring flex items-center gap-2.5 rounded-md focus-visible:ring-2 focus-visible:outline-none"
                >
                    <span className="bg-primary text-primary-foreground flex size-8 items-center justify-center rounded-md">
                        <AppLogoIcon className="size-4" />
                    </span>
                    <span className="text-sm font-semibold tracking-[-0.02em]">
                        {name}
                    </span>
                </Link>
            </header>
            <main className="flex flex-1 items-center justify-center px-6 pb-16 sm:px-10">
                <div className="w-full max-w-[24rem]">
                    <header className="mb-8 space-y-2">
                        <h1 className="text-2xl font-semibold tracking-[-0.03em]">
                            {title}
                        </h1>
                        {description ? (
                            <p className="text-muted-foreground max-w-[40ch] text-sm leading-6">
                                {description}
                            </p>
                        ) : null}
                    </header>
                    <div className="flex flex-col gap-6">{children}</div>
                </div>
            </main>
        </div>
    );
}
