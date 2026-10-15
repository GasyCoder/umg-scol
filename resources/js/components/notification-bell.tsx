import { Link, router, usePage, usePoll } from '@inertiajs/react';
import {
    Bell,
    BadgeCheck,
    ChevronRight,
    FilePenLine,
    FileUp,
    FolderClock,
    FolderSearch,
    UserCheck,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { EmptyState } from '@/components/empty-state';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';

const icons: Record<string, { icon: LucideIcon; tone: string }> = {
    student_links: { icon: UserCheck, tone: 'bg-info-soft text-info' },
    corrections: { icon: FilePenLine, tone: 'bg-warning-soft text-warning' },
    applications_pending: {
        icon: FolderClock,
        tone: 'bg-accent text-primary',
    },
    applications_in_review: {
        icon: FolderSearch,
        tone: 'bg-info-soft text-info',
    },
    imports_ready: { icon: FileUp, tone: 'bg-success-soft text-success' },
    // Notifications (StudentActivity.php)
    correction_requested: {
        icon: FilePenLine,
        tone: 'bg-warning-soft text-warning',
    },
    information_confirmed: {
        icon: BadgeCheck,
        tone: 'bg-success-soft text-success',
    },
};

/** "il y a 5 min", "il y a 2 h", or the date. */
export const ago = (value: string) => {
    const minutes = Math.round(
        (Date.now() - new Date(value).getTime()) / 60000,
    );
    if (minutes < 1) return 'à l’instant';
    if (minutes < 60) return `il y a ${minutes} min`;
    if (minutes < 24 * 60) return `il y a ${Math.round(minutes / 60)} h`;
    return new Date(value).toLocaleDateString('fr-FR');
};

/**
 * Top bar bell: the account's notifications (student portal events, read or unread)
 * and the work waiting in its perimeter (PendingTasks.php), refreshed with every visit.
 */
export function NotificationBell() {
    const { tasks, notifications } = usePage().props;
    const unread = notifications?.unread ?? 0;
    const recent = notifications?.recent ?? [];
    const total = tasks?.total ?? 0;
    const items = tasks?.items ?? [];
    const badge = unread;
    usePoll(30000, { only: ['notifications', 'tasks'] });

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className="relative size-10 rounded-full border bg-card text-muted-foreground shadow-xs hover:border-primary/40 hover:bg-accent hover:text-primary data-[state=open]:bg-accent data-[state=open]:text-primary"
                    aria-label={`Notifications : ${unread} non lue${unread > 1 ? 's' : ''}, ${total} élément${total > 1 ? 's' : ''} à traiter`}
                >
                    <Bell
                        className={cn(
                            'size-5',
                            badge > 0 && 'animate-[wiggle_1s_ease-in-out_1]',
                        )}
                    />
                    {badge > 0 && (
                        <span className="absolute -top-1 -right-1 flex h-[18px] min-w-[18px] items-center justify-center rounded-full bg-danger px-1 text-[10px] leading-none font-bold text-white tabular-nums ring-2 ring-card">
                            {badge > 99 ? '99+' : badge}
                        </span>
                    )}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent
                align="end"
                sideOffset={8}
                className="w-[min(24rem,calc(100vw-2rem))] overflow-hidden rounded-xl p-0"
            >
                <div className="flex items-center justify-between gap-2 border-b bg-muted/50 px-4 py-3">
                    <p className="font-heading text-sm font-bold text-foreground">
                        Notifications
                        {unread > 0 && (
                            <span className="ml-2 rounded-full bg-primary px-2 py-0.5 text-xs font-bold text-primary-foreground tabular-nums">
                                {unread}
                            </span>
                        )}
                    </p>
                    {unread > 0 && (
                        <button
                            type="button"
                            className="rounded text-xs font-medium text-primary hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            onClick={() =>
                                router.post(
                                    '/notifications/read-all',
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                        >
                            Tout marquer comme lu
                        </button>
                    )}
                </div>

                <div className="max-h-[26rem] overflow-y-auto">
                    {recent.length === 0 ? (
                        <p className="px-4 py-5 text-center text-sm text-muted-foreground">
                            Aucune notification pour le moment.
                        </p>
                    ) : (
                        <div className="p-1.5">
                            {recent.map((item) => {
                                const meta = icons[item.data.kind] ?? {
                                    icon: Bell,
                                    tone: 'bg-accent text-primary',
                                };
                                return (
                                    <DropdownMenuItem
                                        key={item.id}
                                        asChild
                                        className={cn(
                                            'cursor-pointer items-start gap-3 rounded-lg p-2.5',
                                            !item.read_at && 'bg-accent/50',
                                        )}
                                    >
                                        <Link
                                            href={`/notifications/${item.id}/open`}
                                            method="post"
                                            as="button"
                                            className="w-full text-left"
                                        >
                                            <span
                                                className={cn(
                                                    'flex size-9 shrink-0 items-center justify-center rounded-lg',
                                                    meta.tone,
                                                )}
                                                aria-hidden="true"
                                            >
                                                <meta.icon className="size-[18px]" />
                                            </span>
                                            <span className="min-w-0 flex-1">
                                                <span className="block text-sm text-foreground">
                                                    {item.data.message}
                                                </span>
                                                <span className="mt-0.5 block text-xs text-muted-foreground">
                                                    {ago(item.created_at)}
                                                </span>
                                            </span>
                                            {!item.read_at && (
                                                <span
                                                    className="mt-1.5 size-2 shrink-0 rounded-full bg-primary"
                                                    aria-label="Non lue"
                                                />
                                            )}
                                        </Link>
                                    </DropdownMenuItem>
                                );
                            })}
                        </div>
                    )}

                    <p className="border-t bg-muted/30 px-4 py-2 text-xs font-bold tracking-wide text-muted-foreground uppercase">
                        À traiter{total > 0 ? ` · ${total}` : ''}
                    </p>
                    {items.length === 0 ? (
                        <EmptyState
                            compact
                            illustration="done"
                            title="Tout est à jour"
                            description="Aucune demande ni aucun dossier n’attend votre action."
                        />
                    ) : (
                        <div className="p-1.5">
                            {items.map((task) => {
                                const meta = icons[task.key] ?? {
                                    icon: Bell,
                                    tone: 'bg-accent text-primary',
                                };
                                return (
                                    <DropdownMenuItem
                                        key={task.key}
                                        asChild
                                        className="cursor-pointer items-start gap-3 rounded-lg p-2.5"
                                    >
                                        <Link href={task.href}>
                                            <span
                                                className={cn(
                                                    'flex size-9 shrink-0 items-center justify-center rounded-lg',
                                                    meta.tone,
                                                )}
                                                aria-hidden="true"
                                            >
                                                <meta.icon className="size-[18px]" />
                                            </span>
                                            <span className="min-w-0 flex-1">
                                                <span className="flex items-center justify-between gap-2">
                                                    <span className="truncate text-sm font-semibold text-foreground">
                                                        {task.label}
                                                    </span>
                                                    <span className="shrink-0 rounded-full bg-secondary px-2 text-xs font-bold text-secondary-foreground tabular-nums">
                                                        {task.count}
                                                    </span>
                                                </span>
                                                <span className="mt-0.5 block text-xs text-muted-foreground">
                                                    {task.description}
                                                </span>
                                            </span>
                                            <ChevronRight
                                                className="mt-2.5 size-4 shrink-0 text-muted-foreground"
                                                aria-hidden="true"
                                            />
                                        </Link>
                                    </DropdownMenuItem>
                                );
                            })}
                        </div>
                    )}
                </div>

                <DropdownMenuItem
                    asChild
                    className="cursor-pointer justify-center rounded-none border-t px-4 py-2.5 text-sm font-medium text-primary"
                >
                    <Link href="/notifications">
                        Voir et gérer les notifications
                    </Link>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
