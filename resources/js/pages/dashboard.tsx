import { Head, Link, usePage } from '@inertiajs/react';
import {
    Activity as ActivityIcon,
    AlertTriangle,
    ArrowRight,
    Building2,
    CalendarCheck,
    CalendarRange,
    ChartColumn,
    FileCheck2,
    FilePenLine,
    FileUp,
    FolderClock,
    FolderOpen,
    GraduationCap,
    Inbox,
    Layers,
    Route,
    ServerCog,
    UserCheck,
    UserCog,
    Users,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { EmptyState } from '@/components/empty-state';
import { StudentsIllustration } from '@/components/illustrations';
import { ToneBadge, toneFor } from '@/components/tone-badge';
import type { Tone } from '@/components/tone-badge';
import {
    applicationStatusLabels,
    BarList,
    formatNumber,
    KpiCard,
    Panel,
    YearLine,
    YearSelect,
} from '@/components/reporting/shared';
import type { BarItem, Scope, Year } from '@/components/reporting/shared';
import { formatDate } from '@/components/scholarships/shared';
import { dashboard } from '@/routes';

type Kpi = {
    key: string;
    label: string;
    value: number;
    hint: string;
    href: string | null;
};
type Activity = {
    id: number;
    uuid: string;
    at: string | null;
    actor: string;
    module_label: string;
    event_label: string;
};
type DashboardData = {
    scope: Scope;
    kpis: Kpi[];
    watch: { label: string; value: number; href: string | null }[];
    charts: {
        students_by_level: BarItem[] | null;
        students_by_program: BarItem[] | null;
        students_by_establishment: BarItem[] | null;
        scholarships_by_status: Record<string, number> | null;
        scholarships_by_establishment: BarItem[] | null;
    };
    confirmations: {
        confirmed: number;
        stale: number;
        never_confirmed: number;
        total: number;
    } | null;
    imports: {
        imports: number;
        failed: number;
        with_anomalies: number;
        recent: {
            id: number;
            uuid: string;
            filename: string;
            establishment: string;
            status: string;
            total_rows: number;
            rejected_rows: number;
            declared_rows: number;
            created_at: string;
        }[];
    } | null;
    campaigns: {
        year: { id: number; name: string; status: string }[];
        open: { id: number; name: string }[];
    } | null;
    technical: {
        failed_jobs: number | null;
        failed_imports: number;
        audit_entries_24h: number;
        last_audit_at: string | null;
    } | null;
    activity: Activity[] | null;
};

const importStatusLabels: Record<string, string> = {
    uploaded: 'Fichier reçu',
    analyzing: 'Analyse en cours',
    ready: 'Prêt à confirmer',
    committing: 'Import en cours',
    completed: 'Terminé',
    failed: 'Échec',
    cancelled: 'Annulé',
};

const kpiStyles: Record<string, { icon: LucideIcon; tone: Tone }> = {
    active_students: { icon: Users, tone: 'primary' },
    active_declarations: { icon: FileCheck2, tone: 'info' },
    establishments: { icon: Building2, tone: 'neutral' },
    applications: { icon: FolderOpen, tone: 'primary' },
    applications_pending: { icon: FolderClock, tone: 'warning' },
    applications_today: { icon: Inbox, tone: 'success' },
    corrections_pending: { icon: FilePenLine, tone: 'warning' },
    links_pending: { icon: UserCheck, tone: 'info' },
    imports_anomalies: { icon: FileUp, tone: 'danger' },
    internal_users: { icon: UserCog, tone: 'neutral' },
};

function SeeAll({ href, children }: { href: string; children: string }) {
    return (
        <Link
            href={href}
            className="inline-flex items-center gap-1 rounded-full px-3 py-1 text-sm font-medium text-primary transition-colors hover:bg-accent"
        >
            {children}
            <ArrowRight className="size-4" aria-hidden="true" />
        </Link>
    );
}

export default function Dashboard({
    dashboard: data,
    years,
}: {
    dashboard: DashboardData;
    years: Year[];
}) {
    const { auth } = usePage().props;
    const { scope, charts } = data;
    const statusItems: BarItem[] | null = charts.scholarships_by_status
        ? Object.entries(charts.scholarships_by_status).map(
              ([status, value]) => ({
                  label: applicationStatusLabels[status] ?? status,
                  value,
                  tone: toneFor(status),
              }),
          )
        : null;
    const firstName = auth.user.name.split(' ')[0];
    const today = new Intl.DateTimeFormat('fr-FR', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    }).format(new Date());
    const hasCharts =
        charts.students_by_level ||
        charts.students_by_program ||
        charts.students_by_establishment ||
        statusItems ||
        charts.scholarships_by_establishment;

    return (
        <>
            <Head title="Tableau de bord" />
            <div className="space-y-6 p-4 md:p-6 lg:p-8">
                <section className="relative overflow-hidden rounded-2xl bg-sidebar p-6 text-white shadow-elevated ring-1 ring-white/10 sm:p-8 dark:bg-linear-to-br dark:from-accent dark:to-sidebar">
                    <div
                        className="bg-grid absolute inset-0 text-white opacity-30"
                        aria-hidden="true"
                    />
                    <div
                        className="absolute -top-24 right-1/4 size-72 rounded-full bg-primary/40 blur-3xl"
                        aria-hidden="true"
                    />
                    <div className="relative flex flex-col gap-6 md:flex-row md:items-center md:justify-between">
                        <div className="space-y-3">
                            <p
                                className="text-sm font-medium text-white/60 first-letter:uppercase"
                                suppressHydrationWarning
                            >
                                {today}
                            </p>
                            <h1 className="font-heading text-2xl font-extrabold tracking-tight sm:text-3xl">
                                Bienvenue, {firstName}
                            </h1>
                            <p className="max-w-xl text-sm text-white/70">
                                Suivi des étudiants déclarés, des demandes
                                étudiantes et des dossiers papier de bourse
                                {scope.global
                                    ? ' pour l’ensemble des établissements.'
                                    : ` de ${scope.establishment ?? 'votre établissement'}.`}
                            </p>
                            <div className="flex flex-wrap items-center gap-2 pt-1 text-sm">
                                {scope.year ? (
                                    <span className="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-3 py-1 font-medium ring-1 ring-white/15">
                                        <CalendarCheck
                                            className="size-4 text-brand-gold"
                                            aria-hidden="true"
                                        />
                                        Année {scope.year.name}
                                        {!scope.year.is_current &&
                                            ' (non courante)'}
                                    </span>
                                ) : (
                                    <span className="inline-flex items-center gap-1.5 rounded-full bg-warning px-3 py-1 font-semibold text-sidebar">
                                        <AlertTriangle
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        Aucune année courante
                                    </span>
                                )}
                                {auth.roleLabel && (
                                    <span className="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-3 py-1 font-medium ring-1 ring-white/15">
                                        <GraduationCap
                                            className="size-4 text-brand-gold"
                                            aria-hidden="true"
                                        />
                                        {auth.roleLabel}
                                    </span>
                                )}
                                {scope.global && (
                                    <div className="w-full sm:w-auto [&_button]:border-white/20 [&_button]:bg-white/10 [&_button]:text-white [&_svg]:text-white/70">
                                        <YearSelect
                                            years={years}
                                            value={scope.year?.id ?? null}
                                            path="/dashboard"
                                        />
                                    </div>
                                )}
                            </div>
                        </div>
                        <StudentsIllustration className="hidden h-44 w-auto shrink-0 md:block" />
                    </div>
                </section>

                {!scope.year && <YearLine scope={scope} />}

                {data.kpis.length > 0 && (
                    <div className="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4">
                        {data.kpis.map((kpi) => (
                            <KpiCard
                                key={kpi.key}
                                label={kpi.label}
                                value={kpi.value}
                                hint={kpi.hint}
                                href={kpi.href}
                                icon={kpiStyles[kpi.key]?.icon ?? ChartColumn}
                                tone={kpiStyles[kpi.key]?.tone ?? 'primary'}
                            />
                        ))}
                    </div>
                )}

                {hasCharts && (
                    <div className="grid gap-4 lg:grid-cols-2">
                        {charts.students_by_level && (
                            <Panel
                                icon={Layers}
                                title="Étudiants par niveau"
                                description="Déclarations actives de l’année"
                            >
                                <BarList
                                    label="Étudiants par niveau"
                                    items={charts.students_by_level}
                                    empty="Aucune déclaration active pour cette année."
                                />
                            </Panel>
                        )}
                        {charts.students_by_program && (
                            <Panel
                                icon={Route}
                                title="Étudiants par parcours"
                                description="Déclarations actives de l’année"
                            >
                                <BarList
                                    label="Étudiants par parcours"
                                    items={charts.students_by_program}
                                    empty="Aucune déclaration active pour cette année."
                                />
                            </Panel>
                        )}
                        {charts.students_by_establishment && (
                            <Panel
                                icon={Building2}
                                title="Étudiants par établissement"
                                description="Déclarations actives de l’année"
                            >
                                <BarList
                                    label="Étudiants par établissement"
                                    items={charts.students_by_establishment}
                                    empty="Aucune déclaration active pour cette année."
                                />
                            </Panel>
                        )}
                        {statusItems && (
                            <Panel
                                icon={FolderOpen}
                                title="Dossiers de bourse par statut"
                                description="Campagnes de l’année"
                            >
                                <BarList
                                    label="Dossiers de bourse par statut"
                                    items={statusItems}
                                    empty="Aucun dossier."
                                />
                            </Panel>
                        )}
                        {charts.scholarships_by_establishment && (
                            <Panel
                                icon={Building2}
                                title="Dossiers de bourse par établissement"
                                description="Campagnes de l’année"
                            >
                                <BarList
                                    label="Dossiers de bourse par établissement"
                                    items={charts.scholarships_by_establishment}
                                    empty="Aucun dossier reçu pour les campagnes de cette année."
                                />
                            </Panel>
                        )}
                    </div>
                )}

                <div className="grid gap-4 lg:grid-cols-3">
                    <Panel
                        icon={AlertTriangle}
                        title="À surveiller"
                        description="Points d’attention dans votre périmètre"
                    >
                        {data.watch.length === 0 ? (
                            <EmptyState
                                compact
                                illustration="done"
                                title="Rien en attente"
                                description="Aucun point d’attention dans votre périmètre."
                            />
                        ) : (
                            <ul className="-mx-2 space-y-1 text-sm">
                                {data.watch.map((item) => {
                                    const content = (
                                        <>
                                            <span className="min-w-0 flex-1 truncate">
                                                {item.label}
                                            </span>
                                            <span
                                                className={`rounded-full px-2.5 py-0.5 text-xs font-bold tabular-nums ${item.value > 0 ? 'bg-warning-soft text-warning-foreground' : 'bg-secondary text-muted-foreground'}`}
                                            >
                                                {formatNumber(item.value)}
                                            </span>
                                        </>
                                    );
                                    return (
                                        <li key={item.label}>
                                            {item.href ? (
                                                <Link
                                                    href={item.href}
                                                    className="flex items-center gap-3 rounded-lg px-2 py-2 transition-colors hover:bg-muted"
                                                >
                                                    {content}
                                                </Link>
                                            ) : (
                                                <span className="flex items-center gap-3 px-2 py-2">
                                                    {content}
                                                </span>
                                            )}
                                        </li>
                                    );
                                })}
                            </ul>
                        )}
                    </Panel>
                    {data.confirmations && (
                        <Panel
                            icon={UserCheck}
                            title="Confirmations étudiantes"
                            description={`${formatNumber(data.confirmations.total)} étudiant(s) avec une déclaration active`}
                        >
                            <ConfirmationMeter {...data.confirmations} />
                        </Panel>
                    )}
                    {data.campaigns && (
                        <Panel
                            icon={CalendarRange}
                            title="Campagnes de bourse"
                            description={`${data.campaigns.year.length} campagne(s) pour l’année sélectionnée`}
                        >
                            {data.campaigns.open.length === 0 ? (
                                <EmptyState
                                    compact
                                    illustration="scholarship"
                                    title="Aucune campagne ouverte"
                                    description="La réception des dossiers papier est fermée."
                                />
                            ) : (
                                <ul className="space-y-2 text-sm">
                                    {data.campaigns.open.map((campaign) => (
                                        <li
                                            key={campaign.id}
                                            className="flex items-center justify-between gap-2 rounded-lg border bg-muted/40 px-3 py-2.5"
                                        >
                                            <span className="min-w-0 truncate font-medium">
                                                {campaign.name}
                                            </span>
                                            <ToneBadge tone="success">
                                                Ouverte
                                            </ToneBadge>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </Panel>
                    )}
                    {data.technical && (
                        <Panel
                            icon={ServerCog}
                            title="Indicateurs techniques"
                            description="Compteurs uniquement, sans contenu"
                        >
                            <dl className="grid grid-cols-2 gap-3">
                                {[
                                    {
                                        label: 'Jobs en échec',
                                        value:
                                            data.technical.failed_jobs ?? '—',
                                        alert:
                                            (data.technical.failed_jobs ?? 0) >
                                            0,
                                    },
                                    {
                                        label: 'Imports en échec',
                                        value: data.technical.failed_imports,
                                        alert:
                                            data.technical.failed_imports > 0,
                                    },
                                    {
                                        label: 'Audit (24 h)',
                                        value: data.technical.audit_entries_24h,
                                        alert: false,
                                    },
                                    {
                                        label: 'Dernier audit',
                                        value: formatDate(
                                            data.technical.last_audit_at,
                                            true,
                                        ),
                                        alert: false,
                                    },
                                ].map((item) => (
                                    <div
                                        key={item.label}
                                        className="rounded-lg border bg-muted/40 p-3"
                                    >
                                        <dt className="text-xs text-muted-foreground">
                                            {item.label}
                                        </dt>
                                        <dd
                                            className={`mt-1 font-semibold tabular-nums ${item.alert ? 'text-danger' : 'text-foreground'} ${typeof item.value === 'string' ? 'text-xs' : 'font-heading text-xl'}`}
                                        >
                                            {item.value}
                                        </dd>
                                    </div>
                                ))}
                            </dl>
                        </Panel>
                    )}
                </div>

                {data.imports && (
                    <Panel
                        icon={FileUp}
                        title="Imports récents"
                        description={`${data.imports.imports} import(s) pour l’année, dont ${data.imports.with_anomalies} avec anomalies et ${data.imports.failed} en échec`}
                        action={
                            <SeeAll href="/imports">Tous les imports</SeeAll>
                        }
                    >
                        {data.imports.recent.length === 0 ? (
                            <EmptyState
                                compact
                                illustration="upload"
                                title="Aucun import pour le moment"
                                description="Les listes d’étudiants importées par les établissements apparaîtront ici."
                            />
                        ) : (
                            <div className="-mx-5 overflow-x-auto border-t sm:-mx-6">
                                <table className="data-table min-w-[36rem]">
                                    <thead>
                                        <tr>
                                            <th>Fichier</th>
                                            {scope.global && (
                                                <th>Établissement</th>
                                            )}
                                            <th>Statut</th>
                                            <th className="text-right">
                                                Lignes
                                            </th>
                                            <th className="text-right">
                                                Rejetées
                                            </th>
                                            <th className="text-right">
                                                Importées
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {data.imports.recent.map((item) => (
                                            <tr key={item.id}>
                                                <td className="max-w-56">
                                                    <Link
                                                        href={`/imports/${item.uuid}`}
                                                        className="block truncate font-medium text-foreground hover:text-primary"
                                                    >
                                                        {item.filename}
                                                    </Link>
                                                    <span className="block text-xs text-muted-foreground">
                                                        {formatDate(
                                                            item.created_at,
                                                        )}
                                                    </span>
                                                </td>
                                                {scope.global && (
                                                    <td>
                                                        {item.establishment}
                                                    </td>
                                                )}
                                                <td>
                                                    <ToneBadge
                                                        tone={toneFor(
                                                            item.status,
                                                        )}
                                                    >
                                                        {importStatusLabels[
                                                            item.status
                                                        ] ?? item.status}
                                                    </ToneBadge>
                                                </td>
                                                <td className="text-right tabular-nums">
                                                    {item.total_rows}
                                                </td>
                                                <td
                                                    className={`text-right tabular-nums ${item.rejected_rows > 0 ? 'font-semibold text-danger' : ''}`}
                                                >
                                                    {item.rejected_rows}
                                                </td>
                                                <td className="text-right font-semibold tabular-nums">
                                                    {item.declared_rows}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </Panel>
                )}

                {data.activity && (
                    <Panel
                        icon={ActivityIcon}
                        title="Activité récente"
                        description="Dernières actions tracées sur la plateforme"
                        action={<SeeAll href="/audits">Journal d’audit</SeeAll>}
                    >
                        {data.activity.length === 0 ? (
                            <EmptyState
                                compact
                                illustration="inbox"
                                title="Aucune activité enregistrée"
                            />
                        ) : (
                            <ol className="relative space-y-1 text-sm before:absolute before:top-2 before:bottom-2 before:left-[7px] before:w-px before:bg-border">
                                {data.activity.map((item) => (
                                    <li
                                        key={item.id}
                                        className="relative flex flex-wrap items-baseline justify-between gap-x-4 gap-y-0.5 py-2 pl-7"
                                    >
                                        <span
                                            className="absolute top-3.5 left-0 size-[15px] rounded-full border-[3px] border-card bg-primary ring-1 ring-primary/30"
                                            aria-hidden="true"
                                        />
                                        <span className="min-w-0">
                                            <Link
                                                href={`/audits/${item.uuid}`}
                                                className="font-semibold text-foreground hover:text-primary"
                                            >
                                                {item.event_label}
                                            </Link>
                                            <span className="block text-xs text-muted-foreground sm:inline">
                                                <span className="hidden sm:inline">
                                                    {' · '}
                                                </span>
                                                {item.module_label} ·{' '}
                                                {item.actor}
                                            </span>
                                        </span>
                                        <time className="text-xs text-muted-foreground tabular-nums">
                                            {formatDate(item.at, true)}
                                        </time>
                                    </li>
                                ))}
                            </ol>
                        )}
                    </Panel>
                )}
            </div>
        </>
    );
}

/** Stacked meter of the three confirmation states, each value also written out. */
function ConfirmationMeter({
    confirmed,
    stale,
    never_confirmed,
    total,
}: NonNullable<DashboardData['confirmations']>) {
    if (total === 0)
        return (
            <EmptyState
                compact
                illustration="students"
                title="Aucun étudiant déclaré"
            />
        );
    const parts = [
        {
            label: 'Confirmées et à jour',
            value: confirmed,
            color: 'bg-success',
        },
        { label: 'Devenues obsolètes', value: stale, color: 'bg-warning' },
        {
            label: 'Jamais confirmées',
            value: never_confirmed,
            color: 'bg-muted-foreground/40',
        },
    ];
    const rate = Math.round((confirmed / total) * 100);
    return (
        <div className="space-y-4">
            <p className="flex items-baseline gap-2">
                <span className="font-heading text-3xl font-extrabold text-foreground tabular-nums">
                    {rate} %
                </span>
                <span className="text-sm text-muted-foreground">
                    confirmées et à jour
                </span>
            </p>
            <div
                className="flex h-3 overflow-hidden rounded-full bg-muted"
                aria-hidden="true"
            >
                {parts.map((part) => (
                    <div
                        key={part.label}
                        className={part.color}
                        style={{ width: `${(part.value / total) * 100}%` }}
                    />
                ))}
            </div>
            <ul className="space-y-2 text-sm">
                {parts.map((part) => (
                    <li
                        key={part.label}
                        className="flex items-center justify-between gap-3"
                    >
                        <span className="flex items-center gap-2">
                            <span
                                className={`size-2.5 rounded-full ${part.color}`}
                                aria-hidden="true"
                            />
                            {part.label}
                        </span>
                        <span className="font-semibold tabular-nums">
                            {formatNumber(part.value)}
                        </span>
                    </li>
                ))}
            </ul>
        </div>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Tableau de bord',
            href: dashboard(),
        },
    ],
};
