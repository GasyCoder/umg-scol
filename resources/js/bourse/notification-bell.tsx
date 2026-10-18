import {
    BadgeCheck,
    Ban,
    Bell,
    CircleCheck,
    Clock,
    FileText,
    Mail,
    XCircle,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';

import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';

/** One event of the bell, derived by the server from the student's requests and the office's emails. */
export type PortalNotification = {
    id: string;
    kind:
        | 'requested'
        | 'approved'
        | 'rejected'
        | 'applied'
        | 'cancelled'
        | 'message'
        | 'confirmed'
        | 'application';
    title: string;
    body: string;
    at: string;
    unread: boolean;
};

/** What happened, at a glance: icon and colour per kind (the title says it in words). */
const kinds: Record<
    PortalNotification['kind'],
    { icon: LucideIcon; tone: string }
> = {
    requested: { icon: Clock, tone: 'text-warning' },
    approved: { icon: CircleCheck, tone: 'text-success' },
    applied: { icon: CircleCheck, tone: 'text-success' },
    confirmed: { icon: BadgeCheck, tone: 'text-success' },
    rejected: { icon: XCircle, tone: 'text-danger' },
    cancelled: { icon: Ban, tone: 'text-muted-foreground' },
    message: { icon: Mail, tone: 'text-primary' },
    application: { icon: FileText, tone: 'text-primary' },
};

function ago(value: string): string {
    const minutes = Math.round(
        (Date.now() - new Date(value).getTime()) / 60000,
    );
    if (minutes < 1) return 'à l’instant';
    if (minutes < 60) return `il y a ${minutes} min`;
    const hours = Math.round(minutes / 60);
    if (hours < 24) return `il y a ${hours} h`;
    const days = Math.round(hours / 24);
    if (days < 7) return `il y a ${days} j`;

    return new Date(value).toLocaleDateString('fr-FR');
}

/**
 * Bell of the student portal: unread count on the icon, latest events in the menu; opening it marks
 * them as read. « Voir mes demandes » leads to the dedicated page.
 */
export function NotificationBell({
    items,
    unread,
    onOpen,
    onViewAll,
}: {
    items: PortalNotification[];
    unread: number;
    onOpen: () => void;
    onViewAll: () => void;
}) {
    return (
        <DropdownMenu onOpenChange={(open) => open && unread > 0 && onOpen()}>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className="relative size-10 rounded-full text-muted-foreground hover:text-foreground data-[state=open]:bg-accent"
                    aria-label={
                        unread > 0
                            ? `Notifications : ${unread} non lue${unread > 1 ? 's' : ''}`
                            : 'Notifications'
                    }
                >
                    <Bell className="size-5" aria-hidden="true" />
                    {unread > 0 && (
                        <span className="absolute top-1.5 right-1.5 flex min-w-4 items-center justify-center rounded-full bg-danger px-1 text-[10px] leading-4 font-semibold text-white tabular-nums ring-2 ring-card">
                            {unread > 9 ? '9+' : unread}
                        </span>
                    )}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent
                align="end"
                sideOffset={8}
                className="w-[min(22rem,calc(100vw-2rem))] rounded-xl p-1.5"
            >
                <p className="px-2 py-2 text-sm font-semibold">Notifications</p>
                <DropdownMenuSeparator className="my-1" />
                {items.length === 0 ? (
                    <p className="px-2 py-6 text-center text-sm text-muted-foreground">
                        Aucune notification pour le moment.
                    </p>
                ) : (
                    <div className="max-h-80 overflow-y-auto">
                        {items.slice(0, 10).map((item) => {
                            const kind = kinds[item.kind];

                            return (
                                <DropdownMenuItem
                                    key={item.id}
                                    className="items-start gap-3 rounded-lg px-2 py-2.5"
                                    onSelect={onViewAll}
                                >
                                    <kind.icon
                                        className={cn(
                                            'mt-0.5 size-4 shrink-0',
                                            kind.tone,
                                        )}
                                        aria-hidden="true"
                                    />
                                    <span className="min-w-0 flex-1">
                                        <span className="flex items-center gap-2 text-sm font-medium text-foreground">
                                            {item.title}
                                            {item.unread && (
                                                <span className="size-1.5 shrink-0 rounded-full bg-primary">
                                                    <span className="sr-only">
                                                        non lue
                                                    </span>
                                                </span>
                                            )}
                                        </span>
                                        <span className="line-clamp-2 text-xs text-muted-foreground">
                                            {item.body}
                                        </span>
                                        <span className="mt-0.5 block text-[11px] text-muted-foreground">
                                            {ago(item.at)}
                                        </span>
                                    </span>
                                </DropdownMenuItem>
                            );
                        })}
                    </div>
                )}
                <DropdownMenuSeparator className="my-1" />
                <DropdownMenuItem
                    className="h-10 justify-center rounded-lg text-sm font-medium text-primary"
                    onSelect={onViewAll}
                >
                    Voir mes demandes
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
