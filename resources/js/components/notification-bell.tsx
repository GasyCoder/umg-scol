import { Link, usePage } from '@inertiajs/react';
import {
    Bell,
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
    corrections: { icon: FilePenLine, tone: 'bg-warning-soft text-warning' },
    student_links: { icon: UserCheck, tone: 'bg-info-soft text-info' },
    applications_pending: {
        icon: FolderClock,
        tone: 'bg-accent text-primary',
    },
    applications_in_review: {
        icon: FolderSearch,
        tone: 'bg-info-soft text-info',
    },
    imports_ready: { icon: FileUp, tone: 'bg-success-soft text-success' },
};

/**
 * Top bar bell: work waiting for the current account (PendingTasks.php), refreshed
 * with every Inertia visit. Counts follow the server perimeter, never a client filter.
 */
export function NotificationBell() {
    const { tasks } = usePage().props;
    const total = tasks?.total ?? 0;
    const items = tasks?.items ?? [];

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className="relative size-10 rounded-full border bg-card text-muted-foreground shadow-xs hover:border-primary/40 hover:bg-accent hover:text-primary data-[state=open]:bg-accent data-[state=open]:text-primary"
                    aria-label={
                        total > 0
                            ? `Notifications : ${total} élément${total > 1 ? 's' : ''} à traiter`
                            : 'Notifications : rien à traiter'
                    }
                >
                    <Bell
                        className={cn(
                            'size-5',
                            total > 0 && 'animate-[wiggle_1s_ease-in-out_1]',
                        )}
                    />
                    {total > 0 && (
                        <span className="absolute -top-1 -right-1 flex h-[18px] min-w-[18px] items-center justify-center rounded-full bg-danger px-1 text-[10px] leading-none font-bold text-white tabular-nums ring-2 ring-card">
                            {total > 99 ? '99+' : total}
                        </span>
                    )}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent
                align="end"
                sideOffset={8}
                className="w-[min(22rem,calc(100vw-2rem))] overflow-hidden rounded-xl p-0"
            >
                <div className="flex items-center justify-between gap-2 border-b bg-muted/50 px-4 py-3">
                    <div>
                        <p className="font-heading text-sm font-bold text-foreground">
                            Notifications
                        </p>
                        <p className="text-xs text-muted-foreground">
                            Travail en attente dans votre périmètre
                        </p>
                    </div>
                    {total > 0 && (
                        <span className="rounded-full bg-primary px-2 py-0.5 text-xs font-bold text-primary-foreground tabular-nums">
                            {total}
                        </span>
                    )}
                </div>
                {items.length === 0 ? (
                    <EmptyState
                        compact
                        illustration="done"
                        title="Tout est à jour"
                        description="Aucune demande ni aucun dossier n’attend votre action."
                    />
                ) : (
                    <div className="max-h-[22rem] overflow-y-auto p-1.5">
                        {items.map((task) => {
                            const meta = icons[task.key] ?? {
                                icon: Bell,
                                tone: 'bg-accent text-primary',
                            };
                            const Icon = meta.icon;
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
                                            <Icon className="size-[18px]" />
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
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
