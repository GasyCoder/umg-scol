import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    BadgeCheck,
    Bell,
    CheckCheck,
    FilePenLine,
    Mail,
    MailOpen,
    Settings2,
    UserPen,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { FormEvent } from 'react';
import { EmptyState } from '@/components/empty-state';
import { ago } from '@/components/notification-bell';
import { PageBody, PageHeader } from '@/components/page-header';
import { Pagination } from '@/components/scholarships/shared';
import type { PageLink } from '@/components/scholarships/shared';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';
import type { AppNotification } from '@/types/auth';

type NotificationType = { kind: string; label: string; enabled: boolean };
type Filters = { filter: string; kind: string };

/** Icon, colour and action wording per kind (StudentActivity.php). */
const kinds: Record<string, { icon: LucideIcon; tone: string; open: string }> =
    {
        correction_requested: {
            icon: FilePenLine,
            tone: 'text-warning',
            open: 'Voir la demande',
        },
        information_confirmed: {
            icon: BadgeCheck,
            tone: 'text-success',
            open: 'Voir la fiche',
        },
        information_updated: {
            icon: UserPen,
            tone: 'text-info',
            open: 'Voir la fiche',
        },
    };
const fallback = { icon: Bell, tone: 'text-muted-foreground', open: 'Ouvrir' };

/** « Aujourd'hui », « Hier » or the date: the list reads day by day. */
function dayLabel(value: string): string {
    const day = new Date(value);
    const today = new Date();
    const yesterday = new Date();
    yesterday.setDate(today.getDate() - 1);
    if (day.toDateString() === today.toDateString()) return 'Aujourd’hui';
    if (day.toDateString() === yesterday.toDateString()) return 'Hier';

    return day.toLocaleDateString('fr-FR', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
    });
}

