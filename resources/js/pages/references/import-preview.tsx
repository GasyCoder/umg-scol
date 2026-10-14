import { Head, Link, useForm } from '@inertiajs/react';
import {
    AlertTriangle,
    CircleCheck,
    CircleMinus,
    CirclePlus,
    FileSpreadsheet,
    RefreshCw,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { baseUrl } from '@/components/references/definitions';
import type { TransferKind } from '@/components/references/reference-transfer';
import { formatNumber } from '@/components/reporting/shared';
import { formatDate } from '@/components/scholarships/shared';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

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
    {
        label: string;
        summary: string;
        icon: LucideIcon;
        variant: 'default' | 'secondary' | 'destructive' | 'outline';
    }
> = {
    create: {
        label: 'À créer',
        summary: 'À créer',
        icon: CirclePlus,
        variant: 'default',
    },
    update: {
        label: 'À mettre à jour',
        summary: 'À mettre à jour',
        icon: RefreshCw,
        variant: 'secondary',
    },
    unchanged: {
        label: 'Inchangé',
        summary: 'Inchangées',
        icon: CircleMinus,
        variant: 'outline',
    },
    error: {
        label: 'Erreur',
        summary: 'En erreur',
        icon: AlertTriangle,
        variant: 'destructive',
    },
};
const order: Status[] = ['create', 'update', 'unchanged', 'error'];

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

    return (
        <div className="space-y-6 p-4 pb-0 md:p-6 md:pb-0">
            <Head title={`Import — ${title}`} />
            <Heading
                title={`${title} / Prévisualisation de l’import`}
                description="Vérifiez chaque ligne avant de confirmer. Rien n’est enregistré tant que l’import n’est pas confirmé."
            />

            <section className="flex flex-wrap items-center justify-between gap-3 rounded-lg border bg-card px-5 py-4">
                <div className="flex min-w-0 items-center gap-3">
                    <span
                        className="flex size-9 shrink-0 items-center justify-center rounded-full bg-accent text-primary"
                        aria-hidden="true"
                    >
                        <FileSpreadsheet className="size-4" />
                    </span>
                    <div className="min-w-0">
                        <p className="truncate font-bold">{filename}</p>
                        <p className="text-xs text-muted-foreground">
                            {formatNumber(counts.total)} ligne(s) analysée(s) —
                            prévisualisation valable jusqu’à{' '}
                            {formatDate(expiresAt, true)}
                        </p>
                    </div>
                </div>
                <Button variant="outline" size="sm" asChild>
                    <Link href={listUrl}>Importer un autre fichier</Link>
                </Button>
            </section>

            <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                {order.map((status) => {
                    const { icon: Icon, summary } = statuses[status];
                    return (
                        <div
                            key={status}
                            className="rounded-lg border bg-card p-4"
                        >
                            <p className="flex items-center gap-2 text-sm text-muted-foreground">
                                <Icon className="size-4" aria-hidden="true" />
                                {summary}
                            </p>
                            <p
                                className={`mt-1 text-2xl font-bold tabular-nums ${status === 'error' && counts.error > 0 ? 'text-destructive' : ''}`}
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
                    className="flex gap-3 rounded-lg border border-destructive/40 bg-destructive/5 p-4 text-sm"
                >
                    <AlertTriangle
                        className="mt-0.5 size-4 shrink-0 text-destructive"
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
                <div className="flex gap-3 rounded-lg border bg-muted/40 p-4 text-sm">
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

            <section className="rounded-lg border bg-card">
                <header className="flex flex-wrap items-center justify-between gap-3 border-b px-5 py-3">
                    <h2 className="text-base font-bold">Lignes du fichier</h2>
                    <div
                        className="flex flex-wrap gap-1"
                        role="group"
                        aria-label="Filtrer les lignes par statut"
                    >
                        {(['all', ...order] as const).map((value) => (
                            <Button
                                key={value}
                                size="sm"
                                variant={
                                    filter === value ? 'default' : 'outline'
                                }
                                aria-pressed={filter === value}
                                onClick={() => setFilter(value)}
                            >
                                {value === 'all'
                                    ? 'Toutes'
                                    : statuses[value].summary}{' '}
                                (
                                {value === 'all' ? counts.total : counts[value]}
                                )
                            </Button>
                        ))}
                    </div>
                </header>
                <div className="overflow-x-auto">
                    <Table>
                        <caption className="sr-only">
                            Prévisualisation des lignes importées
                        </caption>
                        <TableHeader>
                            <TableRow className="hover:bg-transparent">
                                <TableHead scope="col">Ligne</TableHead>
                                <TableHead scope="col">Établissement</TableHead>
                                {programs && (
                                    <TableHead scope="col">Mention</TableHead>
                                )}
                                <TableHead scope="col">Code</TableHead>
                                <TableHead scope="col">Nom</TableHead>
                                <TableHead scope="col">Statut</TableHead>
                                <TableHead scope="col">Détail</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {visible.length === 0 ? (
                                <TableRow className="hover:bg-transparent">
                                    <TableCell
                                        colSpan={programs ? 7 : 6}
                                        className="p-8 text-center text-muted-foreground"
                                    >
                                        Aucune ligne pour ce statut.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                visible.map((row) => (
                                    <TableRow
                                        key={row.line}
                                        className={
                                            row.status === 'error'
                                                ? 'bg-destructive/5'
                                                : undefined
                                        }
                                    >
                                        <TableCell className="text-muted-foreground tabular-nums">
                                            {row.line}
                                        </TableCell>
                                        <TableCell className="font-mono text-xs">
                                            {row.establishment_code || '—'}
                                        </TableCell>
                                        {programs && (
                                            <TableCell className="font-mono text-xs">
                                                {row.mention_code || '—'}
                                            </TableCell>
                                        )}
                                        <TableCell className="font-mono text-xs">
                                            {row.code || '—'}
                                        </TableCell>
                                        <TableCell className="min-w-48">
                                            {row.name || '—'}
                                        </TableCell>
                                        <TableCell>
                                            <Badge
                                                variant={
                                                    statuses[row.status].variant
                                                }
                                            >
                                                {statuses[row.status].label}
                                            </Badge>
                                        </TableCell>
                                        <TableCell
                                            className={`min-w-56 text-xs ${row.status === 'error' ? 'text-destructive' : 'text-muted-foreground'}`}
                                        >
                                            {row.reason || '—'}
                                        </TableCell>
                                    </TableRow>
                                ))
                            )}
                        </TableBody>
                    </Table>
                </div>
            </section>

            <div className="sticky bottom-0 z-10 -mx-4 flex flex-wrap items-center justify-between gap-3 border-t bg-card/95 px-4 py-3 backdrop-blur md:-mx-6 md:px-6">
                <p className="text-xs text-muted-foreground">
                    {formatNumber(counts.create)} création(s),{' '}
                    {formatNumber(counts.update)} mise(s) à jour.{' '}
                    {counts.error + counts.unchanged > 0 &&
                        `${formatNumber(counts.error + counts.unchanged)} ligne(s) en erreur ou inchangée(s) seront ignorées.`}
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
                        {confirm.processing && <Spinner />}
                        {confirm.processing
                            ? 'Import en cours…'
                            : 'Confirmer l’import'}
                    </Button>
                </div>
            </div>
        </div>
    );
}

ImportPreview.layout = {
    breadcrumbs: [{ title: 'Référentiels', href: '/references/mentions' }],
};
