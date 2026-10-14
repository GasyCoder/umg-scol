import { usePage } from '@inertiajs/react';

import AppLogoIcon from '@/components/app-logo-icon';

export default function AppLogo() {
    const { name } = usePage().props;

    return (
        <>
            {/* Wrapped: the sidebar button forces direct svg children to size-4. */}
            <div className="flex size-8 shrink-0 items-center justify-center">
                <AppLogoIcon
                    className="size-8 fill-current text-sidebar-primary"
                    aria-hidden="true"
                />
            </div>
            <div className="ml-1 grid flex-1 text-left text-sm">
                <span className="truncate leading-tight font-semibold">
                    {name}
                </span>
                <span className="truncate text-xs text-muted-foreground">
                    Université de Mahajanga
                </span>
            </div>
        </>
    );
}
