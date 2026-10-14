import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import {
    correctionStatusLabels,
    formatNumber,
    Panel,
    YearLine,
    YearSelect,
} from '@/components/reporting/shared';
import type { Scope, Year } from '@/components/reporting/shared';
import { Button } from '@/components/ui/button';

type Row = Record<string, string | number>;
type ReportKey = 'students' | 'imports' | 'scholarships';

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
        return <p className="text-sm text-muted-foreground">{empty}</p>;
    const keys = Object.keys(columns);
    const numeric = (key: string) => typeof rows[0][key] === 'number';
    const totals = keys.map((key) =>
        numeric(key)
            ? rows.reduce((sum, row) => sum + Number(row[key] ?? 0), 0)
            : null,
    );
    return (
        <div className="overflow-x-auto">
            <table className="w-full min-w-[40rem] text-sm">
                <caption className="sr-only">{caption}</caption>
                <thead className="text-left text-muted-foreground">
                    <tr>
                        {keys.map((key) => (
                            <th
                                key={key}
                                scope="col"
                                className={`py-2 pr-3 font-medium ${numeric(key) ? 'text-right' : ''}`}
                            >
                                {columns[key]}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody className="divide-y">
                    {rows.map((row, index) => (
                        <tr key={index}>
                            {keys.map((key) => (
                                <td
                                    key={key}
                                    className={`py-2 pr-3 ${numeric(key) ? 'text-right tabular-nums' : ''}`}
                                >
                                    {typeof row[key] === 'number'
                                        ? formatNumber(row[key])
                                        : row[key]}
                                </td>
                            ))}
                        </tr>
                    ))}
                </tbody>
                <tfoot className="border-t font-medium">
                    <tr>
                        {keys.map((key, index) => (
                            <td
                                key={key}
                                className={`py-2 pr-3 ${totals[index] !== null ? 'text-right tabular-nums' : ''}`}
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
    const exportButton = (report: ReportKey) =>
        exports[report] ? (
            <Button variant="outline" size="sm" asChild>
                <a
                    href={`/reports/${report}/export${scope.year ? `?year=${scope.year.id}` : ''}`}
                >
                    Exporter (CSV)
                </a>
            </Button>
        ) : undefined;
    return (
        <div className="space-y-6 p-4 md:p-6">
            <Head title="Rapports" />
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="space-y-2">
                    <Heading
                        title="Rapports"
                        description="Effectifs agrégés de votre périmètre, sans donnée individuelle."
                    />
                    <YearLine scope={scope} />
                </div>
                <YearSelect
                    years={years}
                    value={scope.year?.id ?? null}
                    path="/reports"
                />
            </div>
            {students && (
                <Panel
                    title="Étudiants déclarés"
                    description="Déclarations de l’année par établissement, niveau et parcours"
                    action={exportButton('students')}
                >
                    <ReportTable
                        caption="Étudiants déclarés par établissement, niveau et parcours"
                        columns={columns.students}
                        rows={students}
                        empty="Aucune déclaration pour cette année."
                    />
                </Panel>
            )}
            {imports && (
                <Panel
                    title="Imports"
                    description="Imports de l’année par établissement"
                    action={exportButton('imports')}
                >
                    <ReportTable
                        caption="Imports par établissement"
                        columns={columns.imports}
                        rows={imports}
                        empty="Aucun import pour cette année."
                    />
                </Panel>
            )}
            {corrections && (
                <Panel
                    title="Demandes de correction"
                    description="Toutes périodes, dans votre périmètre de revue"
                >
                    <dl className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        {Object.entries(corrections).map(([status, value]) => (
                            <div key={status} className="rounded-lg border p-4">
                                <dt className="text-sm text-muted-foreground">
                                    {correctionStatusLabels[status] ?? status}
                                </dt>
                                <dd className="text-2xl font-semibold tabular-nums">
                                    {formatNumber(value)}
                                </dd>
                            </div>
                        ))}
                    </dl>
                </Panel>
            )}
            {scholarships && (
                <Panel
                    title="Dossiers de bourse"
                    description="Campagnes de l’année par établissement, niveau et statut"
                    action={exportButton('scholarships')}
                >
                    <ReportTable
                        caption="Dossiers de bourse par campagne, établissement, niveau et statut"
                        columns={columns.scholarships}
                        rows={scholarships}
                        empty="Aucun dossier pour les campagnes de cette année."
                    />
                </Panel>
            )}
        </div>
    );
}

ReportsIndex.layout = {
    breadcrumbs: [{ title: 'Rapports', href: '/reports' }],
};
