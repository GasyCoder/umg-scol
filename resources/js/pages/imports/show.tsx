import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowLeft,
    Ban,
    CalendarDays,
    Check,
    CircleCheck,
    CircleDashed,
    ClipboardCheck,
    Columns3,
    Copy,
    Download,
    FileSpreadsheet,
    Info,
    Landmark,
    Loader2,
    Rows3,
    ScanSearch,
    UserCheck,
    UserRound,
    XCircle,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import { EmptyState } from '@/components/empty-state';
import InputError from '@/components/input-error';
import { PageBody, PageHeader } from '@/components/page-header';
import { formatNumber } from '@/components/reporting/shared';
import { Pagination } from '@/components/scholarships/shared';
import type { Tone } from '@/components/tone-badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';
import { ImportStatusBadge, ImportStepper, RowStatusBadge } from './import-ui';
import { rowStatusOrder, statuses } from './types';
import type {
    ImportItem,
    ImportRow,
    ImportStatus,
    Page,
    RowStatus,
} from './types';

const REQUIRED_FIELDS = ['last_name', 'level'];

/** Soft tinted surfaces per tone (banners, tiles): tokens only, so dark mode follows. */
const toneSurfaces: Record<Tone, string> = {
    primary: 'border-primary/25 bg-accent text-accent-foreground',
    success: 'border-success/30 bg-success-soft text-success-foreground',
    warning: 'border-warning/30 bg-warning-soft text-warning-foreground',
    info: 'border-info/30 bg-info-soft text-info-foreground',
    danger: 'border-danger/30 bg-danger-soft text-danger-foreground',
    neutral: 'border-border bg-muted/60 text-foreground',
};
const toneText: Record<Tone, string> = {
    primary: 'text-primary',
    success: 'text-success',
    warning: 'text-warning',
    info: 'text-info',
    danger: 'text-danger',
    neutral: 'text-muted-foreground',
};
const toneIcons: Record<Tone, string> = {
    primary: 'bg-accent text-primary',
    success: 'bg-success-soft text-success',
    warning: 'bg-warning-soft text-warning',
    info: 'bg-info-soft text-info',
    danger: 'bg-danger-soft text-danger',
    neutral: 'bg-secondary text-muted-foreground',
};

const rowTiles: Record<
    RowStatus | 'all',
    { label: string; icon: LucideIcon; tone: Tone }
> = {
    all: { label: 'Total', icon: Rows3, tone: 'neutral' },
    valid: { label: 'Valides', icon: CircleCheck, tone: 'success' },
    warning: { label: 'Avertissements', icon: AlertTriangle, tone: 'warning' },
    rejected: { label: 'Rejetées', icon: XCircle, tone: 'danger' },
    duplicate: { label: 'Doublons', icon: Copy, tone: 'info' },
};

/** Pipeline position of an import, for the stepper. */
function stepOf(status: ImportStatus, mapping: boolean): number {
    if (mapping) return 1;
    switch (status) {
        case 'uploaded':
            return 1;
        case 'committing':
        case 'failed':
            return 3;
        case 'completed':
            return 4;
        default:
            return 2;
    }
}

function Banner({
    tone,
    icon: Icon,
    spin = false,
    title,
    role,
    children,
    actions,
}: {
    tone: Tone;
    icon: LucideIcon;
    spin?: boolean;
    title: string;
    role?: 'status' | 'alert';
    children?: ReactNode;
    actions?: ReactNode;
}) {
    return (
        <section
            role={role}
            className={cn(
                'flex flex-col gap-4 rounded-2xl border p-4 sm:flex-row sm:items-center sm:p-5',
                toneSurfaces[tone],
            )}
        >
            <span
                className="flex size-11 shrink-0 items-center justify-center rounded-xl bg-card shadow-card"
                aria-hidden="true"
            >
                <Icon
                    className={cn(
                        'size-5',
                        toneText[tone],
                        spin && 'animate-spin motion-reduce:animate-none',
                    )}
                />
            </span>
            <div className="min-w-0 flex-1 space-y-1">
                <h2 className="font-heading text-base font-bold">{title}</h2>
                {children && (
                    <div className="text-sm opacity-90">{children}</div>
                )}
            </div>
            {actions && (
                <div className="flex shrink-0 flex-wrap gap-2">{actions}</div>
            )}
        </section>
    );
}

