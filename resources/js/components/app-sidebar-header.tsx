import { usePage } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';
import { CommandPalette } from '@/components/command-palette';
import { NotificationBell } from '@/components/notification-bell';
import { ThemeToggle } from '@/components/theme-toggle';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { UserMenuContent } from '@/components/user-menu-content';
import { useInitials } from '@/hooks/use-initials';

/**
 * Sticky top bar: menu toggle and page search on the left (the page header names the page); theme,
 * notifications and the account menu (with role) on the right, each in its own spaced control.
 */
export function AppSidebarHeader() {
    const { auth } = usePage().props;
    const getInitials = useInitials();

    return (
        <header className="sticky top-0 z-20 flex h-16 shrink-0 items-center gap-2 border-b bg-card/85 px-3 backdrop-blur-md supports-[backdrop-filter]:bg-card/75 md:gap-3 md:px-6">
            <SidebarTrigger
                className="size-10 rounded-full text-muted-foreground hover:bg-accent hover:text-primary md:hidden"
                aria-label="Afficher le menu"
            />
            <CommandPalette />
            <div className="ml-auto flex items-center gap-2 sm:gap-3">
                <ThemeToggle />
                <NotificationBell />
                {auth.user && (
                    <>
                        <span
                            className="mx-1 hidden h-8 w-px bg-border sm:block"
                            aria-hidden="true"
                        />
                        <DropdownMenu>
                            <DropdownMenuTrigger
                                className="group flex items-center gap-3 rounded-full py-1 pr-1 pl-1 outline-none hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring md:pr-3"
                                data-test="sidebar-menu-button"
                            >
                                <Avatar className="size-9 overflow-hidden rounded-full ring-2 ring-accent">
                                    <AvatarImage
                                        src={auth.user.avatar}
                                        alt={auth.user.name}
                                    />
                                    <AvatarFallback className="rounded-full bg-primary text-xs font-bold text-primary-foreground">
                                        {getInitials(auth.user.name)}
                                    </AvatarFallback>
                                </Avatar>
                                <span className="hidden text-left leading-tight md:block">
                                    <span className="flex items-center gap-1 text-sm font-semibold whitespace-nowrap text-foreground">
                                        {auth.user.name}
                                        <ChevronDown
                                            className="size-3.5 text-muted-foreground transition-transform group-data-[state=open]:rotate-180"
                                            aria-hidden="true"
                                        />
                                    </span>
                                    {auth.roleLabel && (
                                        <span className="block text-xs font-medium whitespace-nowrap text-muted-foreground">
                                            {auth.roleLabel}
                                        </span>
                                    )}
                                </span>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent
                                align="end"
                                sideOffset={8}
                                className="w-64 rounded-xl p-1.5"
                            >
                                <UserMenuContent user={auth.user} />
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </>
                )}
            </div>
        </header>
    );
}
