import { usePage } from '@inertiajs/react';

import AppLogoIcon from '@/components/app-logo-icon';

/** Sidebar brand: configured logo (or the gold monogram), portal name and institution. */
export default function AppLogo() {
    const { branding } = usePage().props;

    return (
        <>
            {/* Wrapped: the sidebar button forces direct svg children to size-4. */}
            <div className="flex size-9 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-white/10 ring-1 ring-white/15">
                {branding.logo_url ? (
                    <img
                        src={branding.logo_url}
                        alt=""
                        className="size-full bg-white object-contain p-1"
                    />
                ) : (
                    <AppLogoIcon
                        className="size-7 fill-current text-brand-gold"
                        aria-hidden="true"
                    />
                )}
            </div>
            <div className="ml-0.5 grid flex-1 text-left leading-tight">
                <span className="truncate font-heading text-[15px] font-extrabold tracking-tight text-white">
                    {branding.scol_name}
                </span>
                <span className="truncate text-[11px] font-medium text-sidebar-muted">
                    {branding.institution_name}
                </span>
            </div>
        </>
    );
}
