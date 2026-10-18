import { Inbox, LogOut, Monitor, Moon, Sun } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';

import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Spinner } from '@/components/ui/spinner';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { useAppearance } from '@/hooks/use-appearance';
import type { Appearance } from '@/hooks/use-appearance';

const appearances: { value: Appearance; label: string; icon: LucideIcon }[] = [
    { value: 'light', label: 'Clair', icon: Sun },
    { value: 'dark', label: 'Sombre', icon: Moon },
    { value: 'system', label: 'Auto', icon: Monitor },
];

function initials(name: string): string {
    const words = name.split(/\s+/).filter(Boolean);
    const last = words.length > 1 ? words[words.length - 1][0] : '';

    return ((words[0]?.[0] ?? '') + last).toUpperCase();
}

function StudentAvatar({
    name,
    className,
}: {
    name: string;
    className: string;
}) {
    return (
        <Avatar className={className}>
            <AvatarFallback className="bg-primary text-xs font-semibold text-primary-foreground">
                {initials(name)}
            </AvatarFallback>
        </Avatar>
    );
}

/**
 * Account menu of the student whose record is open: who is connected, appearance and sign-out
 * behind the avatar. Every row shares the same left edge.
 */
export function AccountMenu({
    name,
    lastName,
    email,
    busy,
    onRequests,
    onLogout,
}: {
    name: string;
    /** Shown in the top bar: the last name alone, so that long names fit. */
    lastName: string;
    email: string | null;
    busy: boolean;
    /** Opens « Mes demandes ». */
    onRequests: () => void;
    onLogout: () => void;
}) {
    const { appearance, updateAppearance } = useAppearance();

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    className="h-10 gap-2.5 rounded-full p-0.5 data-[state=open]:bg-accent sm:pr-3"
                    aria-label={`Menu du compte : ${name}`}
                >
                    <StudentAvatar name={name} className="size-9" />
                    <span className="hidden max-w-40 truncate text-sm font-medium sm:inline">
                        {lastName}
                    </span>
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent
                align="end"
                sideOffset={8}
                className="w-[min(18rem,calc(100vw-2rem))] rounded-xl p-1.5"
            >
                <div className="flex items-center gap-3 px-2 py-2">
                    <StudentAvatar name={name} className="size-10" />
                    <div className="min-w-0">
                        <p className="truncate text-sm font-semibold">{name}</p>
                        {email && (
                            <p className="truncate text-xs text-muted-foreground">
                                {email}
                            </p>
                        )}
                    </div>
                </div>
                <DropdownMenuSeparator className="my-1.5" />
                <DropdownMenuItem
                    className="h-10 gap-2.5 rounded-lg px-2"
                    onSelect={onRequests}
                >
                    <Inbox aria-hidden="true" />
                    Mes demandes
                </DropdownMenuItem>
                <DropdownMenuSeparator className="my-1.5" />
                <div className="space-y-2 px-2 py-1.5">
                    <p className="text-xs text-muted-foreground">Apparence</p>
                    <ToggleGroup
                        type="single"
                        value={appearance}
                        onValueChange={(value) => {
                            if (value) updateAppearance(value as Appearance);
                        }}
                        className="grid w-full grid-cols-3 gap-1 rounded-lg bg-muted p-1"
                        aria-label="Apparence"
                    >
                        {appearances.map(({ value, label, icon: Icon }) => (
                            <ToggleGroupItem
                                key={value}
                                value={value}
                                className="h-8 gap-1.5 rounded-md text-xs text-muted-foreground hover:bg-background/60 hover:text-foreground data-[state=on]:bg-background data-[state=on]:text-foreground data-[state=on]:shadow-sm"
                            >
                                <Icon className="size-3.5" aria-hidden="true" />
                                {label}
                            </ToggleGroupItem>
                        ))}
                    </ToggleGroup>
                </div>
                <DropdownMenuSeparator className="my-1.5" />
                <DropdownMenuItem
                    variant="destructive"
                    className="h-10 gap-2.5 rounded-lg px-2"
                    disabled={busy}
                    onSelect={onLogout}
                >
                    {busy ? <Spinner /> : <LogOut aria-hidden="true" />}
                    Se déconnecter
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
