import { Link } from '@inertiajs/react';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import type { NavItem } from '@/types';

/** DashWind menu group: spaced uppercase heading, bold Nunito items, primary colour when active. */
export function NavMain({
    items,
    label = 'Plateforme',
}: {
    items: NavItem[];
    label?: string;
}) {
    const { isCurrentUrl } = useCurrentUrl();

    return (
        <SidebarGroup className="px-3 py-0 pt-6 first:pt-3">
            <SidebarGroupLabel className="h-7 px-3 text-[11px] font-bold tracking-[0.2em] text-muted-foreground uppercase">
                {label}
            </SidebarGroupLabel>
            <SidebarMenu className="gap-0.5">
                {items.map((item) => (
                    <SidebarMenuItem key={item.title}>
                        <SidebarMenuButton
                            asChild
                            isActive={isCurrentUrl(item.href)}
                            tooltip={{ children: item.title }}
                            className="h-10 gap-3 px-3 font-heading font-bold text-sidebar-foreground hover:bg-transparent hover:text-primary data-[active=true]:bg-accent data-[active=true]:font-bold data-[active=true]:text-primary [&>svg]:size-5 [&>svg]:text-muted-foreground hover:[&>svg]:text-primary data-[active=true]:[&>svg]:text-primary"
                        >
                            <Link href={item.href} prefetch>
                                {item.icon && <item.icon />}
                                <span>{item.title}</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                ))}
            </SidebarMenu>
        </SidebarGroup>
    );
}
