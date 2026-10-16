import { Head, Link } from '@inertiajs/react';
import {
    AlertTriangle,
    Download,
    Eye,
    FileSpreadsheet,
    FileUp,
    History,
    ShieldCheck,
    Upload,
} from 'lucide-react';
import { EmptyState } from '@/components/empty-state';
import { IconAction } from '@/components/icon-action';
import { PageBody, PageHeader } from '@/components/page-header';
import { formatNumber } from '@/components/reporting/shared';
import { Pagination } from '@/components/scholarships/shared';
import { TrashButton } from '@/components/trash/trash-button';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';
import { ImportStatusBadge, RowMixBar } from './import-ui';
import { importSteps } from './import-ui';
import type { ImportItem, Page } from './types';

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
            className={cn(
                'inline-flex items-center gap-1 font-semibold tabular-nums',
                value === 0
                    ? 'text-muted-foreground'
                    : alert
                      ? 'text-warning-foreground'
                      : 'text-foreground',
            )}
        >
            {alert && value > 0 && (
                <AlertTriangle
                    className="size-3.5 text-warning"
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
                    className="cursor-help rounded-sm underline decoration-muted-foreground/50 decoration-dotted underline-offset-4 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
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

function CreateActions({ className }: { className?: string }) {
    return (
        <div className={cn('flex flex-wrap gap-2', className)}>
            <Button variant="outline" asChild>
                <a href="/imports/template">
                    <Download aria-hidden="true" />
                    Télécharger le modèle Excel
                </a>
            </Button>
            <Button asChild>
                <Link href="/imports/create">
                    <Upload aria-hidden="true" />
                    Importer un fichier
                </Link>
            </Button>
        </div>
    );
}

export default function ImportsIndex({
    imports,
    canCreate,
}: {
    imports: Page<ImportItem>;
    canCreate: boolean;
}) {
    return (
        <PageBody>
            <Head title="Imports des étudiants" />
            <PageHeader
                icon={FileUp}
                title="Imports des étudiants"
                description="Déclarez en une fois les étudiants déjà régulièrement inscrits dans votre établissement : chaque fichier est vérifié avant toute écriture."
                actions={canCreate ? <CreateActions /> : undefined}
            />

            <section
                aria-labelledby="import-steps"
                className="surface relative overflow-hidden p-5 sm:p-6"
            >
                <div
                    className="bg-grid absolute inset-0 text-primary opacity-40"
                    aria-hidden="true"
                />
                <div className="relative space-y-4">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <h2
                            id="import-steps"
                            className="text-base font-bold text-foreground"
                        >
                            Étapes d’un import
                        </h2>
                        <p className="inline-flex items-center gap-1.5 text-xs font-medium text-muted-foreground">
                            <ShieldCheck
                                className="size-4 text-success"
                                aria-hidden="true"
                            />
                            Rien n’est enregistré avant la confirmation
                        </p>
                    </div>
                    <ol className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                        {importSteps.map((step, index) => (
                            <li
                                key={step.title}
                                className="flex min-w-0 items-center gap-3 rounded-lg border bg-card/80 p-3 backdrop-blur-sm"
                            >
                                <span
                                    className="relative flex size-10 shrink-0 items-center justify-center rounded-xl bg-accent text-primary"
                                    aria-hidden="true"
                                >
                                    <step.icon className="size-5" />
                                    <span className="absolute -top-1.5 -right-1.5 flex size-5 items-center justify-center rounded-full bg-primary text-[10px] font-bold text-primary-foreground ring-2 ring-card">
                                        {index + 1}
                                    </span>
                                </span>
                                <div className="min-w-0 flex-1">
                                    <p className="text-sm font-bold text-foreground">
                                        <span className="sr-only">
                                            {index + 1}.{' '}
                                        </span>
                                        {step.title}
                                    </p>
                                    <p className="truncate text-xs text-muted-foreground">
                                        {step.hint}
                                    </p>
                                </div>
                            </li>
                        ))}
                    </ol>
                </div>
            </section>

            {imports.data.length === 0 ? (
                <section className="surface">
                    <EmptyState
                        illustration="upload"
                        title="Aucun import pour le moment."
                        description={
                            canCreate
                                ? 'Téléchargez le modèle, complétez-le avec les étudiants déjà inscrits, puis importez-le pour vérification.'
                                : 'Les imports réalisés par les établissements apparaîtront ici.'
                        }
                        action={
                            canCreate ? (
                                <CreateActions className="justify-center" />
                            ) : undefined
                        }
                    />
                </section>
            ) : (
                <section className="space-y-4" aria-labelledby="import-history">
                    <div className="surface overflow-hidden">
                        <div className="flex flex-wrap items-center justify-between gap-3 border-b px-5 py-4">
                            <div className="flex items-center gap-3">
                                <span
                                    className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-accent text-primary"
                                    aria-hidden="true"
                                >
                                    <History className="size-[18px]" />
                                </span>
                                <h2
                                    id="import-history"
                                    className="text-base font-bold text-foreground"
                                >
                                    Historique
                                </h2>
                            </div>
                            <span className="rounded-full bg-secondary px-3 py-1 text-xs font-semibold text-muted-foreground tabular-nums">
                                {formatNumber(imports.total)}{' '}
                                {imports.total > 1 ? 'imports' : 'import'}
                            </span>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="data-table min-w-[60rem]">
                                <caption className="sr-only">
                                    Historique des imports d’étudiants
                                </caption>
                                <thead>
                                    <tr>
                                        <th scope="col">Fichier</th>
                                        <th scope="col">Auteur / date</th>
                                        <th scope="col">Statut</th>
                                        <th scope="col" className="text-right">
                                            Lignes
                                        </th>
                                        <th scope="col" className="text-right">
                                            Valides
                                        </th>
                                        <th scope="col" className="text-right">
                                            Anomalies
                                        </th>
                                        <th scope="col" className="text-right">
                                            Importées
                                        </th>
                                        <th scope="col">
                                            <span className="sr-only">
                                                Actions
                                            </span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {imports.data.map((item) => {
                                        const anomalies =
                                            item.warning_rows +
                                            item.rejected_rows +
                                            item.duplicate_rows;
                                        return (
                                            <tr key={item.id}>
                                                <td>
                                                    <div className="flex items-center gap-3">
                                                        <span
                                                            className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-accent text-primary"
                                                            aria-hidden="true"
                                                        >
                                                            <FileSpreadsheet className="size-5" />
                                                        </span>
                                                        <div className="min-w-0">
                                                            <Link
                                                                href={`/imports/${item.uuid}`}
                                                                className="block max-w-[18rem] truncate rounded-sm font-semibold text-foreground underline-offset-4 hover:text-primary hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                                                title={
                                                                    item.original_filename
                                                                }
                                                            >
                                                                {
                                                                    item.original_filename
                                                                }
                                                            </Link>
                                                            <p className="max-w-[18rem] truncate text-xs text-muted-foreground">
                                                                {item
                                                                    .establishment
                                                                    ?.name ??
                                                                    'Tous les établissements'}{' '}
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
                                                <td>
                                                    <p className="font-medium text-foreground">
                                                        {item.user.name}
                                                    </p>
                                                    <p className="text-xs text-muted-foreground tabular-nums">
                                                        {formatDateTime(
                                                            item.created_at,
                                                        )}
                                                    </p>
                                                </td>
                                                <td>
                                                    <ImportStatusBadge
                                                        status={item.status}
                                                    />
                                                </td>
                                                <td className="text-right">
                                                    <div className="ml-auto flex w-24 flex-col items-end gap-1.5">
                                                        <Counter
                                                            value={
                                                                item.total_rows
                                                            }
                                                        />
                                                        <RowMixBar
                                                            valid={
                                                                item.valid_rows
                                                            }
                                                            warning={
                                                                item.warning_rows
                                                            }
                                                            rejected={
                                                                item.rejected_rows
                                                            }
                                                            duplicate={
                                                                item.duplicate_rows
                                                            }
                                                        />
                                                    </div>
                                                </td>
                                                <td className="text-right">
                                                    <Counter
                                                        value={item.valid_rows}
                                                    />
                                                </td>
                                                <td className="text-right">
                                                    <Counter
                                                        value={anomalies}
                                                        alert
                                                        detail={`${formatNumber(item.warning_rows)} avertissement(s), ${formatNumber(item.rejected_rows)} rejet(s), ${formatNumber(item.duplicate_rows)} doublon(s)`}
                                                    />
                                                </td>
                                                <td className="text-right">
                                                    <Counter
                                                        value={
                                                            item.declared_rows
                                                        }
                                                        detail={`${formatNumber(item.declared_rows)} situation(s), ${formatNumber(item.created_rows)} identité(s) créée(s), ${formatNumber(item.updated_rows)} complétée(s), ${formatNumber(item.skipped_rows)} ligne(s) ignorée(s)`}
                                                    />
                                                </td>
                                                <td>
                                                    <div className="flex items-center justify-end gap-1">
                                                        <IconAction
                                                            icon={Eye}
                                                            label="Consulter l’import"
                                                            href={`/imports/${item.uuid}`}
                                                        />
                                                        <TrashButton
                                                            iconOnly
                                                            kind="imports"
                                                            uuid={item.uuid}
                                                            label={
                                                                item.original_filename
                                                            }
                                                        />
                                                    </div>
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <ul
                            className="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted-foreground"
                            aria-label="Légende de la barre de répartition"
                        >
                            {[
                                ['bg-success', 'Valides'],
                                ['bg-warning', 'Avertissements'],
                                ['bg-danger', 'Rejetées'],
                                ['bg-info', 'Doublons'],
                            ].map(([color, label]) => (
                                <li
                                    key={label}
                                    className="inline-flex items-center gap-1.5"
                                >
                                    <span
                                        className={cn(
                                            'size-2 rounded-full',
                                            color,
                                        )}
                                        aria-hidden="true"
                                    />
                                    {label}
                                </li>
                            ))}
                        </ul>
                        <Pagination links={imports.links} />
                    </div>
                </section>
            )}
        </PageBody>
    );
}
ImportsIndex.layout = { breadcrumbs: [{ title: 'Imports', href: '/imports' }] };
