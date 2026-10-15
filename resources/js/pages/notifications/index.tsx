import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import {
    BadgeCheck,
    Bell,
    CheckCheck,
    FilePenLine,
    Mail,
    MailOpen,
    Settings2,
} from 'lucide-react';
import type { FormEvent } from 'react';
import { EmptyState } from '@/components/empty-state';
import { PageBody, PageHeader } from '@/components/page-header';
import { Pagination, formatDate } from '@/components/scholarships/shared';
import type { PageLink } from '@/components/scholarships/shared';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import type { AppNotification } from '@/types/auth';

type NotificationType = { kind: string; label: string; enabled: boolean };
type Filters = { filter: string; kind: string };

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
        preferences.put('/notifications/preferences', { preserveScroll: true });
    };

    return (
        <>
            <Head title="Notifications" />
            <PageBody>
                <PageHeader
                    icon={Bell}
                    title="Notifications"
                    description="Les signalements et confirmations des étudiants dans votre périmètre."
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
                    <section
                        className="surface overflow-hidden"
                        aria-label="Liste des notifications"
                    >
                        <div className="flex flex-wrap items-center justify-between gap-3 border-b p-4">
                            <div
                                className="flex flex-wrap gap-1"
                                aria-label="Filtrer par état"
                            >
                                {[
                                    ['', 'Toutes'],
                                    ['unread', 'Non lues'],
                                    ['read', 'Lues'],
                                ].map(([value, label]) => (
                                    <Button
                                        key={value}
                                        size="sm"
                                        variant={
                                            filters.filter === value
                                                ? 'default'
                                                : 'ghost'
                                        }
                                        aria-pressed={filters.filter === value}
                                        onClick={() =>
                                            filter({ filter: value })
                                        }
                                    >
                                        {label}
                                        {value === 'unread'
                                            ? ` (${notifications.unread})`
                                            : ''}
                                    </Button>
                                ))}
                            </div>
                            <div className="flex items-center gap-2">
                                <Label htmlFor="notification-kind">Type</Label>
                                <select
                                    id="notification-kind"
                                    className="h-9 max-w-full min-w-0 rounded-md border bg-card px-2 text-sm focus-visible:ring-2 focus-visible:ring-ring"
                                    value={filters.kind}
                                    onChange={(event) =>
                                        filter({ kind: event.target.value })
                                    }
                                >
                                    <option value="">Tous les types</option>
                                    {types.map((type) => (
                                        <option
                                            key={type.kind}
                                            value={type.kind}
                                        >
                                            {type.label}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        </div>
                        {entries.data.length === 0 ? (
                            <EmptyState
                                illustration="done"
                                title="Aucune notification"
                                description={
                                    filters.filter || filters.kind
                                        ? 'Aucune notification ne correspond à ces filtres.'
                                        : 'Les prochaines demandes de correction et confirmations apparaîtront ici.'
                                }
                            />
                        ) : (
                            <ul className="divide-y">
                                {entries.data.map((item) => {
                                    const correction =
                                        item.data.kind ===
                                        'correction_requested';
                                    const Icon = correction
                                        ? FilePenLine
                                        : BadgeCheck;
                                    return (
                                        <li
                                            key={item.id}
                                            className={cn(
                                                'flex flex-wrap items-start gap-3 p-4 sm:p-5',
                                                !item.read_at && 'bg-accent/30',
                                            )}
                                        >
                                            <span
                                                className={cn(
                                                    'flex size-10 shrink-0 items-center justify-center rounded-xl',
                                                    correction
                                                        ? 'bg-warning-soft text-warning-foreground'
                                                        : 'bg-success-soft text-success-foreground',
                                                )}
                                                aria-hidden="true"
                                            >
                                                <Icon className="size-5" />
                                            </span>
                                            <div className="min-w-0 flex-1 space-y-1">
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <p className="text-sm font-semibold">
                                                        {item.data.title}
                                                    </p>
                                                    {!item.read_at && (
                                                        <span className="rounded-full bg-primary/10 px-2 py-0.5 text-xs font-semibold text-primary">
                                                            Non lue
                                                        </span>
                                                    )}
                                                </div>
                                                <p className="text-sm text-muted-foreground">
                                                    {item.data.message}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    <time
                                                        dateTime={
                                                            item.created_at
                                                        }
                                                    >
                                                        {formatDate(
                                                            item.created_at,
                                                            true,
                                                        )}
                                                    </time>
                                                </p>
                                                <div className="flex flex-wrap gap-2 pt-2">
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        asChild
                                                    >
                                                        <Link
                                                            href={`/notifications/${item.id}/open`}
                                                            method="post"
                                                            as="button"
                                                        >
                                                            {correction
                                                                ? 'Voir la demande'
                                                                : 'Voir la fiche étudiant'}
                                                        </Link>
                                                    </Button>
                                                    <Button
                                                        size="sm"
                                                        variant="ghost"
                                                        onClick={() =>
                                                            router.patch(
                                                                `/notifications/${item.id}`,
                                                                {
                                                                    read: !item.read_at,
                                                                },
                                                                {
                                                                    preserveScroll: true,
                                                                },
                                                            )
                                                        }
                                                    >
                                                        {item.read_at ? (
                                                            <Mail aria-hidden="true" />
                                                        ) : (
                                                            <MailOpen aria-hidden="true" />
                                                        )}
                                                        {item.read_at
                                                            ? 'Marquer comme non lue'
                                                            : 'Marquer comme lue'}
                                                    </Button>
                                                </div>
                                            </div>
                                        </li>
                                    );
                                })}
                            </ul>
                        )}
                        <div className="flex flex-wrap items-center justify-between gap-3 border-t p-4">
                            <p className="text-sm text-muted-foreground">
                                {entries.total} notification
                                {entries.total > 1 ? 's' : ''}
                            </p>
                            <Pagination links={entries.links} />
                        </div>
                    </section>
                    <section
                        className="surface p-5"
                        aria-labelledby="notification-preferences"
                    >
                        <h2
                            id="notification-preferences"
                            className="flex items-center gap-2 font-heading font-bold"
                        >
                            <Settings2 className="size-4" aria-hidden="true" />
                            Mes préférences
                        </h2>
                        <p className="mt-2 text-sm text-muted-foreground">
                            Choisissez les événements à recevoir dans votre
                            cloche. Ce réglage concerne uniquement votre compte
                            et les prochaines notifications.
                        </p>
                        <form onSubmit={save} className="mt-5 space-y-4">
                            {types.map((type) => (
                                <div
                                    key={type.kind}
                                    className="flex items-start gap-3"
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
                                                          ...preferences.data
                                                              .enabled,
                                                          type.kind,
                                                      ]
                                                    : preferences.data.enabled.filter(
                                                          (kind) =>
                                                              kind !==
                                                              type.kind,
                                                      ),
                                            )
                                        }
                                    />
                                    <Label
                                        htmlFor={`notification-${type.kind}`}
                                        className="leading-5"
                                    >
                                        {type.label}
                                    </Label>
                                </div>
                            ))}
                            {Object.values(preferences.errors).map((error) => (
                                <p
                                    key={error}
                                    role="alert"
                                    className="text-sm text-destructive"
                                >
                                    {error}
                                </p>
                            ))}
                            <Button
                                type="submit"
                                disabled={preferences.processing}
                                className="w-full"
                            >
                                {preferences.processing
                                    ? 'Enregistrement…'
                                    : 'Enregistrer mes préférences'}
                            </Button>
                        </form>
                        <p className="mt-4 text-xs text-muted-foreground">
                            Les notifications lues sont conservées 90 jours. Les
                            éléments « À traiter » restent visibles tant que le
                            travail attend une action.
                        </p>
                    </section>
                </div>
            </PageBody>
        </>
    );
}
