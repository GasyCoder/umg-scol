import { Head, Link } from '@inertiajs/react';
import {
    AlertTriangle,
    Ban,
    CheckCircle2,
    ChevronRight,
    CircleDashed,
    Clock,
    Columns3,
    Download,
    FileCheck2,
    FileSpreadsheet,
    FileUp,
    ListChecks,
    Loader2,
    Upload,
    XCircle,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { TrashButton } from '@/components/trash/trash-button';
import Heading from '@/components/heading';
import { formatNumber } from '@/components/reporting/shared';
import { Pagination } from '@/components/scholarships/shared';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { statuses } from './types';
import type { ImportItem, ImportStatus, Page } from './types';

const steps: { title: string; hint: string; icon: LucideIcon }[] = [
    { title: 'Fichier', hint: 'Dépôt du CSV ou Excel', icon: FileUp },
    { title: 'Colonnes', hint: 'Correspondance des en-têtes', icon: Columns3 },
    {
        title: 'Vérification',
        hint: 'Contrôle ligne par ligne',
        icon: ListChecks,
    },
    {
        title: 'Confirmation',
        hint: 'Validation explicite',
        icon: FileCheck2,
    },
    { title: 'Résultat', hint: 'Résumé de l’import', icon: CheckCircle2 },
];

/** Each status has its own icon and label: the colour only repeats them. */
const statusStyles: Record<
    ImportStatus,
    { icon: LucideIcon; className: string; spin?: boolean }
> = {
    uploaded: { icon: CircleDashed, className: 'text-muted-foreground' },
    analyzing: { icon: Loader2, className: 'text-chart-3', spin: true },
    ready: { icon: Clock, className: 'text-chart-4' },
    committing: { icon: Loader2, className: 'text-chart-3', spin: true },
    completed: { icon: CheckCircle2, className: 'text-chart-2' },
    failed: { icon: XCircle, className: 'text-destructive' },
    cancelled: { icon: Ban, className: 'text-muted-foreground' },
};

function ImportStatusBadge({ status }: { status: ImportStatus }) {
    const style = statusStyles[status] ?? statusStyles.uploaded;
    const Icon = style.icon;
    return (
        <Badge variant="outline" className="gap-1.5 bg-card py-1">
            <Icon
                className={`${style.className} ${style.spin ? 'animate-spin' : ''}`}
                aria-hidden="true"
            />
            {statuses[status] ?? status}
        </Badge>
    );
}

function Counter({
    value,
    detail,
    alert = false,
}: {
    value: number;
    detail?: string;
    alert?: boolean;
}) {
    const number = (
        <span
            className={`inline-flex items-center gap-1 font-semibold tabular-nums ${value === 0 ? 'text-muted-foreground' : 'text-foreground'}`}
        >
            {alert && value > 0 && (
                <AlertTriangle
                    className="size-3.5 text-chart-4"
                    aria-hidden="true"
                />
            )}
            {formatNumber(value)}
        </span>
    );
    if (!detail) return number;
    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <button
                    type="button"
                    className="cursor-help rounded-sm underline decoration-dotted underline-offset-4 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    aria-label={`${formatNumber(value)} : ${detail}`}
                >
                    {number}
                </button>
            </TooltipTrigger>
            <TooltipContent>{detail}</TooltipContent>
        </Tooltip>
    );
}

