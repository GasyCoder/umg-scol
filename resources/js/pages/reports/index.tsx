import { Head, Link } from '@inertiajs/react';
import {
    Building2,
    ChartColumn,
    Download,
    FilePenLine,
    FileUp,
    FolderOpen,
    Table2,
    Users,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { PageBody, PageHeader } from '@/components/page-header';
import {
    applicationStatusLabels,
    BarList,
    correctionStatusLabels,
    formatNumber,
    KpiCard,
    Panel,
    YearLine,
    YearSelect,
} from '@/components/reporting/shared';
import type { BarItem, Scope, Year } from '@/components/reporting/shared';
import { toneFor } from '@/components/tone-badge';
import type { Tone } from '@/components/tone-badge';
import { Button } from '@/components/ui/button';

const correctionTones: Record<string, Tone> = {
    pending: 'warning',
    approved: 'success',
    rejected: 'danger',
    cancelled: 'neutral',
};

type Row = Record<string, string | number>;
type ReportKey = 'students' | 'imports' | 'scholarships';

const reportLabels: Record<ReportKey, string> = {
    students: 'Étudiants',
    imports: 'Imports',
    scholarships: 'Dossiers de bourse',
};

const sum = (rows: Row[] | null, key: string) =>
    (rows ?? []).reduce((total, row) => total + Number(row[key] ?? 0), 0);

/** Aggregates a numeric column by a label column, largest first. */
const groupBy = (rows: Row[], label: string, value: string): BarItem[] =>
    Object.entries(
        rows.reduce<Record<string, number>>((acc, row) => {
            const key = String(row[label] ?? '—');
            acc[key] = (acc[key] ?? 0) + Number(row[value] ?? 0);
            return acc;
        }, {}),
    )
        .map(([name, total]) => ({ label: name, value: total }))
        .sort((a, b) => b.value - a.value);

function ReportTable({
    columns,
    rows,
    empty,
    caption,
}: {
    columns: Record<string, string>;
    rows: Row[];
    empty: string;
    caption: string;
}) {
    if (rows.length === 0)
        return (
            <p className="rounded-lg border border-dashed px-4 py-6 text-center text-sm text-muted-foreground">
                {empty}
            </p>
        );
    const keys = Object.keys(columns);
    const numeric = (key: string) => typeof rows[0][key] === 'number';
    const totals = keys.map((key) =>
        numeric(key)
            ? rows.reduce((total, row) => total + Number(row[key] ?? 0), 0)
            : null,
    );
    return (
        <div className="-mx-5 overflow-x-auto border-t sm:-mx-6">
            <table className="data-table min-w-[40rem]">
                <caption className="sr-only">{caption}</caption>
                <thead>
                    <tr>
                        {keys.map((key) => (
                            <th
                                key={key}
                                scope="col"
                                className={numeric(key) ? 'text-right' : ''}
                            >
                                {columns[key]}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {rows.map((row, index) => (
                        <tr key={index}>
                            {keys.map((key) => (
                                <td
                                    key={key}
                                    className={
                                        numeric(key)
                                            ? `text-right tabular-nums ${key === 'total' ? 'font-semibold text-foreground' : ''}`
                                            : 'text-foreground'
                                    }
                                >
                                    {typeof row[key] === 'number'
                                        ? formatNumber(row[key])
                                        : row[key]}
                                </td>
                            ))}
                        </tr>
                    ))}
                </tbody>
                <tfoot>
                    <tr className="border-t-2 bg-muted/60 font-semibold text-foreground">
                        {keys.map((key, index) => (
                            <td
                                key={key}
                                className={`px-4 py-3 ${totals[index] !== null ? 'text-right tabular-nums' : ''}`}
                            >
                                {index === 0
                                    ? 'Total'
                                    : totals[index] !== null
                                      ? formatNumber(totals[index])
                                      : ''}
                            </td>
                        ))}
                    </tr>
                </tfoot>
            </table>
        </div>
    );
}

export default function ReportsIndex({
    scope,
    years,
    columns,
    students,
    imports,
    corrections,
    scholarships,
    exports,
}: {
    scope: Scope;
    years: Year[];
    columns: Record<ReportKey, Record<string, string>>;
    students: Row[] | null;
    imports: Row[] | null;
    corrections: Record<string, number> | null;
    scholarships: Row[] | null;
    exports: Record<ReportKey, boolean>;
}) {
    // The own-activity report chosen by an account that has both kinds keeps its choice.
    const mine: Record<string, string> =
        scope.own && scope.canChoose ? { scope: 'mine' } : {};
    const exportHref = (report: ReportKey) => {
        const params: Record<string, string> = { ...mine };
        if (scope.year) params.year = String(scope.year.id);
        const query = new URLSearchParams(params).toString();
        return `/reports/${report}/export${query ? `?${query}` : ''}`;
    };
    const exportable = (Object.keys(reportLabels) as ReportKey[]).filter(
        (report) =>
            exports[report] &&
            { students, imports, scholarships }[report] !== null,
    );

    const kpis: {
        key: string;
        label: string;
        value: number;
        hint: string;
        icon: LucideIcon;
        tone: Tone;
    }[] = [];
    if (students) {
        kpis.push({
            key: 'students',
            label: 'Situations actives',
            value: sum(students, 'active'),
            hint: `${formatNumber(sum(students, 'total'))} situation(s) au total`,
            icon: Users,
            tone: 'primary',
        });
    }
    if (imports) {
        kpis.push({
            key: 'imports',
            label: 'Imports',
            value: sum(imports, 'imports'),
            hint: `${formatNumber(sum(imports, 'failed'))} en échec · ${formatNumber(sum(imports, 'rejected_rows'))} ligne(s) rejetée(s)`,
            icon: FileUp,
            tone: sum(imports, 'failed') > 0 ? 'danger' : 'info',
        });
    }
    if (scholarships) {
        kpis.push({
            key: 'scholarships',
            label: 'Dossiers de bourse',
            value: sum(scholarships, 'total'),
            hint: `${formatNumber(sum(scholarships, 'validated'))} validé(s)`,
            icon: FolderOpen,
            tone: 'success',
        });
    }
    if (corrections) {
        kpis.push({
            key: 'corrections',
            label: 'Corrections en attente',
            value: corrections.pending ?? 0,
            hint: `${formatNumber(Object.values(corrections).reduce((a, b) => a + b, 0))} demande(s), toutes périodes`,
            icon: FilePenLine,
            tone: 'warning',
        });
    }

    const scholarshipStatuses: BarItem[] = scholarships
        ? Object.keys(applicationStatusLabels)
              .filter((status) => status in columns.scholarships)
              .map((status) => ({
                  label: applicationStatusLabels[status],
                  value: sum(scholarships, status),
                  tone: toneFor(status),
              }))
        : [];
    const correctionItems: BarItem[] = corrections
        ? Object.entries(corrections).map(([status, value]) => ({
              label: correctionStatusLabels[status] ?? status,
              value,
              tone: correctionTones[status] ?? 'neutral',
          }))
        : [];
    const studentsByEstablishment =
        students && scope.global
            ? groupBy(students, 'establishment', 'active')
            : null;
    const hasScholarshipTotal =
        scholarshipStatuses.reduce((total, item) => total + item.value, 0) > 0;

    return (
        <>
            <Head title="Rapports" />
            <PageBody>
                <PageHeader
                    icon={ChartColumn}
                    title={scope.own ? 'Mes activités' : 'Rapports'}
                    description={
                        scope.own
                            ? 'Ce que vous avez fait vous-même : étudiants déclarés, imports lancés, dossiers reçus et corrections traitées.'
                            : 'Effectifs agrégés de votre périmètre, sans donnée individuelle.'
                    }
                    meta={<YearLine scope={scope} />}
                    actions={
                        <div className="flex flex-wrap items-center gap-2">
                            {scope.canChoose && (
                                <nav
                                    aria-label="Type de rapport"
                                    className="inline-flex rounded-lg border bg-muted/40 p-1"
                                >
                                    {[
                                        { own: false, label: 'Mon périmètre' },
                                        { own: true, label: 'Mes activités' },
                                    ].map((item) => (
                                        <Link
                                            key={item.label}
                                            href={`/reports?${new URLSearchParams(
                                                {
                                                    ...(item.own
                                                        ? { scope: 'mine' }
                                                        : {}),
                                                    ...(scope.year
                                                        ? {
                                                              year: String(
                                                                  scope.year.id,
                                                              ),
                                                          }
                                                        : {}),
                                                },
                                            ).toString()}`}
                                            aria-current={
                                                Boolean(scope.own) === item.own
                                                    ? 'page'
                                                    : undefined
                                            }
                                            className={
                                                Boolean(scope.own) === item.own
                                                    ? 'rounded-md bg-card px-3 py-1.5 text-sm font-semibold text-primary shadow-card'
                                                    : 'rounded-md px-3 py-1.5 text-sm font-medium text-muted-foreground hover:text-foreground'
                                            }
                                        >
                                            {item.label}
                                        </Link>
                                    ))}
                                </nav>
                            )}
                            <YearSelect
                                years={years}
                                value={scope.year?.id ?? null}
                                path="/reports"
                                params={mine}
                            />
                        </div>
                    }
                />

                {exportable.length > 0 && (
                    <div className="surface flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between">
                        <div className="flex items-center gap-3">
                            <span
                                className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-accent text-primary"
                                aria-hidden="true"
                            >
                                <Download className="size-[18px]" />
                            </span>
                            <div>
                                <p className="text-sm font-semibold text-foreground">
                                    Exports CSV
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    {scope.year
                                        ? `Année ${scope.year.name}, ${scope.own ? 'vos activités' : 'dans votre périmètre'}`
                                        : scope.own
                                          ? 'Vos activités'
                                          : 'Dans votre périmètre'}
                                </p>
                            </div>
                        </div>
                        <div
                            role="group"
                            aria-label="Exports CSV"
                            className="flex flex-wrap gap-2"
                        >
                            {exportable.map((report) => (
                                <Button
                                    key={report}
                                    variant="outline"
                                    size="sm"
                                    asChild
                                >
                                    <a href={exportHref(report)}>
                                        <Download aria-hidden="true" />
                                        {reportLabels[report]}
                                        <span className="sr-only">
                                            {' '}
                                            : exporter au format CSV
                                        </span>
                                    </a>
                                </Button>
                            ))}
                        </div>
                    </div>
                )}

                {kpis.length > 0 && (
                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        {kpis.map((kpi) => (
                            <KpiCard
                                key={kpi.key}
                                label={kpi.label}
                                value={kpi.value}
                                hint={kpi.hint}
                                href={null}
                                icon={kpi.icon}
                                tone={kpi.tone}
                            />
                        ))}
                    </div>
                )}

                {(scholarships || corrections || studentsByEstablishment) && (
                    <div className="grid gap-4 lg:grid-cols-2">
                        {scholarships && (
                            <Panel
                                icon={FolderOpen}
                                title="Dossiers de bourse par statut"
                                description="Année universitaire sélectionnée"
                            >
                                <BarList
                                    label="Dossiers de bourse par statut"
                                    items={
                                        hasScholarshipTotal
                                            ? scholarshipStatuses
                                            : []
                                    }
                                    empty="Aucun dossier pour cette année universitaire."
                                />
                            </Panel>
                        )}
                        {corrections && (
                            <Panel
                                icon={FilePenLine}
                                title="Demandes de correction"
                                description="Toutes périodes, dans votre périmètre de revue"
                            >
                                <BarList
                                    label="Demandes de correction par statut"
                                    items={correctionItems}
                                    empty="Aucune demande de correction."
                                />
                            </Panel>
                        )}
                        {studentsByEstablishment && (
                            <Panel
                                icon={Building2}
                                title="Situations actives par établissement"
                                description="Année sélectionnée"
                            >
                                <BarList
                                    label="Situations actives par établissement"
                                    items={studentsByEstablishment}
                                    empty="Aucune situation pour cette année."
                                />
                            </Panel>
                        )}
                    </div>
                )}

                {students && (
                    <Panel
                        icon={Table2}
                        title="Étudiants déclarés"
                        description="Situations de l’année par établissement, niveau et parcours"
                    >
                        <ReportTable
                            caption="Étudiants déclarés par établissement, niveau et parcours"
                            columns={columns.students}
                            rows={students}
                            empty="Aucune situation pour cette année."
                        />
                    </Panel>
                )}
                {imports && (
                    <Panel
                        icon={FileUp}
                        title="Imports"
                        description="Imports de l’année par établissement"
                    >
                        <ReportTable
                            caption="Imports par établissement"
                            columns={columns.imports}
                            rows={imports}
                            empty="Aucun import pour cette année."
                        />
                    </Panel>
                )}
                {scholarships && (
                    <Panel
                        icon={FolderOpen}
                        title="Dossiers de bourse"
                        description="Dossiers de l’année par établissement, niveau et statut"
                    >
                        <ReportTable
                            caption="Dossiers de bourse par année universitaire, établissement, niveau et statut"
                            columns={columns.scholarships}
                            rows={scholarships}
                            empty="Aucun dossier pour cette année universitaire."
                        />
                    </Panel>
                )}
            </PageBody>
        </>
    );
}

ReportsIndex.layout = {
    breadcrumbs: [{ title: 'Rapports', href: '/reports' }],
};
