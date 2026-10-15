import { Link, router, usePage } from '@inertiajs/react';
import { LogOut, Settings, ShieldCheck } from 'lucide-react';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import { UserInfo } from '@/components/user-info';
import { useMobileNavigation } from '@/hooks/use-mobile-navigation';
import { logout } from '@/routes';
import { edit } from '@/routes/profile';
import type { User } from '@/types';

type Props = {
    user: User;
};

export function UserMenuContent({ user }: Props) {
    const cleanup = useMobileNavigation();
    const roleLabel = usePage().props.auth.roleLabel;

    const handleLogout = () => {
        cleanup();
        router.flushAll();
    };

    return (
        <>
            <DropdownMenuLabel className="p-0 font-normal">
                <div className="flex items-center gap-3 rounded-lg bg-muted/60 px-2.5 py-2.5 text-left text-sm">
                    <UserInfo user={user} showEmail={true} />
                </div>
                {roleLabel && (
                    <p className="flex items-center gap-1.5 px-2.5 pt-2 text-xs font-medium text-muted-foreground">
                        <ShieldCheck
                            className="size-3.5 text-primary"
                            aria-hidden="true"
                        />
                        {roleLabel}
                    </p>
                )}
            </DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuGroup>
                <DropdownMenuItem asChild>
                    <Link
                        className="flex w-full cursor-pointer items-center rounded-lg"
                        href={edit()}
                        prefetch
                        onClick={cleanup}
                    >
                        <Settings className="mr-2" />
                        Paramètres du compte
                    </Link>
                </DropdownMenuItem>
            </DropdownMenuGroup>
            <DropdownMenuSeparator />
            <DropdownMenuItem asChild>
                <Link
                    className="flex w-full cursor-pointer items-center rounded-lg text-danger-foreground focus:bg-danger-soft focus:text-danger-foreground"
                    href={logout()}
                    as="button"
                    onClick={handleLogout}
                    data-test="logout-button"
                >
                    <LogOut className="mr-2 text-danger-foreground" />
                    Se déconnecter
                </Link>
            </DropdownMenuItem>
        </>
    );
}
