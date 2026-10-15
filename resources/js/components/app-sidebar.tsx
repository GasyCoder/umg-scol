import { Link } from '@inertiajs/react';
import { PanelLeftClose, PanelLeftOpen, Settings } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { useNavigation } from '@/hooks/use-navigation';
import { dashboard } from '@/routes';
import { edit } from '@/routes/profile';

const footerButton =
    'h-9 gap-3 rounded-lg px-3 text-sidebar-foreground hover:bg-sidebar-accent/70 hover:text-sidebar-accent-foreground [&>svg]:size-[18px] [&>svg]:text-sidebar-muted';

export function AppSidebar() {
    const groups = useNavigation();
    const { state, toggleSidebar, isMobile } = useSidebar();
    const collapsed = state === 'collapsed' && !isMobile;

    return (
        <Sidebar
            collapsible="icon"
            variant="sidebar"
            className="border-r-0 [&_[data-sidebar=sidebar]]:bg-sidebar"
        >
            <SidebarHeader className="h-16 justify-center border-b border-sidebar-border px-3">
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            size="lg"
                            asChild
                            className="hover:bg-transparent active:bg-transparent"
                        >
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent className="[scrollbar-width:thin] [scrollbar-color:var(--sidebar-border)_transparent] pb-4">
                {groups.map((group) => (
                    <NavMain
                        key={group.label}
                        label={group.label}
                        items={group.items}
                    />
                ))}
            </SidebarContent>

            <SidebarFooter className="border-t border-sidebar-border p-3">
                <SidebarMenu className="gap-0.5">
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            asChild
                            tooltip={{ children: 'Paramètres du compte' }}
                            className={footerButton}
                        >
                            <Link href={edit()} prefetch>
                                <Settings />
                                <span>Paramètres</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                    {!isMobile && (
                        <SidebarMenuItem>
                            <SidebarMenuButton
                                onClick={toggleSidebar}
                                tooltip={{ children: 'Déplier le menu' }}
                                className={footerButton}
                                aria-label={
                                    collapsed
                                        ? 'Déplier le menu'
                                        : 'Réduire le menu'
                                }
                            >
                                {collapsed ? (
                                    <PanelLeftOpen />
                                ) : (
                                    <PanelLeftClose />
                                )}
                                <span>Réduire le menu</span>
                                <kbd className="ml-auto rounded border border-sidebar-border px-1.5 font-sans text-[10px] text-sidebar-muted">
                                    Ctrl B
                                </kbd>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    )}
                </SidebarMenu>
            </SidebarFooter>
        </Sidebar>
    );
}
