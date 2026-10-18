import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    Activity as ActivityIcon,
    AlertTriangle,
    ArrowRight,
    Building2,
    ChartColumn,
    FileCheck2,
    FilePenLine,
    FileUp,
    FolderClock,
    FolderOpen,
    Inbox,
    Layers,
    RefreshCw,
    TrendingUp,
    Route,
    ServerCog,
    UserCheck,
    UserCog,
    Users,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { EmptyState } from '@/components/empty-state';
import { useState } from 'react';
import type { ComponentProps } from 'react';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import {
    DistributionChart,
    DonutChart,
    ReceptionTrendChart,
} from '@/components/reporting/charts';
import type { DailyPoint } from '@/components/reporting/charts';
import { ToneBadge, toneFor } from '@/components/tone-badge';
import type { Tone } from '@/components/tone-badge';
import {
    applicationStatusLabels,
    formatNumber,
    KpiCard,
    Panel as BasePanel,
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
        scholarships_daily: DailyPoint[] | null;
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

function Panel(props: ComponentProps<typeof BasePanel>) {
    return <BasePanel {...props} compact />;
}

export default function Dashboard({
    dashboard: data,
    years,
}: {
    dashboard: DashboardData;
    years: Year[];
}) {
    const { auth } = usePage().props;
    const [refreshing, setRefreshing] = useState(false);
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
    return (
        <>
            <Head title="Tableau de bord" />
            <div className="space-y-5 p-4 md:p-6">
                <PageHeader
                    icon={ChartColumn}
                    title="Tableau de bord"
                    description={`Bonjour, ${firstName}. Suivez les étudiants et le traitement des dossiers de bourse.`}
                    meta={
                        <p className="text-xs text-muted-foreground">
                            {scope.global
                                ? 'Vue globale'
                                : (scope.establishment ??
                                  'Votre établissement')}
                            {auth.roleLabel && ` · ${auth.roleLabel}`}
                            <span
                                className="ml-2 hidden sm:inline"
                                suppressHydrationWarning
                            >
                                · {today}
                            </span>
                        </p>
                    }
                    actions={
                        <div className="flex w-full items-center gap-2 sm:w-auto">
                            {scope.global ? (
                                <div className="min-w-0 flex-1 sm:flex-none">
                                    <YearSelect
                                        years={years}
                                        value={scope.year?.id ?? null}
                                        path="/dashboard"
                                    />
                                </div>
                            ) : (
                                scope.year && (
                                    <span className="rounded-lg border bg-card px-3 py-2 text-sm font-medium">
                                        {scope.year.name}
                                    </span>
                                )
                            )}
                            <Button
                                type="button"
                                variant="outline"
                                size="icon"
                                aria-label="Actualiser le tableau de bord"
                                title="Actualiser les données"
                                disabled={refreshing}
                                onClick={() =>
                                    router.reload({
                                        only: ['dashboard'],
                                        onStart: () => setRefreshing(true),
                                        onFinish: () => setRefreshing(false),
                                    })
                                }
                            >
                                <RefreshCw
                                    className={
                                        refreshing
                                            ? 'size-4 animate-spin motion-reduce:animate-none'
                                            : 'size-4'
                                    }
                                    aria-hidden="true"
                                />
                            </Button>
                        </div>
                    }
                />

                {!scope.year && <YearLine scope={scope} />}

                {data.kpis.length > 0 && (
                    <div className="grid grid-cols-2 gap-3 xl:grid-cols-4">
                        {data.kpis.map((kpi) => (
                            <KpiCard
                                compact
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

                <div
                    className={`grid gap-4 lg:grid-cols-2 ${charts.scholarships_daily && statusItems ? '2xl:grid-cols-3' : ''}`}
                >
                    {charts.students_by_level && (
                        <Panel
                            icon={Layers}
                            title="Étudiants par niveau"
                            description="Répartition des situations actives de l’année"
                        >
                            <DistributionChart
                                label="Étudiants par niveau"
                                items={charts.students_by_level}
                                empty="Aucune situation active pour cette année."
                            />
                        </Panel>
                    )}
                    {statusItems ? (
                        <Panel
                            icon={FolderOpen}
                            title="Traitement des dossiers"
                            description="Répartition par statut · année sélectionnée"
                        >
                            <DonutChart
                                label="Dossiers de bourse par statut"
                                items={statusItems}
                                unit="dossiers"
                                empty="Aucun dossier reçu pour cette année."
                            />
                        </Panel>
                    ) : (
                        data.confirmations && (
                            <Panel
                                icon={UserCheck}
                                title="Confirmations étudiantes"
                                description={`${formatNumber(data.confirmations.total)} étudiant(s) avec une situation active`}
                            >
                                <ConfirmationChart {...data.confirmations} />
                            </Panel>
                        )
                    )}
                    {charts.scholarships_daily && (
                        <Panel
                            icon={TrendingUp}
                            title="Évolution des réceptions"
                            description="30 derniers jours · année sélectionnée"
                            className={
                                charts.students_by_level && statusItems
                                    ? 'lg:col-span-2 2xl:col-span-1'
                                    : undefined
                            }
                        >
                            <ReceptionTrendChart
                                points={charts.scholarships_daily}
                            />
                        </Panel>
                    )}
                </div>

                {(charts.students_by_program ||
                    charts.students_by_establishment ||
                    charts.scholarships_by_establishment) && (
                    <div className="grid gap-4 lg:grid-cols-2">
                        {charts.students_by_program && (
                            <Panel
                                icon={Route}
                                title="Étudiants par parcours"
                                description="Situations actives de l’année"
                            >
                                <DistributionChart
                                    horizontal
                                    label="Étudiants par parcours"
                                    items={charts.students_by_program}
                                    empty="Aucune situation active pour cette année."
                                />
                            </Panel>
                        )}
                        {charts.students_by_establishment && (
                            <Panel
                                icon={Building2}
                                title="Étudiants par établissement"
                                description="Situations actives de l’année"
                            >
                                <DistributionChart
                                    horizontal
                                    label="Étudiants par établissement"
                                    items={charts.students_by_establishment}
                                    empty="Aucune situation active pour cette année."
                                />
                            </Panel>
                        )}
                        {charts.scholarships_by_establishment && (
                            <Panel
                                icon={Building2}
                                title="Dossiers par établissement"
                                description="Dossiers reçus pour l’année sélectionnée"
                            >
                                <DistributionChart
                                    horizontal
                                    label="Dossiers de bourse par établissement"
                                    items={charts.scholarships_by_establishment}
                                    empty="Aucun dossier reçu pour cette année universitaire."
                                />
                            </Panel>
                        )}
                    </div>
                )}

                <div
                    className={`grid gap-4 lg:grid-cols-2 ${data.technical && statusItems && data.confirmations ? '2xl:grid-cols-3' : ''}`}
                >
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
                    {statusItems && data.confirmations && (
                        <Panel
                            icon={UserCheck}
                            title="Confirmations étudiantes"
                            description={`${formatNumber(data.confirmations.total)} étudiant(s) avec une situation active`}
                        >
                            <ConfirmationChart {...data.confirmations} />
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
                            <div className="-mx-4 overflow-x-auto border-t">
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

function ConfirmationChart({
    confirmed,
    stale,
    never_confirmed,
    total,
}: NonNullable<DashboardData['confirmations']>) {
    return (
        <DonutChart
            label="État des confirmations étudiantes"
            items={[
                { label: 'À jour', value: confirmed, tone: 'success' },
                { label: 'Obsolètes', value: stale, tone: 'warning' },
                {
                    label: 'Jamais confirmées',
                    value: never_confirmed,
                    tone: 'neutral',
                },
            ]}
            unit="étudiants"
            empty="Aucun étudiant avec une situation active pour cette année."
            summary={{
                value:
                    confirmed > 0 && confirmed / total < 0.01
                        ? '< 1 %'
                        : `${total > 0 ? Math.round((confirmed / total) * 100) : 0} %`,
                label: 'confirmées à jour',
            }}
        />
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
