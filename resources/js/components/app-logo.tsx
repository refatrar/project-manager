import { usePage } from '@inertiajs/react';

import AppLogoIcon from '@/components/app-logo-icon';

export default function AppLogo() {
    const { name } = usePage().props;

    return (
        <>
            <div className="bg-sidebar-primary text-sidebar-primary-foreground flex aspect-square size-8 items-center justify-center rounded-lg">
                <AppLogoIcon className="size-[1.125rem] fill-current" />
            </div>
            <div className="ml-0.5 grid flex-1 text-left">
                <span className="truncate text-[0.9375rem] leading-tight font-semibold tracking-tight">
                    {name}
                </span>
            </div>
        </>
    );
}
