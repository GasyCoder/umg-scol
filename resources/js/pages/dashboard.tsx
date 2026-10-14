import { Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
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
import { Badge } from '@/components/ui/badge';
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

export default function Dashboard({
    dashboard: data,
    years,
}: {
    dashboard: DashboardData;
    years: Year[];
}) {
    const { scope, charts } = data;
    const statusItems: BarItem[] | null = charts.scholarships_by_status
        ? Object.entries(charts.scholarships_by_status).map(
              ([status, value]) => ({
                  label: applicationStatusLabels[status] ?? status,
                  value,
              }),
          )
        : null;
    return (
        <>
            <Head title="Tableau de bord" />
            <div className="space-y-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div className="space-y-2">
                        <Heading
                            title="Tableau de bord"
                            description="Suivi des étudiants déclarés, des demandes étudiantes et des dossiers papier de bourse."
                        />
                        <YearLine scope={scope} />
                    </div>
                    {scope.global && (
                        <YearSelect
                            years={years}
                            value={scope.year?.id ?? null}
                            path="/dashboard"
                        />
                    )}
                </div>

                {data.kpis.length > 0 && (
                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        {data.kpis.map((kpi) => (
                            <KpiCard
                                key={kpi.key}
                                label={kpi.label}
                                value={kpi.value}
                                hint={kpi.hint}
                                href={kpi.href}
                            />
                        ))}
                    </div>
                )}

                <div className="grid gap-4 lg:grid-cols-2">
                    {charts.students_by_level && (
                        <Panel
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

                <div className="grid gap-4 lg:grid-cols-3">
                    <Panel title="À surveiller">
                        {data.watch.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Rien en attente dans votre périmètre.
                            </p>
                        ) : (
                            <ul className="divide-y text-sm">
                                {data.watch.map((item) => (
                                    <li
                                        key={item.label}
                                        className="flex items-center justify-between gap-3 py-2"
                                    >
                                        {item.href ? (
                                            <Link
                                                href={item.href}
                                                className="underline-offset-4 hover:underline"
                                            >
                                                {item.label}
                                            </Link>
                                        ) : (
                                            <span>{item.label}</span>
                                        )}
                                        <Badge variant="outline">
                                            {formatNumber(item.value)}
                                        </Badge>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Panel>
                    {data.confirmations && (
                        <Panel
                            title="Confirmations étudiantes"
                            description={`${formatNumber(data.confirmations.total)} étudiant(s) avec une déclaration active`}
                        >
                            <BarList
                                label="Confirmations étudiantes"
                                items={[
                                    {
                                        label: 'Confirmées et à jour',
                                        value: data.confirmations.confirmed,
                                    },
                                    {
                                        label: 'Devenues obsolètes',
                                        value: data.confirmations.stale,
                                    },
                                    {
                                        label: 'Jamais confirmées',
                                        value: data.confirmations
                                            .never_confirmed,
                                    },
                                ]}
                                empty="Aucun étudiant déclaré."
                            />
                        </Panel>
                    )}
                    {data.campaigns && (
                        <Panel title="Campagnes de bourse">
                            {data.campaigns.open.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    Aucune campagne ouverte : la réception des
                                    dossiers papier est fermée.
                                </p>
                            ) : (
                                <ul className="space-y-2 text-sm">
                                    {data.campaigns.open.map((campaign) => (
                                        <li
                                            key={campaign.id}
                                            className="flex items-center justify-between gap-2"
                                        >
                                            <span className="min-w-0 truncate">
                                                {campaign.name}
                                            </span>
                                            <Badge>Ouverte</Badge>
                                        </li>
                                    ))}
                                </ul>
                            )}
                            <p className="mt-3 text-xs text-muted-foreground">
                                {data.campaigns.year.length} campagne(s) pour
                                l’année sélectionnée.
                            </p>
                        </Panel>
                    )}
                    {data.technical && (
                        <Panel
                            title="Indicateurs techniques"
                            description="Compteurs uniquement, sans contenu"
                        >
                            <dl className="grid grid-cols-2 gap-3 text-sm">
                                <dt className="text-muted-foreground">
                                    Jobs en échec
                                </dt>
                                <dd className="text-right tabular-nums">
                                    {data.technical.failed_jobs ?? '—'}
                                </dd>
                                <dt className="text-muted-foreground">
                                    Imports en échec
                                </dt>
                                <dd className="text-right tabular-nums">
                                    {data.technical.failed_imports}
                                </dd>
                                <dt className="text-muted-foreground">
                                    Entrées d’audit (24 h)
                                </dt>
                                <dd className="text-right tabular-nums">
                                    {data.technical.audit_entries_24h}
                                </dd>
                                <dt className="text-muted-foreground">
                                    Dernier audit
                                </dt>
                                <dd className="text-right">
                                    {formatDate(
                                        data.technical.last_audit_at,
                                        true,
                                    )}
                                </dd>
                            </dl>
                        </Panel>
                    )}
                </div>

                {data.imports && (
                    <Panel
                        title="Imports récents"
                        description={`${data.imports.imports} import(s) pour l’année, dont ${data.imports.with_anomalies} avec anomalies et ${data.imports.failed} en échec`}
                        action={
                            <Link
                                href="/imports"
                                className="text-sm underline underline-offset-4"
                            >
                                Tous les imports
                            </Link>
                        }
                    >
                        {data.imports.recent.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Aucun import pour le moment.
                            </p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[36rem] text-sm">
                                    <thead className="text-left text-muted-foreground">
                                        <tr>
                                            <th className="py-2 pr-3 font-medium">
                                                Fichier
                                            </th>
                                            {scope.global && (
                                                <th className="py-2 pr-3 font-medium">
                                                    Établissement
                                                </th>
                                            )}
                                            <th className="py-2 pr-3 font-medium">
                                                Statut
                                            </th>
                                            <th className="py-2 pr-3 text-right font-medium">
                                                Lignes
                                            </th>
                                            <th className="py-2 pr-3 text-right font-medium">
                                                Rejetées
                                            </th>
                                            <th className="py-2 text-right font-medium">
                                                Importées
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {data.imports.recent.map((item) => (
                                            <tr key={item.id}>
                                                <td className="max-w-56 truncate py-2 pr-3">
                                                    <Link
                                                        href={`/imports/${item.uuid}`}
                                                        className="underline-offset-4 hover:underline"
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
                                                    <td className="py-2 pr-3">
                                                        {item.establishment}
                                                    </td>
                                                )}
                                                <td className="py-2 pr-3">
                                                    <Badge variant="outline">
                                                        {importStatusLabels[
                                                            item.status
                                                        ] ?? item.status}
                                                    </Badge>
                                                </td>
                                                <td className="py-2 pr-3 text-right tabular-nums">
                                                    {item.total_rows}
                                                </td>
                                                <td className="py-2 pr-3 text-right tabular-nums">
                                                    {item.rejected_rows}
                                                </td>
                                                <td className="py-2 text-right tabular-nums">
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
                        title="Activité récente"
                        action={
                            <Link
                                href="/audits"
                                className="text-sm underline underline-offset-4"
                            >
                                Journal d’audit
                            </Link>
                        }
                    >
                        {data.activity.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Aucune activité enregistrée.
                            </p>
                        ) : (
                            <ul className="divide-y text-sm">
                                {data.activity.map((item) => (
                                    <li
                                        key={item.id}
                                        className="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 py-2"
                                    >
                                        <span className="min-w-0">
                                            <Link
                                                href={`/audits/${item.uuid}`}
                                                className="font-medium underline-offset-4 hover:underline"
                                            >
                                                {item.event_label}
                                            </Link>{' '}
                                            <span className="text-muted-foreground">
                                                · {item.module_label} ·{' '}
                                                {item.actor}
                                            </span>
                                        </span>
                                        <span className="text-xs text-muted-foreground">
                                            {formatDate(item.at, true)}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Panel>
                )}
            </div>
        </>
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
