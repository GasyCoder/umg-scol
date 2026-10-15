import { Link, usePage } from '@inertiajs/react';
import {
    Palette,
    Settings2,
    ShieldCheck,
    Sparkles,
    UserRound,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { PropsWithChildren, ReactNode } from 'react';
import { PageBody } from '@/components/page-header';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn, toUrl } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { edit } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import type { NavItem } from '@/types';

type SettingsNavItem = NavItem & { icon: LucideIcon; hint: string };

const sidebarNavItems: SettingsNavItem[] = [
    {
        title: 'Profil',
        hint: 'Nom et adresse email',
        href: edit(),
        icon: UserRound,
    },
    {
        title: 'Sécurité',
        hint: 'Mot de passe, 2FA, clés',
        href: editSecurity(),
        icon: ShieldCheck,
    },
    {
        title: 'Apparence',
        hint: 'Thème clair ou sombre',
        href: editAppearance(),
        icon: Palette,
    },
];

/**
 * Account settings shell inside the app layout: title block, vertical navigation with icons
 * (horizontal pills on small screens) and the page content stacked in `SettingsSection` cards.
 */
const platformNavItem: SettingsNavItem = {
    title: 'Plateforme',
    hint: 'Noms, logo et favicon',
    href: '/settings/platform',
    icon: Sparkles,
};

export default function SettingsLayout({ children }: PropsWithChildren) {
    const { isCurrentOrParentUrl } = useCurrentUrl();
    const { auth } = usePage().props;
    const items = auth.canManageBranding
        ? [...sidebarNavItems, platformNavItem]
        : sidebarNavItems;

    return (
        <PageBody>
            {/* Not a heading: each settings page renders its own h1. */}
            <div className="flex items-start gap-4">
                <span
                    className="hidden size-12 shrink-0 items-center justify-center rounded-xl bg-primary text-primary-foreground shadow-sm ring-4 ring-accent sm:flex"
                    aria-hidden="true"
                >
                    <Settings2 className="size-6" />
                </span>
                <div className="space-y-1">
                    <p className="font-heading text-2xl leading-tight font-bold tracking-tight text-foreground lg:text-[1.65rem]">
                        Paramètres
                    </p>
                    <p className="text-sm text-muted-foreground">
                        Votre profil, la sécurité de votre compte et l’apparence
                        du portail
                    </p>
                </div>
            </div>

            <div className="grid gap-6 lg:grid-cols-[15rem_minmax(0,1fr)] lg:gap-8">
                <aside className="min-w-0">
                    <nav
                        className="-mx-1 flex gap-1.5 overflow-x-auto px-1 pb-1 lg:sticky lg:top-20 lg:mx-0 lg:flex-col lg:overflow-visible lg:px-0 lg:pb-0"
                        aria-label="Paramètres"
                    >
                        {items.map((item) => {
                            const active = isCurrentOrParentUrl(item.href);
                            const Icon = item.icon;

                            return (
                                <Link
                                    key={toUrl(item.href)}
                                    href={item.href}
                                    aria-current={active ? 'page' : undefined}
                                    className={cn(
                                        'group flex shrink-0 items-center gap-3 rounded-xl border px-3 py-2 text-sm transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none lg:py-2.5',
                                        active
                                            ? 'border-border bg-card text-foreground shadow-card'
                                            : 'border-transparent text-muted-foreground hover:bg-card/60 hover:text-foreground',
                                    )}
                                >
                                    <span
                                        className={cn(
                                            'flex size-8 shrink-0 items-center justify-center rounded-lg transition-colors',
                                            active
                                                ? 'bg-primary text-primary-foreground'
                                                : 'bg-muted text-muted-foreground group-hover:text-primary',
                                        )}
                                        aria-hidden="true"
                                    >
                                        <Icon className="size-4" />
                                    </span>
                                    <span className="min-w-0">
                                        <span className="block font-semibold">
                                            {item.title}
                                        </span>
                                        <span className="hidden truncate text-xs text-muted-foreground lg:block">
                                            {item.hint}
                                        </span>
                                    </span>
                                </Link>
                            );
                        })}
                    </nav>
                </aside>

                <div className="min-w-0 space-y-6">{children}</div>
            </div>
        </PageBody>
    );
}

/**
 * One settings block: `.surface` card with an icon tile, title, description, optional
 * right-hand meta (status badge) and the content below. `danger` tints it for destructive zones.
 */
export function SettingsSection({
    icon: Icon,
    title,
    description,
    meta,
    tone = 'default',
    children,
    className,
}: {
    icon: LucideIcon;
    title: string;
    description?: ReactNode;
    meta?: ReactNode;
    tone?: 'default' | 'danger';
    children: ReactNode;
    className?: string;
}) {
    const danger = tone === 'danger';

    return (
        <section
            className={cn(
                'surface overflow-hidden',
                danger && 'border-danger/30',
                className,
            )}
        >
            <header
                className={cn(
                    'flex flex-wrap items-start gap-x-4 gap-y-3 border-b px-5 py-4 sm:px-6',
                    danger
                        ? 'border-danger/20 bg-danger-soft/60'
                        : 'bg-muted/30',
                )}
            >
                <span
                    className={cn(
                        'flex size-10 shrink-0 items-center justify-center rounded-lg',
                        danger
                            ? 'bg-danger-soft text-danger-foreground ring-1 ring-danger/20'
                            : 'bg-accent text-primary',
                    )}
                    aria-hidden="true"
                >
                    <Icon className="size-5" />
                </span>
                <div className="min-w-0 flex-1 space-y-0.5">
                    <h2
                        className={cn(
                            'font-heading text-base font-bold',
                            danger
                                ? 'text-danger-foreground'
                                : 'text-foreground',
                        )}
                    >
                        {title}
                    </h2>
                    {description && (
                        <p className="text-sm text-muted-foreground">
                            {description}
                        </p>
                    )}
                </div>
                {meta && <div className="shrink-0">{meta}</div>}
            </header>
            <div className="px-5 py-5 sm:px-6">{children}</div>
        </section>
    );
}
