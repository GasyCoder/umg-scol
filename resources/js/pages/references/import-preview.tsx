import { Head, Link, useForm } from '@inertiajs/react';
import {
    AlertTriangle,
    CircleCheck,
    CircleMinus,
    CirclePlus,
    Clock,
    FileSpreadsheet,
    FileUp,
    RefreshCw,
    Rows3,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useState } from 'react';
import { EmptyState } from '@/components/empty-state';
import { PageBody, PageHeader } from '@/components/page-header';
import { baseUrl } from '@/components/references/definitions';
import type { TransferKind } from '@/components/references/reference-transfer';
import { formatNumber } from '@/components/reporting/shared';
import { formatDate } from '@/components/scholarships/shared';
import { ToneBadge } from '@/components/tone-badge';
import type { Tone } from '@/components/tone-badge';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';

type Status = 'create' | 'update' | 'unchanged' | 'error';
type PreviewRow = {
    line: number;
    establishment_code: string;
    mention_code: string | null;
    code: string;
    name: string;
    status: Status;
    reason: string;
};
type Counts = Record<Status, number> & { total: number };

const statuses: Record<
    Status,
    { label: string; summary: string; icon: LucideIcon; tone: Tone }
> = {
    create: {
        label: 'À créer',
        summary: 'À créer',
        icon: CirclePlus,
        tone: 'success',
    },
    update: {
        label: 'À mettre à jour',
        summary: 'À mettre à jour',
        icon: RefreshCw,
        tone: 'info',
    },
    unchanged: {
        label: 'Inchangé',
        summary: 'Inchangées',
        icon: CircleMinus,
        tone: 'neutral',
    },
    error: {
        label: 'Erreur',
        summary: 'En erreur',
        icon: AlertTriangle,
        tone: 'danger',
    },
};
const order: Status[] = ['create', 'update', 'unchanged', 'error'];

const tileIcons: Record<Tone, string> = {
    primary: 'bg-accent text-primary',
    success: 'bg-success-soft text-success',
    warning: 'bg-warning-soft text-warning',
    info: 'bg-info-soft text-info',
    danger: 'bg-danger-soft text-danger',
    neutral: 'bg-secondary text-muted-foreground',
};

function CodeCell({ value }: { value: string | null }) {
    return value ? (
        <span className="inline-flex rounded-md border bg-muted/60 px-1.5 py-0.5 font-mono text-xs font-semibold text-foreground">
            {value}
        </span>
    ) : (
        <span className="text-muted-foreground">—</span>
    );
}

