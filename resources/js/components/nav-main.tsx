import { Link } from '@inertiajs/react';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { toUrl } from '@/lib/utils';
import type { NavItem } from '@/types';

const pathOf = (href: NavItem['href']) => toUrl(href).split('?')[0];

/**
 * The one menu entry lit for the current page: the entry stays active on its sub-pages
 * (/students/… keeps "Étudiants" lit), and the most specific entry wins when two match
 * (/scholarships/reception/labels lights "Étiquettes DDB", not "Réception des dossiers").
 */
export function activeHrefOf(
    items: NavItem[],
    currentUrl: string,
): string | null {
    let best: string | null = null;
    for (const item of items) {
        const path = pathOf(item.href);
        const matches =
            currentUrl === path || currentUrl.startsWith(`${path}/`);
        if (matches && (best === null || path.length > best.length))
            best = path;
    }
    return best;
}

/** Menu group on the navy sidebar: small caps heading, icon entries and active bar. */
export function NavMain({
    items,
    label,
    activeHref,
}: {
    items: NavItem[];
    label: string;
    /** Path of the entry lit across the whole menu (activeHrefOf). */
    activeHref: string | null;
}) {
    const isActive = (href: NavItem['href']) => pathOf(href) === activeHref;

    return (
        <SidebarGroup className="px-3 py-0 pt-5 first:pt-3">
            <SidebarGroupLabel className="h-7 px-3 text-[10.5px] font-semibold tracking-[0.16em] text-sidebar-muted uppercase">
                {label}
            </SidebarGroupLabel>
            <SidebarMenu className="gap-0.5">
                {items.map((item) => {
                    const active = isActive(item.href);
                    return (
                        <SidebarMenuItem key={item.title}>
                            <SidebarMenuButton
                                asChild
                                isActive={active}
                                tooltip={{ children: item.title }}
                                className="relative h-10 gap-3 rounded-lg px-3 font-medium text-sidebar-foreground transition-colors hover:bg-sidebar-accent/70 hover:text-sidebar-accent-foreground data-[active=true]:bg-sidebar-accent data-[active=true]:font-semibold data-[active=true]:text-sidebar-accent-foreground data-[active=true]:before:absolute data-[active=true]:before:inset-y-2 data-[active=true]:before:left-0 data-[active=true]:before:w-[3px] data-[active=true]:before:rounded-r-full data-[active=true]:before:bg-brand-gold [&>svg]:size-[18px] [&>svg]:text-sidebar-muted hover:[&>svg]:text-sidebar-accent-foreground data-[active=true]:[&>svg]:text-sidebar-accent-foreground"
                            >
                                <Link
                                    href={item.href}
                                    prefetch
                                    aria-current={active ? 'page' : undefined}
                                >
                                    {item.icon && <item.icon />}
                                    <span>{item.title}</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    );
                })}
            </SidebarMenu>
        </SidebarGroup>
    );
}
