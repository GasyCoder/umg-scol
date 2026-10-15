import { Link } from '@inertiajs/react';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuBadge,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { toUrl } from '@/lib/utils';
import type { NavItem } from '@/types';

/** Menu group on the navy sidebar: small caps heading, icon entries, active bar and pending counters. */
export function NavMain({ items, label }: { items: NavItem[]; label: string }) {
    const { currentUrl } = useCurrentUrl();
    // An entry stays active on its sub-pages (/students/… keeps "Étudiants" lit).
    const isActive = (href: NavItem['href']) => {
        const path = toUrl(href).split('?')[0];
        return currentUrl === path || currentUrl.startsWith(`${path}/`);
    };

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
                                tooltip={{
                                    children: item.badge
                                        ? `${item.title} (${item.badge})`
                                        : item.title,
                                }}
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
                            {!!item.badge && (
                                <SidebarMenuBadge
                                    className="right-2 h-5 min-w-5 rounded-full bg-brand-gold px-1.5 text-[11px] font-bold text-sidebar tabular-nums peer-hover/menu-button:text-sidebar peer-data-[active=true]/menu-button:text-sidebar peer-data-[size=default]/menu-button:top-2.5"
                                    aria-label={`${item.badge} à traiter`}
                                >
                                    {item.badge > 99 ? '99+' : item.badge}
                                </SidebarMenuBadge>
                            )}
                        </SidebarMenuItem>
                    );
                })}
            </SidebarMenu>
        </SidebarGroup>
    );
}