export default function ImportPreview({
    kind,
    title,
    token,
    filename,
    expiresAt,
    counts,
    canConfirm,
    rows,
}: {
    kind: TransferKind;
    title: string;
    token: string;
    filename: string;
    expiresAt: string;
    counts: Counts;
    canConfirm: boolean;
    rows: PreviewRow[];
}) {
    const [filter, setFilter] = useState<Status | 'all'>('all');
    const confirm = useForm({});
    const visible =
        filter === 'all' ? rows : rows.filter((row) => row.status === filter);
    const programs = kind === 'programs';
    const listUrl = baseUrl(kind);
    const submit = () =>
        confirm.post(`${listUrl}/import/${token}`, { preserveScroll: true });
    const ignored = counts.error + counts.unchanged;

    return (
        <PageBody className="pb-0 md:pb-0 lg:pb-0">
            <Head title={`Import — ${title}`} />
            <PageHeader
                icon={FileUp}
                title={`${title} / Prévisualisation de l’import`}
                description="Vérifiez chaque ligne avant de confirmer. Rien n’est enregistré tant que l’import n’est pas confirmé."
                actions={
                    <Button variant="outline" asChild>
                        <Link href={listUrl}>
                            <FileUp aria-hidden="true" />
                            Importer un autre fichier
                        </Link>
                    </Button>
                }
            />

            <section className="surface flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                <div className="flex min-w-0 items-center gap-3">
                    <span
                        className="flex size-11 shrink-0 items-center justify-center rounded-xl bg-success-soft text-success"
                        aria-hidden="true"
                    >
                        <FileSpreadsheet className="size-5" />
                    </span>
                    <div className="min-w-0">
                        <p className="truncate font-bold text-foreground">
                            {filename}
                        </p>
                        <p className="text-xs text-muted-foreground">
                            {formatNumber(counts.total)} ligne(s) analysée(s)
                        </p>
                    </div>
                </div>
                <p className="inline-flex items-center gap-1.5 self-start rounded-full bg-warning-soft px-3 py-1 text-xs font-medium text-warning-foreground sm:self-auto">
                    <Clock className="size-3.5" aria-hidden="true" />
                    Prévisualisation valable jusqu’à{' '}
                    {formatDate(expiresAt, true)}
                </p>
            </section>

            <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                {order.map((status) => {
                    const { icon: Icon, summary, tone } = statuses[status];
                    const highlight = status === 'error' && counts.error > 0;
                    return (
                        <div
                            key={status}
                            className={cn(
                                'surface flex flex-col gap-2 p-4',
                                highlight && 'border-danger/40',
                            )}
                        >
                            <div className="flex items-center justify-between gap-2">
                                <p className="text-sm font-medium text-muted-foreground">
                                    {summary}
                                </p>
                                <span
                                    className={cn(
                                        'flex size-9 shrink-0 items-center justify-center rounded-lg',
                                        tileIcons[tone],
                                    )}
                                    aria-hidden="true"
                                >
                                    <Icon className="size-[18px]" />
                                </span>
                            </div>
                            <p
                                className={cn(
                                    'font-heading text-2xl font-extrabold tracking-tight tabular-nums',
                                    highlight
                                        ? 'text-danger'
                                        : 'text-foreground',
                                )}
                            >
                                {formatNumber(counts[status])}
                            </p>
                        </div>
                    );
                })}
            </div>

            {counts.error > 0 && (
                <div
                    role="alert"
                    className="flex gap-3 rounded-xl border border-danger/30 bg-danger-soft p-4 text-sm text-danger-foreground"
                >
                    <AlertTriangle
                        className="mt-0.5 size-4 shrink-0 text-danger"
                        aria-hidden="true"
                    />
                    <p>
                        <strong>
                            {formatNumber(counts.error)} ligne(s) en erreur ne
                            seront pas importées.
                        </strong>{' '}
                        Corrigez-les dans le fichier puis relancez l’analyse
                        pour les inclure. Les autres lignes peuvent être
                        importées dès maintenant.
                    </p>
                </div>
            )}
            {!canConfirm && (
                <div className="flex gap-3 rounded-xl border bg-muted/60 p-4 text-sm text-foreground">
                    <CircleCheck
                        className="mt-0.5 size-4 shrink-0 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <p>
                        Aucune ligne à créer ou à mettre à jour : il n’y a rien
                        à importer.
                    </p>
                </div>
            )}

            <section className="surface overflow-hidden">
                <header className="flex flex-col gap-3 border-b px-5 py-4 lg:flex-row lg:items-center lg:justify-between">
                    <div className="flex items-center gap-3">
                        <span
                            className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-accent text-primary"
                            aria-hidden="true"
                        >
                            <Rows3 className="size-[18px]" />
                        </span>
                        <h2 className="text-base font-bold text-foreground">
                            Lignes du fichier
                        </h2>
                    </div>
                    <div
                        className="flex flex-wrap gap-1 rounded-lg bg-muted p-1"
                        role="group"
                        aria-label="Filtrer les lignes par statut"
                    >
                        {(['all', ...order] as const).map((value) => {
                            const pressed = filter === value;
                            return (
                                <button
                                    key={value}
                                    type="button"
                                    aria-pressed={pressed}
                                    onClick={() => setFilter(value)}
                                    className={cn(
                                        'inline-flex h-8 items-center gap-1.5 rounded-md px-3 text-xs font-semibold transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                        pressed
                                            ? 'bg-card text-foreground shadow-card'
                                            : 'text-muted-foreground hover:text-foreground',
                                    )}
                                >
                                    {value === 'all'
                                        ? 'Toutes'
                                        : statuses[value].summary}
                                    <span
                                        className={cn(
                                            'rounded-full px-1.5 tabular-nums',
                                            pressed
                                                ? 'bg-accent text-accent-foreground'
                                                : 'bg-background/60',
                                        )}
                                    >
                                        {value === 'all'
                                            ? counts.total
                                            : counts[value]}
                                    </span>
                                </button>
                            );
                        })}
                    </div>
                </header>
                {visible.length === 0 ? (
                    <EmptyState
                        compact
                        illustration="search"
                        title="Aucune ligne pour ce statut."
                        action={
                            filter !== 'all' ? (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setFilter('all')}
                                >
                                    Afficher toutes les lignes
                                </Button>
                            ) : undefined
                        }
                    />
                ) : (
                    <div className="overflow-x-auto">
                        <table className="data-table min-w-[48rem]">
                            <caption className="sr-only">
                                Prévisualisation des lignes importées
                            </caption>
                            <thead>
                                <tr>
                                    <th scope="col" className="w-16">
                                        Ligne
                                    </th>
                                    <th scope="col">Établissement</th>
                                    {programs && <th scope="col">Mention</th>}
                                    <th scope="col">Code</th>
                                    <th scope="col">Nom</th>
                                    <th scope="col">Statut</th>
                                    <th scope="col">Détail</th>
                                </tr>
                            </thead>
                            <tbody>
                                {visible.map((row) => {
                                    const isError = row.status === 'error';
                                    return (
                                        <tr
                                            key={row.line}
                                            className={
                                                isError
                                                    ? 'bg-danger-soft/40 hover:bg-danger-soft/60'
                                                    : undefined
                                            }
                                        >
                                            <td className="font-mono text-xs text-muted-foreground tabular-nums">
                                                {row.line}
                                            </td>
                                            <td>
                                                <CodeCell
                                                    value={
                                                        row.establishment_code
                                                    }
                                                />
                                            </td>
                                            {programs && (
                                                <td>
                                                    <CodeCell
                                                        value={row.mention_code}
                                                    />
                                                </td>
                                            )}
                                            <td>
                                                <CodeCell value={row.code} />
                                            </td>
                                            <td className="min-w-48 font-medium text-foreground">
                                                {row.name || (
                                                    <span className="text-muted-foreground">
                                                        —
                                                    </span>
                                                )}
                                            </td>
                                            <td>
                                                <ToneBadge
                                                    tone={
                                                        statuses[row.status]
                                                            .tone
                                                    }
                                                >
                                                    {statuses[row.status].label}
                                                </ToneBadge>
                                            </td>
                                            <td
                                                className={cn(
                                                    'min-w-56 text-xs',
                                                    isError
                                                        ? 'font-medium text-danger-foreground'
                                                        : 'text-muted-foreground',
                                                )}
                                            >
                                                {isError && row.reason ? (
                                                    <span className="inline-flex items-start gap-1.5">
                                                        <AlertTriangle
                                                            className="mt-px size-3.5 shrink-0 text-danger"
                                                            aria-hidden="true"
                                                        />
                                                        {row.reason}
                                                    </span>
                                                ) : (
                                                    row.reason || '—'
                                                )}
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                )}
            </section>

            <div className="sticky bottom-0 z-10 -mx-4 flex flex-wrap items-center justify-between gap-3 border-t bg-card/95 px-4 py-3 backdrop-blur md:-mx-6 md:px-6 lg:-mx-8 lg:px-8">
                <p className="text-xs text-muted-foreground">
                    <span className="font-semibold text-foreground">
                        {formatNumber(counts.create)} création(s),{' '}
                        {formatNumber(counts.update)} mise(s) à jour.
                    </span>{' '}
                    {ignored > 0 &&
                        `${formatNumber(ignored)} ligne(s) en erreur ou inchangée(s) seront ignorées.`}
                </p>
                <div className="flex gap-2">
                    <Button
                        variant="outline"
                        asChild
                        disabled={confirm.processing}
                    >
                        <Link href={listUrl}>Annuler</Link>
                    </Button>
                    <Button
                        onClick={submit}
                        disabled={!canConfirm || confirm.processing}
                    >
                        {confirm.processing ? (
                            <Spinner />
                        ) : (
                            <CircleCheck aria-hidden="true" />
                        )}
                        {confirm.processing
                            ? 'Import en cours…'
                            : 'Confirmer l’import'}
                    </Button>
                </div>
            </div>
        </PageBody>
    );
}

ImportPreview.layout = {
    breadcrumbs: [{ title: 'Référentiels', href: '/references/mentions' }],
};