const formatDateTime = (value: string) =>
    new Date(value).toLocaleString('fr-FR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });

export default function ImportsIndex({
    imports,
    canCreate,
}: {
    imports: Page<ImportItem>;
    canCreate: boolean;
}) {
    return (
        <div className="space-y-6 p-4 md:p-6">
            <Head title="Imports des étudiants" />
            <div className="flex flex-wrap items-start justify-between gap-3">
                <Heading
                    title="Imports des étudiants"
                    description="Déclarez en une fois les étudiants déjà régulièrement inscrits dans votre établissement : chaque fichier est vérifié avant toute écriture."
                />
                {canCreate && (
                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" asChild>
                            <a href="/imports/template">
                                <Download aria-hidden="true" />
                                Télécharger le modèle
                            </a>
                        </Button>
                        <Button asChild>
                            <Link href="/imports/create">
                                <Upload aria-hidden="true" />
                                Importer un fichier
                            </Link>
                        </Button>
                    </div>
                )}
            </div>

            <section
                aria-labelledby="import-steps"
                className="rounded-lg border bg-card p-4"
            >
                <h2 id="import-steps" className="sr-only">
                    Étapes d’un import
                </h2>
                <ol className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                    {steps.map((step, index) => (
                        <li
                            key={step.title}
                            className="flex min-w-0 items-center gap-3"
                        >
                            <span
                                className="flex size-9 shrink-0 items-center justify-center rounded-full bg-accent text-primary"
                                aria-hidden="true"
                            >
                                <step.icon className="size-4" />
                            </span>
                            <div className="min-w-0 flex-1">
                                <p className="text-sm font-bold text-foreground">
                                    <span className="text-muted-foreground">
                                        {index + 1}.
                                    </span>{' '}
                                    {step.title}
                                </p>
                                <p className="truncate text-xs text-muted-foreground">
                                    {step.hint}
                                </p>
                            </div>
                            {index < steps.length - 1 && (
                                <ChevronRight
                                    className="hidden size-4 shrink-0 text-muted-foreground lg:block"
                                    aria-hidden="true"
                                />
                            )}
                        </li>
                    ))}
                </ol>
            </section>

            {imports.data.length === 0 ? (
                <div className="flex flex-col items-center gap-3 rounded-lg border border-dashed bg-card px-6 py-12 text-center">
                    <span
                        className="flex size-16 items-center justify-center rounded-full bg-accent text-primary"
                        aria-hidden="true"
                    >
                        <FileSpreadsheet className="size-8" />
                    </span>
                    <div className="space-y-1">
                        <p className="font-bold text-foreground">
                            Aucun import pour le moment.
                        </p>
                        <p className="max-w-md text-sm text-muted-foreground">
                            {canCreate
                                ? 'Téléchargez le modèle, complétez-le avec les étudiants déjà inscrits, puis importez-le pour vérification.'
                                : 'Les imports réalisés par les établissements apparaîtront ici.'}
                        </p>
                    </div>
                    {canCreate && (
                        <div className="flex flex-wrap justify-center gap-2">
                            <Button variant="outline" asChild>
                                <a href="/imports/template">
                                    <Download aria-hidden="true" />
                                    Télécharger le modèle
                                </a>
                            </Button>
                            <Button asChild>
                                <Link href="/imports/create">
                                    <Upload aria-hidden="true" />
                                    Importer un fichier
                                </Link>
                            </Button>
                        </div>
                    )}
                </div>
            ) : (
                <section className="space-y-3">
                    <p className="text-sm text-muted-foreground">
                        {formatNumber(imports.total)}{' '}
                        {imports.total > 1 ? 'imports' : 'import'}
                    </p>
                    <div className="overflow-x-auto rounded-lg border bg-card">
                        <table className="w-full min-w-[56rem] text-left text-sm">
                            <caption className="sr-only">
                                Historique des imports d’étudiants
                            </caption>
                            <thead className="border-b bg-muted text-muted-foreground">
                                <tr>
                                    <th
                                        scope="col"
                                        className="px-4 py-3 font-medium"
                                    >
                                        Fichier
                                    </th>
                                    <th
                                        scope="col"
                                        className="px-4 py-3 font-medium"
                                    >
                                        Auteur / date
                                    </th>
                                    <th
                                        scope="col"
                                        className="px-4 py-3 font-medium"
                                    >
                                        Statut
                                    </th>
                                    {[
                                        'Lignes',
                                        'Valides',
                                        'Anomalies',
                                        'Importées',
                                    ].map((label) => (
                                        <th
                                            key={label}
                                            scope="col"
                                            className="px-4 py-3 text-right font-medium"
                                        >
                                            {label}
                                        </th>
                                    ))}
                                    <th
                                        scope="col"
                                        className="px-4 py-3 font-medium"
                                    >
                                        <span className="sr-only">Actions</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {imports.data.map((item) => {
                                    const anomalies =
                                        item.warning_rows +
                                        item.rejected_rows +
                                        item.duplicate_rows;
                                    return (
                                        <tr
                                            key={item.id}
                                            className="hover:bg-muted/50"
                                        >
                                            <td className="px-4 py-3">
                                                <div className="flex items-center gap-3">
                                                    <span
                                                        className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-accent text-primary"
                                                        aria-hidden="true"
                                                    >
                                                        <FileSpreadsheet className="size-4" />
                                                    </span>
                                                    <div className="min-w-0">
                                                        <Link
                                                            href={`/imports/${item.uuid}`}
                                                            className="block max-w-[18rem] truncate font-semibold text-foreground underline-offset-4 hover:text-primary hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                                            title={
                                                                item.original_filename
                                                            }
                                                        >
                                                            {
                                                                item.original_filename
                                                            }
                                                        </Link>
                                                        <p className="text-xs text-muted-foreground">
                                                            {
                                                                item
                                                                    .establishment
                                                                    .name
                                                            }{' '}
                                                            ·{' '}
                                                            {
                                                                item
                                                                    .academic_year
                                                                    .name
                                                            }
                                                        </p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="px-4 py-3">
                                                <p className="text-foreground">
                                                    {item.user.name}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {formatDateTime(
                                                        item.created_at,
                                                    )}
                                                </p>
                                            </td>
                                            <td className="px-4 py-3">
                                                <ImportStatusBadge
                                                    status={item.status}
                                                />
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                <Counter
                                                    value={item.total_rows}
                                                />
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                <Counter
                                                    value={item.valid_rows}
                                                />
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                <Counter
                                                    value={anomalies}
                                                    alert
                                                    detail={`${formatNumber(item.warning_rows)} avertissement(s), ${formatNumber(item.rejected_rows)} rejet(s), ${formatNumber(item.duplicate_rows)} doublon(s)`}
                                                />
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                <Counter
                                                    value={item.declared_rows}
                                                    detail={`${formatNumber(item.declared_rows)} déclaration(s), ${formatNumber(item.created_rows)} identité(s) créée(s), ${formatNumber(item.updated_rows)} complétée(s), ${formatNumber(item.skipped_rows)} ligne(s) ignorée(s)`}
                                                />
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    asChild
                                                >
                                                    <Link
                                                        href={`/imports/${item.uuid}`}
                                                        aria-label={`Ouvrir l’import ${item.original_filename}`}
                                                    >
                                                        Ouvrir
                                                    </Link>
                                                </Button>
                                                <TrashButton
                                                    kind="imports"
                                                    uuid={item.uuid}
                                                    label={
                                                        item.original_filename
                                                    }
                                                />
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                    <Pagination links={imports.links} />
                </section>
            )}
        </div>
    );
}
ImportsIndex.layout = { breadcrumbs: [{ title: 'Imports', href: '/imports' }] };