function IssueChip({
    tone,
    label,
    message,
}: {
    tone: 'danger' | 'warning' | 'info';
    label?: string;
    message: string;
}) {
    const Icon =
        tone === 'danger' ? XCircle : tone === 'warning' ? AlertTriangle : Info;
    return (
        <li
            className={cn(
                'flex items-start gap-1.5 rounded-md px-2 py-1 text-xs leading-snug',
                toneSurfaces[tone],
                'border-0',
            )}
        >
            <Icon
                className={cn('mt-px size-3.5 shrink-0', toneText[tone])}
                aria-hidden="true"
            />
            <span>
                {label && <span className="font-semibold">{label} : </span>}
                {message}
            </span>
        </li>
    );
}

export default function ImportShow({
    import: item,
    rows,
    columns,
    filter,
    canAnalyze,
    canCommit,
    canExport,
}: {
    import: ImportItem;
    rows: Page<ImportRow>;
    columns: Record<string, string>;
    filter: string;
    canAnalyze: boolean;
    canCommit: boolean;
    canExport: boolean;
}) {
    const mapping = useForm<{ mapping: Record<string, number> }>({
        mapping: item.mapping ?? {},
    });
    const commit = useForm({});
    const [confirm, setConfirm] = useState(false);
    const [editMapping, setEditMapping] = useState(false);
    const analyzing =
        item.status === 'uploaded' || (item.status === 'ready' && editMapping);
    const showMapping = analyzing && canAnalyze;
    const analyze = (event: FormEvent) => {
        event.preventDefault();
        mapping.post(`/imports/${item.uuid}/analyze`, {
            onSuccess: () => setEditMapping(false),
        });
    };
    const applyFilter = (value: string) =>
        router.get(
            `/imports/${item.uuid}`,
            value === 'all' ? {} : { status: value },
            { preserveState: true },
        );
    const commitError = (commit.errors as Record<string, string>).import;
    const mappingErrors = mapping.errors as Record<string, string>;
    const mappedCount = Object.keys(columns).filter(
        (field) => mapping.data.mapping[field] !== undefined,
    ).length;
    const counts: Record<RowStatus | 'all', number> = {
        all: item.total_rows,
        valid: item.valid_rows,
        warning: item.warning_rows,
        rejected: item.rejected_rows,
        duplicate: item.duplicate_rows,
    };
    const ignored =
        item.warning_rows + item.rejected_rows + item.duplicate_rows;
    const activeFilter = filter || 'all';

    return (
        <PageBody>
            <Head title={`Import — ${item.original_filename}`} />
            <PageHeader
                icon={showMapping ? Columns3 : FileSpreadsheet}
                title={
                    showMapping
                        ? 'Association des colonnes'
                        : item.status === 'completed'
                          ? 'Résultat de l’import'
                          : 'Vérification des lignes'
                }
                description={
                    <span className="break-all">
                        <span className="font-semibold text-foreground">
                            {item.original_filename}
                        </span>
                    </span>
                }
                meta={
                    <div className="flex flex-wrap items-center gap-x-4 gap-y-2 text-xs text-muted-foreground">
                        <ImportStatusBadge status={item.status} />
                        <span className="inline-flex items-center gap-1.5">
                            <Landmark className="size-3.5" aria-hidden="true" />
                            {item.establishment.name}
                        </span>
                        <span className="inline-flex items-center gap-1.5">
                            <CalendarDays
                                className="size-3.5"
                                aria-hidden="true"
                            />
                            {item.academic_year.name}
                        </span>
                        <span className="inline-flex items-center gap-1.5">
                            <UserRound
                                className="size-3.5"
                                aria-hidden="true"
                            />
                            {item.user.name}
                        </span>
                    </div>
                }
                actions={
                    <Button variant="outline" asChild>
                        <Link href="/imports">
                            <ArrowLeft aria-hidden="true" />
                            Historique des imports
                        </Link>
                    </Button>
                }
            />

            <ImportStepper
                current={stepOf(item.status, showMapping)}
                finished={item.status === 'completed' && !showMapping}
                failed={
                    !showMapping &&
                    (item.status === 'failed' || item.status === 'cancelled')
                }
            />

            {item.failure_reason ? (
                <Banner
                    tone="danger"
                    icon={XCircle}
                    title="Échec de l’import"
                    role="alert"
                >
                    {item.failure_reason}
                </Banner>
            ) : (
                item.status === 'failed' && (
                    <Banner
                        tone="danger"
                        icon={XCircle}
                        title="Échec de l’import"
                        role="alert"
                    />
                )
            )}

            {showMapping ? (
                <form onSubmit={analyze} className="space-y-6">
                    <section className="surface overflow-hidden">
                        <header className="flex flex-col gap-3 border-b px-5 py-4 sm:flex-row sm:items-start sm:justify-between sm:px-6">
                            <div className="flex min-w-0 items-start gap-3">
                                <span
                                    className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-accent text-primary"
                                    aria-hidden="true"
                                >
                                    <Columns3 className="size-[18px]" />
                                </span>
                                <div className="min-w-0">
                                    <h2 className="text-base font-bold text-foreground">
                                        Associer chaque champ à une colonne
                                    </h2>
                                    <p className="text-sm text-muted-foreground">
                                        Associez chaque champ à une colonne. Nom
                                        et niveau sont obligatoires. Les autres
                                        colonnes peuvent être ignorées.
                                    </p>
                                </div>
                            </div>
                            <span className="inline-flex shrink-0 items-center gap-1.5 self-start rounded-full bg-secondary px-3 py-1 text-xs font-semibold text-muted-foreground tabular-nums">
                                <Check
                                    className="size-3.5"
                                    aria-hidden="true"
                                />
                                {mappedCount} / {Object.keys(columns).length}{' '}
                                associé(s)
                            </span>
                        </header>
                        {item.headers.length > 0 && (
                            <div className="border-b bg-muted/40 px-5 py-3 sm:px-6">
                                <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                    Colonnes détectées dans le fichier
                                </p>
                                <ul className="mt-2 flex flex-wrap gap-1.5">
                                    {item.headers.map((header, index) => (
                                        <li
                                            key={index}
                                            className="inline-flex items-center gap-1 rounded-md border bg-card px-2 py-0.5 text-xs text-foreground"
                                        >
                                            <span className="text-muted-foreground tabular-nums">
                                                {index + 1}.
                                            </span>
                                            {header || '(sans titre)'}
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        )}
                        <div className="grid gap-3 p-5 sm:p-6 md:grid-cols-2 xl:grid-cols-3">
                            {Object.entries(columns).map(([field, label]) => {
                                const value = mapping.data.mapping[field];
                                const mapped = value !== undefined;
                                const required =
                                    REQUIRED_FIELDS.includes(field);
                                const error = mappingErrors[`mapping.${field}`];
                                return (
                                    <div
                                        key={field}
                                        className={cn(
                                            'space-y-2 rounded-xl border p-3 transition-colors',
                                            error
                                                ? 'border-danger/40 bg-danger-soft/40'
                                                : mapped
                                                  ? 'border-primary/25 bg-accent/40'
                                                  : 'bg-card',
                                        )}
                                    >
                                        <div className="flex items-center justify-between gap-2">
                                            <Label
                                                htmlFor={`map-${field}`}
                                                className="flex items-center gap-1"
                                            >
                                                {label}
                                                {required ? (
                                                    <span
                                                        className="text-destructive"
                                                        aria-hidden="true"
                                                    >
                                                        *
                                                    </span>
                                                ) : null}
                                                {required && (
                                                    <span className="sr-only">
                                                        (obligatoire)
                                                    </span>
                                                )}
                                            </Label>
                                            {mapped ? (
                                                <CircleCheck
                                                    className="size-4 shrink-0 text-success"
                                                    aria-hidden="true"
                                                />
                                            ) : (
                                                <CircleDashed
                                                    className={cn(
                                                        'size-4 shrink-0',
                                                        required
                                                            ? 'text-warning'
                                                            : 'text-muted-foreground',
                                                    )}
                                                    aria-hidden="true"
                                                />
                                            )}
                                        </div>
                                        <Select
                                            value={
                                                value === undefined
                                                    ? 'ignore'
                                                    : String(value)
                                            }
                                            onValueChange={(next) => {
                                                const updated = {
                                                    ...mapping.data.mapping,
                                                };
                                                if (next === 'ignore')
                                                    delete updated[field];
                                                else
                                                    updated[field] =
                                                        Number(next);
                                                mapping.setData(
                                                    'mapping',
                                                    updated,
                                                );
                                            }}
                                        >
                                            <SelectTrigger
                                                id={`map-${field}`}
                                                className="w-full bg-card"
                                                aria-invalid={
                                                    error ? true : undefined
                                                }
                                            >
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="ignore">
                                                    Ignorer
                                                </SelectItem>
                                                {item.headers.map(
                                                    (header, index) => (
                                                        <SelectItem
                                                            key={index}
                                                            value={String(
                                                                index,
                                                            )}
                                                        >
                                                            {index + 1}.{' '}
                                                            {header ||
                                                                '(sans titre)'}
                                                        </SelectItem>
                                                    ),
                                                )}
                                            </SelectContent>
                                        </Select>
                                        <InputError message={error} />
                                    </div>
                                );
                            })}
                        </div>
                    </section>
                    <InputError message={mapping.errors.mapping} />
                    <div className="flex flex-wrap items-center justify-end gap-2">
                        {item.status === 'ready' && editMapping && (
                            <Button
                                type="button"
                                variant="outline"
                                disabled={mapping.processing}
                                onClick={() => setEditMapping(false)}
                            >
                                Revenir à la vérification
                            </Button>
                        )}
                        <Button disabled={mapping.processing}>
                            {mapping.processing ? (
                                <Spinner />
                            ) : (
                                <ScanSearch aria-hidden="true" />
                            )}
                            {mapping.processing
                                ? 'Analyse…'
                                : 'Analyser les lignes'}
                        </Button>
                    </div>
                </form>
            ) : (
                <>
                    {item.status === 'ready' && (
                        <Banner
                            tone={item.valid_rows === 0 ? 'warning' : 'primary'}
                            icon={
                                item.valid_rows === 0
                                    ? AlertTriangle
                                    : ClipboardCheck
                            }
                            title={
                                item.valid_rows === 0
                                    ? 'Aucune ligne valide à importer'
                                    : 'Prêt à confirmer'
                            }
                            actions={
                                (canAnalyze || canCommit) && (
                                    <>
                                        {canAnalyze && (
                                            <Button
                                                variant="outline"
                                                className="bg-card"
                                                onClick={() =>
                                                    setEditMapping(true)
                                                }
                                            >
                                                <Columns3 aria-hidden="true" />
                                                Corriger les colonnes
                                            </Button>
                                        )}
                                        {canCommit && (
                                            <Button
                                                disabled={
                                                    item.valid_rows === 0 ||
                                                    commit.processing
                                                }
                                                onClick={() => setConfirm(true)}
                                            >
                                                <ClipboardCheck aria-hidden="true" />
                                                Confirmer l’import
                                            </Button>
                                        )}
                                    </>
                                )
                            }
                        >
                            {item.valid_rows === 0
                                ? 'Corrigez le fichier ou l’association des colonnes, puis relancez l’analyse.'
                                : `${formatNumber(item.valid_rows)} ligne(s) valide(s) sur ${formatNumber(item.total_rows)} seront importées${ignored > 0 ? ` ; ${formatNumber(ignored)} ligne(s) en avertissement, rejet ou doublon seront ignorées` : ''}.`}
                        </Banner>
                    )}
                    {(item.status === 'analyzing' ||
                        item.status === 'committing') && (
                        <Banner
                            tone="info"
                            icon={Loader2}
                            spin
                            role="status"
                            title={statuses[item.status]}
                        >
                            Traitement en cours : actualisez la page dans
                            quelques instants pour voir le résultat.
                        </Banner>
                    )}
                    {item.status === 'cancelled' && (
                        <Banner
                            tone="neutral"
                            icon={Ban}
                            title="Import annulé"
                        />
                    )}
                    {item.status === 'completed' && (
                        <section
                            role="status"
                            className={cn(
                                'rounded-2xl border p-4 sm:p-5',
                                toneSurfaces.success,
                            )}
                        >
                            <div className="flex items-center gap-3">
                                <span
                                    className="flex size-11 shrink-0 items-center justify-center rounded-xl bg-card text-success shadow-card"
                                    aria-hidden="true"
                                >
                                    <CircleCheck className="size-5" />
                                </span>
                                <h2 className="font-heading text-base font-bold">
                                    Import terminé
                                </h2>
                            </div>
                            <dl className="mt-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
                                {[
                                    ['identités créées', item.created_rows],
                                    ['identités complétées', item.updated_rows],
                                    ['déclarations créées', item.declared_rows],
                                    ['lignes ignorées', item.skipped_rows],
                                ].map(([label, value]) => (
                                    <div
                                        key={label}
                                        className="flex flex-col-reverse rounded-xl bg-card p-3 shadow-card"
                                    >
                                        <dt className="text-xs text-muted-foreground first-letter:uppercase">
                                            {label}
                                        </dt>
                                        <dd className="font-heading text-2xl font-extrabold text-foreground tabular-nums">
                                            {formatNumber(Number(value))}
                                        </dd>
                                    </div>
                                ))}
                            </dl>
                        </section>
                    )}
                    <InputError message={commitError} />

                    <div
                        className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5"
                        role="group"
                        aria-label="Filtrer les lignes par statut"
                    >
                        {(['all', ...rowStatusOrder] as const).map((key) => {
                            const tile = rowTiles[key];
                            const active = activeFilter === key;
                            return (
                                <button
                                    key={key}
                                    type="button"
                                    aria-pressed={active}
                                    onClick={() => applyFilter(key)}
                                    className={cn(
                                        'surface group flex flex-col gap-2 p-4 text-left transition-all hover:-translate-y-0.5 hover:shadow-elevated focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none motion-reduce:hover:translate-y-0',
                                        key === 'all' &&
                                            'col-span-2 sm:col-span-1',
                                        active &&
                                            'border-primary ring-2 ring-primary/30',
                                    )}
                                >
                                    <span className="flex items-center justify-between gap-2">
                                        <span className="text-sm font-medium text-muted-foreground">
                                            {tile.label}
                                        </span>
                                        <span
                                            className={cn(
                                                'flex size-9 shrink-0 items-center justify-center rounded-lg',
                                                toneIcons[tile.tone],
                                            )}
                                            aria-hidden="true"
                                        >
                                            <tile.icon className="size-[18px]" />
                                        </span>
                                    </span>
                                    <span className="font-heading text-2xl font-extrabold tracking-tight text-foreground tabular-nums">
                                        {formatNumber(counts[key])}
                                    </span>
                                    <span className="text-xs text-muted-foreground">
                                        {active ? 'Filtre actif' : 'Afficher'}
                                    </span>
                                </button>
                            );
                        })}
                    </div>

                    <section className="surface overflow-hidden">
                        <header className="flex flex-col gap-3 border-b px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                            <div className="flex min-w-0 items-center gap-3">
                                <span
                                    className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-accent text-primary"
                                    aria-hidden="true"
                                >
                                    <Rows3 className="size-[18px]" />
                                </span>
                                <div className="min-w-0">
                                    <h2 className="text-base font-bold text-foreground">
                                        Lignes du fichier
                                    </h2>
                                    <p className="text-xs text-muted-foreground">
                                        {formatNumber(rows.total)} ligne(s)
                                        {filter
                                            ? ` · ${statuses[filter as RowStatus] ?? filter}`
                                            : ''}
                                    </p>
                                </div>
                            </div>
                            <div className="flex flex-wrap items-center gap-2">
                                {canExport && (
                                    <Button variant="outline" asChild>
                                        <a
                                            href={`/imports/${item.uuid}/errors`}
                                        >
                                            <Download aria-hidden="true" />
                                            Télécharger les erreurs
                                        </a>
                                    </Button>
                                )}
                            </div>
                        </header>
                        {rows.data.length === 0 ? (
                            <EmptyState
                                compact
                                illustration="search"
                                title="Aucune ligne pour ce filtre."
                                action={
                                    filter ? (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() => applyFilter('all')}
                                        >
                                            Afficher toutes les lignes
                                        </Button>
                                    ) : undefined
                                }
                            />
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="data-table min-w-[56rem]">
                                    <caption className="sr-only">
                                        Lignes de l’import
                                    </caption>
                                    <thead>
                                        <tr>
                                            <th scope="col" className="w-16">
                                                Ligne
                                            </th>
                                            <th scope="col">Identité</th>
                                            <th scope="col">Matricule</th>
                                            <th scope="col">
                                                Niveau / parcours
                                            </th>
                                            <th scope="col">Statut</th>
                                            <th scope="col">Observations</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {rows.data.map((row) => {
                                            const errors = row.errors ?? [];
                                            const warnings = row.warnings ?? [];
                                            return (
                                                <tr key={row.id}>
                                                    <td className="font-mono text-xs text-muted-foreground tabular-nums">
                                                        {row.row_number}
                                                    </td>
                                                    <td>
                                                        <p className="font-semibold text-foreground">
                                                            {row.data.last_name}{' '}
                                                            <span className="font-normal">
                                                                {
                                                                    row.data
                                                                        .first_names
                                                                }
                                                            </span>
                                                        </p>
                                                        {row.matched_student_id && (
                                                            <p className="mt-0.5 inline-flex items-center gap-1 text-xs text-muted-foreground">
                                                                <UserCheck
                                                                    className="size-3.5 text-info"
                                                                    aria-hidden="true"
                                                                />
                                                                Identité
                                                                existante #
                                                                {
                                                                    row.matched_student_id
                                                                }
                                                            </p>
                                                        )}
                                                    </td>
                                                    <td className="font-mono text-xs">
                                                        {row.data
                                                            .student_number ??
                                                            '—'}
                                                    </td>
                                                    <td>
                                                        <span className="font-medium">
                                                            {row.data.level}
                                                        </span>
                                                        <span className="text-muted-foreground">
                                                            {' '}
                                                            /{' '}
                                                            {row.data.program ??
                                                                '—'}
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div className="flex flex-col items-start gap-1">
                                                            <RowStatusBadge
                                                                status={
                                                                    row.status
                                                                }
                                                            />
                                                            {row.resolution ===
                                                                'imported' && (
                                                                <span className="inline-flex items-center gap-1 text-xs font-medium text-success-foreground">
                                                                    <Check
                                                                        className="size-3.5 text-success"
                                                                        aria-hidden="true"
                                                                    />
                                                                    Importée
                                                                </span>
                                                            )}
                                                        </div>
                                                    </td>
                                                    <td className="min-w-64">
                                                        {errors.length +
                                                            warnings.length >
                                                            0 ||
                                                        (row.status ===
                                                            'duplicate' &&
                                                            !warnings.length) ? (
                                                            <ul className="flex flex-col gap-1">
                                                                {errors.map(
                                                                    (
                                                                        issue,
                                                                        i,
                                                                    ) => (
                                                                        <IssueChip
                                                                            key={`e${i}`}
                                                                            tone="danger"
                                                                            label={
                                                                                columns[
                                                                                    issue
                                                                                        .field
                                                                                ] ??
                                                                                issue.field
                                                                            }
                                                                            message={
                                                                                issue.message
                                                                            }
                                                                        />
                                                                    ),
                                                                )}
                                                                {warnings.map(
                                                                    (
                                                                        issue,
                                                                        i,
                                                                    ) => (
                                                                        <IssueChip
                                                                            key={`w${i}`}
                                                                            tone={
                                                                                row.status ===
                                                                                'duplicate'
                                                                                    ? 'info'
                                                                                    : 'warning'
                                                                            }
                                                                            label={
                                                                                columns[
                                                                                    issue
                                                                                        .field
                                                                                ] ??
                                                                                issue.field
                                                                            }
                                                                            message={
                                                                                issue.message
                                                                            }
                                                                        />
                                                                    ),
                                                                )}
                                                                {row.status ===
                                                                    'duplicate' &&
                                                                    !warnings.length && (
                                                                        <IssueChip
                                                                            tone="info"
                                                                            message="Déclaration identique déjà présente."
                                                                        />
                                                                    )}
                                                            </ul>
                                                        ) : (
                                                            <span className="text-muted-foreground">
                                                                —
                                                            </span>
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
                    <Pagination links={rows.links} />
                </>
            )}

            <Dialog
                open={confirm}
                onOpenChange={(open) => {
                    if (!commit.processing) setConfirm(open);
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <span
                            className="mb-1 flex size-11 items-center justify-center rounded-xl bg-accent text-primary"
                            aria-hidden="true"
                        >
                            <ClipboardCheck className="size-5" />
                        </span>
                        <DialogTitle>4. Confirmer l’import</DialogTitle>
                        <DialogDescription>
                            Seules les {item.valid_rows} lignes valides seront
                            traitées. Les avertissements, rejets et doublons
                            seront ignorés. Les données officielles
                            contradictoires ne seront jamais écrasées.
                        </DialogDescription>
                    </DialogHeader>
                    <dl className="grid grid-cols-2 gap-3 text-sm">
                        <div
                            className={cn(
                                'rounded-xl border p-3',
                                toneSurfaces.success,
                            )}
                        >
                            <dt className="text-xs">Importées</dt>
                            <dd className="font-heading text-xl font-extrabold tabular-nums">
                                {formatNumber(item.valid_rows)}
                            </dd>
                        </div>
                        <div
                            className={cn(
                                'rounded-xl border p-3',
                                toneSurfaces.neutral,
                            )}
                        >
                            <dt className="text-xs text-muted-foreground">
                                Ignorées
                            </dt>
                            <dd className="font-heading text-xl font-extrabold tabular-nums">
                                {formatNumber(ignored)}
                            </dd>
                        </div>
                    </dl>
                    <InputError message={commitError} />
                    <DialogFooter>
                        <Button
                            variant="outline"
                            disabled={commit.processing}
                            onClick={() => setConfirm(false)}
                        >
                            Annuler
                        </Button>
                        <Button
                            disabled={commit.processing}
                            onClick={() =>
                                commit.post(`/imports/${item.uuid}/commit`, {
                                    onSuccess: () => setConfirm(false),
                                })
                            }
                        >
                            {commit.processing && <Spinner />}
                            {commit.processing
                                ? 'Import…'
                                : 'Confirmer l’import'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </PageBody>
    );
}
ImportShow.layout = { breadcrumbs: [{ title: 'Imports', href: '/imports' }] };
