import { Head, Link } from '@inertiajs/react';
import {
    ArrowLeft,
    ArrowRight,
    Clock,
    EyeOff,
    FileDiff,
    Info,
    ScrollText,
    Tags,
} from 'lucide-react';
import { Panel } from '@/components/reporting/shared';
import { PageBody, PageHeader } from '@/components/page-header';
import { formatDate } from '@/components/scholarships/shared';
import { Button } from '@/components/ui/button';
import { AuditEventBadge, AuditModuleBadge } from '@/pages/audits/index';

type Detail = {
    id: number;
    uuid: string;
    at: string | null;
    actor: string;
    role: string | null;
    module?: string;
    module_label: string;
    event?: string;
    event_label: string;
    subject_type: string | null;
    subject_id: number | string | null;
    metadata: { key: string; value: string }[];
    changes: {
        field: string;
        old: string | null;
        new: string | null;
        hidden: boolean;
    }[];
};

function Hidden() {
    return (
        <span className="inline-flex items-center gap-1.5 text-xs text-muted-foreground italic">
            <EyeOff className="size-3.5" aria-hidden="true" />
            Valeur masquée
        </span>
    );
}

export default function AuditShow({ activity }: { activity: Detail }) {
    const rows: [string, React.ReactNode][] = [
        ['Date et heure', formatDate(activity.at, true)],
        ['Acteur', activity.actor],
        ['Rôle', activity.role ?? '—'],
        [
            'Module',
            <AuditModuleBadge
                key="module"
                module={activity.module}
                label={activity.module_label}
            />,
        ],
        [
            'Événement',
            <AuditEventBadge
                key="event"
                event={activity.event}
                label={activity.event_label}
            />,
        ],
        [
            'Sujet',
            activity.subject_type
                ? `${activity.subject_type} n° ${activity.subject_id}`
                : '—',
        ],
    ];
    return (
        <>
            <Head title={`Audit : ${activity.event_label}`} />
            <PageBody>
                <PageHeader
                    icon={ScrollText}
                    title={activity.event_label}
                    description="Seules les métadonnées administratives autorisées sont affichées."
                    meta={
                        <div className="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                            <AuditModuleBadge
                                module={activity.module}
                                label={activity.module_label}
                            />
                            <span className="inline-flex items-center gap-1.5 tabular-nums">
                                <Clock
                                    className="size-3.5"
                                    aria-hidden="true"
                                />
                                {formatDate(activity.at, true)}
                            </span>
                        </div>
                    }
                    actions={
                        <Button variant="outline" asChild>
                            <Link href="/audits">
                                <ArrowLeft aria-hidden="true" />
                                Retour au journal
                            </Link>
                        </Button>
                    }
                />
                <div className="grid gap-6 lg:grid-cols-2">
                    <Panel icon={Info} title="Événement">
                        <dl className="divide-y text-sm">
                            {rows.map(([label, value]) => (
                                <div
                                    key={label}
                                    className="grid gap-1 py-2.5 first:pt-0 last:pb-0 sm:grid-cols-3 sm:items-center sm:gap-3"
                                >
                                    <dt className="text-muted-foreground">
                                        {label}
                                    </dt>
                                    <dd className="font-medium break-words text-foreground sm:col-span-2">
                                        {value}
                                    </dd>
                                </div>
                            ))}
                        </dl>
                    </Panel>
                    <Panel icon={Tags} title="Métadonnées administratives">
                        {activity.metadata.length === 0 ? (
                            <p className="rounded-lg border border-dashed px-4 py-6 text-center text-sm text-muted-foreground">
                                Aucune métadonnée affichable.
                            </p>
                        ) : (
                            <dl className="divide-y text-sm">
                                {activity.metadata.map((item) => (
                                    <div
                                        key={item.key}
                                        className="grid gap-1 py-2.5 first:pt-0 last:pb-0 sm:grid-cols-3 sm:items-center sm:gap-3"
                                    >
                                        <dt className="font-mono text-xs break-all text-muted-foreground">
                                            {item.key}
                                        </dt>
                                        <dd className="font-medium break-words text-foreground sm:col-span-2">
                                            {item.value}
                                        </dd>
                                    </div>
                                ))}
                            </dl>
                        )}
                    </Panel>
                </div>
                {activity.changes.length > 0 && (
                    <section className="surface overflow-hidden">
                        <div className="flex items-start gap-3 p-5 sm:px-6">
                            <span
                                className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-accent text-primary"
                                aria-hidden="true"
                            >
                                <FileDiff className="size-[18px]" />
                            </span>
                            <div>
                                <h2 className="text-base font-bold text-foreground">
                                    Champs modifiés
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    Les valeurs des champs non administratifs
                                    restent masquées.
                                </p>
                            </div>
                        </div>
                        <div className="overflow-x-auto border-t">
                            <table className="data-table min-w-[36rem]">
                                <caption className="sr-only">
                                    Champs modifiés
                                </caption>
                                <thead>
                                    <tr>
                                        <th scope="col">Champ</th>
                                        <th scope="col">Avant</th>
                                        <th scope="col" className="w-8">
                                            <span className="sr-only">
                                                devient
                                            </span>
                                        </th>
                                        <th scope="col">Après</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {activity.changes.map((change) => (
                                        <tr key={change.field}>
                                            <td className="font-mono text-xs font-medium text-foreground">
                                                {change.field}
                                            </td>
                                            <td className="break-words">
                                                {change.hidden ? (
                                                    <Hidden />
                                                ) : change.old === null ? (
                                                    <span className="text-muted-foreground">
                                                        —
                                                    </span>
                                                ) : (
                                                    <span className="rounded bg-danger-soft px-1.5 py-0.5 text-danger-foreground">
                                                        {change.old}
                                                    </span>
                                                )}
                                            </td>
                                            <td className="text-muted-foreground">
                                                <ArrowRight
                                                    className="size-4"
                                                    aria-hidden="true"
                                                />
                                            </td>
                                            <td className="break-words">
                                                {change.hidden ? (
                                                    <Hidden />
                                                ) : change.new === null ? (
                                                    <span className="text-muted-foreground">
                                                        —
                                                    </span>
                                                ) : (
                                                    <span className="rounded bg-success-soft px-1.5 py-0.5 font-medium text-success-foreground">
                                                        {change.new}
                                                    </span>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </section>
                )}
            </PageBody>
        </>
    );
}

AuditShow.layout = {
    breadcrumbs: [{ title: 'Audit', href: '/audits' }],
};
