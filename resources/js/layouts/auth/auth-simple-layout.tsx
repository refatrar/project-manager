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
        <div className="bg-background flex min-h-svh flex-col items-center justify-center px-4 py-10 sm:px-6">
            <main className="w-full max-w-[25rem]">
                <Link
                    href={home()}
                    className="focus-visible:ring-ring mx-auto mb-8 flex w-fit items-center gap-2.5 rounded-lg focus-visible:ring-2 focus-visible:ring-offset-4 focus-visible:outline-none"
                >
                    <span className="bg-primary text-primary-foreground flex size-9 items-center justify-center rounded-lg">
                        <AppLogoIcon className="size-5" />
                    </span>
                    <span className="text-base font-semibold tracking-tight">
                        {name}
                    </span>
                </Link>

                <div className="bg-card rounded-xl border p-6 shadow-xs sm:p-8">
                    <header className="mb-6 space-y-1.5">
                        <h1 className="text-xl leading-7 font-semibold tracking-tight">
                            {title}
                        </h1>
                        {description ? (
                            <p className="text-muted-foreground text-sm">
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
