import { usePage } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { UserMenuContent } from '@/components/user-menu-content';
import { useInitials } from '@/hooks/use-initials';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

/** DashWind-style top bar: sidebar toggle and breadcrumbs on the left, account menu on the right. */
export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    const { auth } = usePage().props;
    const getInitials = useInitials();

    return (
        <header className="sticky top-0 z-20 flex h-16 shrink-0 items-center gap-3 border-b bg-card px-4 md:px-6">
            <SidebarTrigger
                className="-ml-1 size-9 text-muted-foreground hover:text-primary"
                aria-label="Afficher ou masquer le menu"
            />
            <div className="hidden min-w-0 sm:block">
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>
            {auth.user && (
                <div className="ml-auto">
                    <DropdownMenu>
                        <DropdownMenuTrigger
                            className="group flex items-center gap-3 rounded-md px-1 py-1 outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            data-test="sidebar-menu-button"
                        >
                            <Avatar className="size-9 overflow-hidden rounded-full">
                                <AvatarImage
                                    src={auth.user.avatar}
                                    alt={auth.user.name}
                                />
                                <AvatarFallback className="rounded-full bg-primary text-xs font-bold text-primary-foreground">
                                    {getInitials(auth.user.name)}
                                </AvatarFallback>
                            </Avatar>
                            <span className="hidden text-left leading-none md:block">
                                {auth.roleLabel && (
                                    <span className="block pb-1 text-xs font-medium text-primary">
                                        {auth.roleLabel}
                                    </span>
                                )}
                                <span className="flex items-center gap-1 text-xs font-bold text-foreground">
                                    {auth.user.name}
                                    <ChevronDown
                                        className="size-3.5 text-muted-foreground transition-transform group-data-[state=open]:rotate-180"
                                        aria-hidden="true"
                                    />
                                </span>
                            </span>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent
                            align="end"
                            className="w-64 rounded-md border-t-2 border-t-primary"
                        >
                            <UserMenuContent user={auth.user} />
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            )}
        </header>
    );
}