export default function Notifications({
    entries,
    filters,
    types,
}: {
    entries: { data: AppNotification[]; total: number; links: PageLink[] };
    filters: Filters;
    types: NotificationType[];
}) {
    const { notifications } = usePage().props;
    const preferences = useForm<{ enabled: string[] }>({
        enabled: types.filter((type) => type.enabled).map((type) => type.kind),
    });
    const filter = (values: Partial<Filters>) =>
        router.get(
            '/notifications',
            { ...filters, ...values },
            { preserveState: true, preserveScroll: true },
        );
    const save = (event: FormEvent) => {
        event.preventDefault();
        preferences.put('/notifications/preferences', {
            preserveScroll: true,
            onSuccess: () => preferences.setDefaults(),
        });
    };
    const toggleRead = (item: AppNotification) =>
        router.patch(
            `/notifications/${item.id}`,
            { read: !item.read_at },
            { preserveScroll: true },
        );
    const days = entries.data.reduce<
        { label: string; items: AppNotification[] }[]
    >((groups, item) => {
        const label = dayLabel(item.created_at);
        const last = groups[groups.length - 1];
        if (last?.label === label) last.items.push(item);
        else groups.push({ label, items: [item] });
        return groups;
    }, []);

    return (
        <>
            <Head title="Notifications" />
            <PageBody>
                <PageHeader
                    icon={Bell}
                    title="Notifications"
                    description="Les signalements, modifications et confirmations des étudiants de votre périmètre."
                    actions={
                        <Button
                            variant="outline"
                            disabled={notifications.unread === 0}
                            onClick={() =>
                                router.post(
                                    '/notifications/read-all',
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                        >
                            <CheckCheck aria-hidden="true" />
                            Tout marquer comme lu
                        </Button>
                    }
                />
                <div className="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_20rem]">
                    <Card className="gap-0 overflow-hidden py-0">
                        <CardHeader className="flex flex-col gap-3 border-b px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                            <ToggleGroup
                                type="single"
                                value={filters.filter || 'all'}
                                onValueChange={(value) =>
                                    value &&
                                    filter({
                                        filter: value === 'all' ? '' : value,
                                    })
                                }
                                className="grid w-full grid-cols-3 gap-1 rounded-lg bg-muted p-1 sm:w-auto"
                                aria-label="Filtrer par état"
                            >
                                {(
                                    [
                                        ['all', 'Toutes'],
                                        ['unread', 'Non lues'],
                                        ['read', 'Lues'],
                                    ] as [string, string][]
                                ).map(([value, label]) => (
                                    <ToggleGroupItem
                                        key={value}
                                        value={value}
                                        className="h-8 gap-1.5 rounded-md px-3 text-sm text-muted-foreground hover:bg-background/60 hover:text-foreground data-[state=on]:bg-background data-[state=on]:text-foreground data-[state=on]:shadow-sm"
                                    >
                                        {label}
                                        {value === 'unread' &&
                                            notifications.unread > 0 && (
                                                <span className="rounded-full bg-primary px-1.5 text-[11px] leading-4 font-semibold text-primary-foreground tabular-nums">
                                                    {notifications.unread}
                                                </span>
                                            )}
                                    </ToggleGroupItem>
                                ))}
                            </ToggleGroup>
                            <Select
                                value={filters.kind || 'all'}
                                onValueChange={(value) =>
                                    filter({
                                        kind: value === 'all' ? '' : value,
                                    })
                                }
                            >
                                <SelectTrigger
                                    className="w-full sm:w-64"
                                    aria-label="Type de notification"
                                >
                                    <SelectValue placeholder="Tous les types" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        Tous les types
                                    </SelectItem>
                                    {types.map((type) => (
                                        <SelectItem
                                            key={type.kind}
                                            value={type.kind}
                                        >
                                            {type.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </CardHeader>

                        {entries.data.length === 0 ? (
                            <EmptyState
                                illustration="done"
                                title="Aucune notification"
                                description={
                                    filters.filter || filters.kind
                                        ? 'Aucune notification ne correspond à ces filtres.'
                                        : 'Les prochains événements des étudiants apparaîtront ici.'
                                }
                            />
                        ) : (
                            <div>
                                {days.map((day) => (
                                    <section
                                        key={day.label}
                                        aria-label={day.label}
                                    >
                                        <h2 className="border-b bg-muted/40 px-4 py-1.5 text-xs font-medium text-muted-foreground first-letter:uppercase sm:px-5">
                                            {day.label}
                                        </h2>
                                        <ul className="divide-y">
                                            {day.items.map((item) => {
                                                const kind =
                                                    kinds[item.data.kind] ??
                                                    fallback;
                                                const unread = !item.read_at;

                                                return (
                                                    <li
                                                        key={item.id}
                                                        className={cn(
                                                            'group flex items-start gap-3 px-4 py-3.5 sm:px-5',
                                                            unread &&
                                                                'bg-accent/25',
                                                        )}
                                                    >
                                                        <kind.icon
                                                            className={cn(
                                                                'mt-0.5 size-5 shrink-0',
                                                                kind.tone,
                                                            )}
                                                            aria-hidden="true"
                                                        />
                                                        <div className="min-w-0 flex-1">
                                                            <p className="flex items-center gap-2 text-sm font-medium text-foreground">
                                                                {
                                                                    item.data
                                                                        .title
                                                                }
                                                                {unread && (
                                                                    <span className="size-2 shrink-0 rounded-full bg-primary">
                                                                        <span className="sr-only">
                                                                            Non
                                                                            lue
                                                                        </span>
                                                                    </span>
                                                                )}
                                                            </p>
                                                            <p className="mt-0.5 text-sm text-muted-foreground">
                                                                {
                                                                    item.data
                                                                        .message
                                                                }
                                                            </p>
                                                            <time
                                                                dateTime={
                                                                    item.created_at
                                                                }
                                                                className="mt-1 block text-xs text-muted-foreground"
                                                            >
                                                                {ago(
                                                                    item.created_at,
                                                                )}
                                                            </time>
                                                        </div>
                                                        <div className="flex shrink-0 items-center gap-1">
                                                            <Button
                                                                size="sm"
                                                                variant="ghost"
                                                                className="text-primary"
                                                                asChild
                                                            >
                                                                <Link
                                                                    href={`/notifications/${item.id}/open`}
                                                                    method="post"
                                                                    as="button"
                                                                >
                                                                    <span className="hidden sm:inline">
                                                                        {
                                                                            kind.open
                                                                        }
                                                                    </span>
                                                                    <ArrowRight aria-hidden="true" />
                                                                    <span className="sr-only sm:hidden">
                                                                        {
                                                                            kind.open
                                                                        }
                                                                    </span>
                                                                </Link>
                                                            </Button>
                                                            <Tooltip>
                                                                <TooltipTrigger
                                                                    asChild
                                                                >
                                                                    <Button
                                                                        size="icon"
                                                                        variant="ghost"
                                                                        className="size-8 text-muted-foreground"
                                                                        aria-label={
                                                                            unread
                                                                                ? 'Marquer comme lue'
                                                                                : 'Marquer comme non lue'
                                                                        }
                                                                        onClick={() =>
                                                                            toggleRead(
                                                                                item,
                                                                            )
                                                                        }
                                                                    >
                                                                        {unread ? (
                                                                            <MailOpen aria-hidden="true" />
                                                                        ) : (
                                                                            <Mail aria-hidden="true" />
                                                                        )}
                                                                    </Button>
                                                                </TooltipTrigger>
                                                                <TooltipContent>
                                                                    {unread
                                                                        ? 'Marquer comme lue'
                                                                        : 'Marquer comme non lue'}
                                                                </TooltipContent>
                                                            </Tooltip>
                                                        </div>
                                                    </li>
                                                );
                                            })}
                                        </ul>
                                    </section>
                                ))}
                            </div>
                        )}
                        <CardFooter className="flex flex-wrap items-center justify-between gap-3 border-t px-4 py-3 sm:px-5">
                            <p className="text-sm text-muted-foreground tabular-nums">
                                {entries.total} notification
                                {entries.total > 1 ? 's' : ''}
                            </p>
                            <Pagination links={entries.links} />
                        </CardFooter>
                    </Card>

                    <Card className="gap-0 py-0">
                        <CardHeader className="border-b px-5 py-4">
                            <CardTitle className="flex items-center gap-2 text-base">
                                <Settings2
                                    className="size-4 text-primary"
                                    aria-hidden="true"
                                />
                                Mes préférences
                            </CardTitle>
                            <CardDescription>
                                Ce que vous recevez dans votre cloche.
                            </CardDescription>
                        </CardHeader>
                        <form onSubmit={save}>
                            <CardContent className="space-y-1 px-3 py-3">
                                {types.map((type) => {
                                    const kind = kinds[type.kind] ?? fallback;

                                    return (
                                        <Label
                                            key={type.kind}
                                            htmlFor={`notification-${type.kind}`}
                                            className="flex min-h-11 cursor-pointer items-center gap-3 rounded-md px-2 py-2 font-normal hover:bg-muted"
                                        >
                                            <Checkbox
                                                id={`notification-${type.kind}`}
                                                checked={preferences.data.enabled.includes(
                                                    type.kind,
                                                )}
                                                onCheckedChange={(checked) =>
                                                    preferences.setData(
                                                        'enabled',
                                                        checked === true
                                                            ? [
                                                                  ...preferences
                                                                      .data
                                                                      .enabled,
                                                                  type.kind,
                                                              ]
                                                            : preferences.data.enabled.filter(
                                                                  (item) =>
                                                                      item !==
                                                                      type.kind,
                                                              ),
                                                    )
                                                }
                                            />
                                            <kind.icon
                                                className={cn(
                                                    'size-4 shrink-0',
                                                    kind.tone,
                                                )}
                                                aria-hidden="true"
                                            />
                                            <span className="text-sm leading-5">
                                                {type.label}
                                            </span>
                                        </Label>
                                    );
                                })}
                                {Object.values(preferences.errors).map(
                                    (error) => (
                                        <p
                                            key={error}
                                            role="alert"
                                            className="px-2 text-sm text-destructive"
                                        >
                                            {error}
                                        </p>
                                    ),
                                )}
                            </CardContent>
                            <CardFooter className="flex-col items-stretch gap-2 border-t px-5 py-4">
                                {/* Shown only once something changed: nothing to save otherwise. */}
                                {preferences.isDirty && (
                                    <Button
                                        type="submit"
                                        disabled={preferences.processing}
                                    >
                                        {preferences.processing
                                            ? 'Enregistrement…'
                                            : 'Enregistrer'}
                                    </Button>
                                )}
                                <p className="text-xs text-muted-foreground">
                                    Les notifications lues sont gardées 90
                                    jours.
                                </p>
                            </CardFooter>
                        </form>
                    </Card>
                </div>
            </PageBody>
        </>
    );
}
