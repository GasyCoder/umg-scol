import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    AlertCircle,
    Ban,
    CalendarDays,
    CircleCheck,
    ClipboardCheck,
    Eye,
    FilePenLine,
    PlayCircle,
    X,
    FileSpreadsheet,
    FolderOpen,
    Inbox,
    Mail,
    RotateCcw,
    Search,
    SlidersHorizontal,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useEffect, useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import { EmptyState } from '@/components/empty-state';
import { IconAction } from '@/components/icon-action';
import { PageBody, PageHeader } from '@/components/page-header';
import {
    applicationStatusLabels,
    formatNumber,
} from '@/components/reporting/shared';
import {
    formatDate,
    Pagination,
    StatusBadge,
} from '@/components/scholarships/shared';
import type { Option, PageLink } from '@/components/scholarships/shared';
import { toneFor } from '@/components/tone-badge';
import type { Tone } from '@/components/tone-badge';
import { TrashButton } from '@/components/trash/trash-button';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';

type Row = {
    id: number;
    uuid: string;
    /** The one number of the file: its DDB number. */
    reference: string;
    student: {
        last_name: string;
        first_names: string | null;
        cin: string | null;
        birth_date: string | null;
    };
    establishment: { code: string; name: string };
    level: { code: string; name: string };
    status: string;
    status_label: string;
    received_at: string;
    /** Decisions this account may take now on the file. */
    actions: string[];
    /** Why « Valider » cannot be used yet, empty when it can. */
    validationBlockers: string[];
    /** Situation still waiting for the establishment's list. */
    awaiting_list: boolean;
};
type Decision = 'in_review' | 'validated' | 'needs_correction' | 'rejected';

/** Wording of each decision, for one file or a selection. */
const decisions: Record<
    Decision,
    {
        label: string;
        icon: LucideIcon;
        tone: string;
        description: string;
        reason: boolean;
    }
> = {
    in_review: {
        label: 'Mettre en vérification',
        icon: PlayCircle,
        tone: '',
        description: 'La vérification du dossier commence.',
        reason: false,
    },
    validated: {
        label: 'Valider',
        icon: CircleCheck,
        tone: 'text-success',
        description:
            'Le dossier est déclaré conforme : décision définitive. Les contrôles encore à faire sont enregistrés conformes à votre nom ; un contrôle non conforme ou une situation en attente de la liste bloque la validation.',
        reason: false,
    },
    needs_correction: {
        label: 'Demander une correction',
        icon: FilePenLine,
        tone: 'text-warning-foreground',
        description:
            'Indiquez ce qui doit être corrigé ou complété dans le dossier papier.',
        reason: true,
    },
    rejected: {
        label: 'Rejeter',
        icon: Ban,
        tone: 'text-danger',
        description: 'Le rejet est définitif. Indiquez un motif factuel.',
        reason: true,
    },
};
const DECISION_ORDER: Decision[] = [
    'in_review',
    'validated',
    'needs_correction',
    'rejected',
];
type Named = { id: number; name: string };

const LIST_URL = '/scholarships/applications';
const STATUS_TABS = [
    'pending',
    'awaiting_list',
    'in_review',
    'needs_correction',
    'validated',
    'rejected',
];

const dots: Record<Tone, string> = {
    primary: 'bg-primary',
    success: 'bg-success',
    warning: 'bg-warning',
    info: 'bg-info',
    danger: 'bg-danger',
    neutral: 'bg-muted-foreground/60',
};

/** Query string without empty values, so the URL stays readable. */
const clean = (data: Record<string, string>) =>
    Object.fromEntries(Object.entries(data).filter(([, item]) => item !== ''));

function Field({
    id,
    label,
    className = '',
    children,
}: {
    id: string;
    label: string;
    className?: string;
    children: ReactNode;
}) {
    return (
        <div className={`grid min-w-0 content-start gap-1.5 ${className}`}>
            <Label
                htmlFor={id}
                className="text-xs font-medium text-muted-foreground"
            >
                {label}
            </Label>
            {children}
        </div>
    );
}

export default function ApplicationsIndex({
    applications,
    filters,
    statusCounts,
    options,
    canExport,
    canReceive,
    bulkActions,
    canNotifyStudents,
}: {
    applications: { data: Row[]; total: number; links: PageLink[] };
    filters: Record<string, string | number | undefined>;
    statusCounts: Record<string, number>;
    options: {
        academicYears: Named[];
        statuses: Option[];
        establishments: Named[];
        levels: Named[];
    };
    canExport: boolean;
    canReceive: boolean;
    /** Decisions offered on a selection; each file is checked again by the server. */
    bulkActions: Decision[];
    /** Email the students about missing or doubtful information (ADR-088). */
    canNotifyStudents: boolean;
}) {
    // Files ticked on this page, and the decision being confirmed (one file or the selection).
    const [selected, setSelected] = useState<string[]>([]);
    const [pending, setPending] = useState<{
        decision: Decision;
        uuids: string[];
        label: string;
    } | null>(null);
    // « Demander une correction » emails the students by default when the account may.
    const decide = useForm({ reason: '', notify_student: true });
    const [notifying, setNotifying] = useState(false);
    const [notifyProcessing, setNotifyProcessing] = useState(false);
    const notifySelection = () => {
        setNotifyProcessing(true);
        router.post(
            '/students/notifications',
            { applications: selected },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setNotifying(false);
                    setSelected([]);
                },
                onFinish: () => setNotifyProcessing(false),
            },
        );
    };
    const pageUuids = applications.data.map((item) => item.uuid);
    const allSelected =
        pageUuids.length > 0 &&
        pageUuids.every((uuid) => selected.includes(uuid));
    useEffect(() => setSelected([]), [applications]);
    const ask = (decision: Decision, uuids: string[], label: string) => {
        decide.reset();
        decide.clearErrors();
        setPending({ decision, uuids, label });
    };
    const confirm = () => {
        if (!pending) return;
        const reason = decisions[pending.decision].reason
            ? decide.data.reason
            : '';
        const notify_student =
            pending.decision === 'needs_correction' &&
            canNotifyStudents &&
            decide.data.notify_student;
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                setPending(null);
                setSelected([]);
            },
        };
        // One file from its row: the usual decision route, back to the list.
        if (pending.label !== 'selection') {
            decide.transform(() => ({
                status: pending.decision,
                reason,
                from_list: true,
                notify_student,
            }));
            decide.post(`${LIST_URL}/${pending.uuids[0]}/status`, options);
            return;
        }
        decide.transform(() => ({
            status: pending.decision,
            applications: pending.uuids,
            reason,
            notify_student,
        }));
        decide.post(`${LIST_URL}/bulk`, options);
    };
    const decideErrors = decide.errors as Record<string, string | undefined>;
    const value = (key: string) =>
        filters[key] === undefined || filters[key] === null
            ? ''
            : String(filters[key]);
    const form = useForm<Record<string, string>>({
        search: value('search'),
        academic_year_id: value('academic_year_id'),
        status: value('status'),
        establishment_id: value('establishment_id'),
        level_id: value('level_id'),
        received_from: value('received_from'),
        received_to: value('received_to'),
    });
    const errors = form.errors as Record<string, string | undefined>;
    const applied = Object.fromEntries(
        Object.keys(form.data).map((key): [string, string] => [
            key,
            value(key),
        ]),
    );
    const activeStatus = applied.status;
    const hasFilters = Object.values(applied).some((item) => item !== '');
    const hasEdits = Object.values(form.data).some((item) => item !== '');
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.get(LIST_URL, { preserveState: true });
    };
    // A tab keeps the filters the list currently shows and only swaps the status.
    const showStatus = (status: string) => {
        form.setData('status', status);
        router.get(LIST_URL, clean({ ...applied, status }), {
            preserveState: true,
            preserveScroll: true,
        });
    };
    const reset = () => {
        form.setData(
            Object.fromEntries(
                Object.keys(form.data).map((key): [string, string] => [
                    key,
                    '',
                ]),
            ),
        );
        form.clearErrors();
        router.get(LIST_URL, {}, { preserveState: true });
    };
    const named = (items: Named[]) =>
        items.map((item) => ({ value: String(item.id), label: item.name }));
    const selects: { key: string; label: string; items: Option[] }[] = [
        {
            key: 'academic_year_id',
            label: 'Année universitaire',
            items: named(options.academicYears),
        },
        {
            key: 'establishment_id',
            label: 'Établissement',
            items: named(options.establishments),
        },
        { key: 'level_id', label: 'Niveau', items: named(options.levels) },
    ];
    const statusLabel = (status: string) =>
        applicationStatusLabels[status] ??
        options.statuses.find((item) => item.value === status)?.label ??
        status;
    const tabs = [
        {
            value: '',
            label: 'Tous',
            count: Object.values(statusCounts).reduce(
                (sum, count) => sum + count,
                0,
            ),
        },
        ...STATUS_TABS.map((status) => ({
            value: status,
            label: statusLabel(status),
            count: statusCounts[status] ?? 0,
        })),
    ];
    // Export the applied filters (what the list shows), not unsubmitted form edits.
    const exportUrl = `${LIST_URL}/export?${new URLSearchParams(
        clean(applied),
    ).toString()}`;
    const fieldError = errors.received_to ?? errors.search;
    return (
        <PageBody>
            <Head title="Dossiers de bourse" />
            <PageHeader
                icon={FolderOpen}
                title="Dossiers de bourse"
                description="Dossiers papier reçus par la Scolarité Centrale et leur traitement."
                actions={
                    <>
                        {canExport && (
                            <Button
                                asChild
                                className="bg-success text-primary-foreground hover:bg-success/90 focus-visible:ring-success/40"
                            >
                                <a href={exportUrl}>
                                    <FileSpreadsheet aria-hidden="true" />
                                    Exporter (Excel)
                                </a>
                            </Button>
                        )}
                        {canReceive && (
                            <Button asChild>
                                <Link href="/scholarships/reception">
                                    <Inbox aria-hidden="true" />
                                    Réceptionner un dossier
                                </Link>
                            </Button>
                        )}
                    </>
                }
            />

            <nav
                aria-label="Filtrer par statut"
                className="-mx-4 overflow-x-auto px-4 pb-1 md:mx-0 md:px-0"
            >
                <ul className="flex w-full min-w-max gap-2">
                    {tabs.map((tab) => {
                        const active = activeStatus === tab.value;
                        return (
                            <li key={tab.value || 'all'}>
                                <button
                                    type="button"
                                    onClick={() => showStatus(tab.value)}
                                    aria-current={active ? 'true' : undefined}
                                    className={cn(
                                        'flex h-9 items-center gap-2 rounded-full border px-3.5 text-sm font-medium whitespace-nowrap transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                        active
                                            ? 'border-primary bg-primary text-primary-foreground shadow-sm'
                                            : 'bg-card text-muted-foreground hover:border-primary/40 hover:text-foreground',
                                    )}
                                >
                                    {tab.value && (
                                        <span
                                            className={cn(
                                                'size-2 shrink-0 rounded-full',
                                                active
                                                    ? 'bg-primary-foreground'
                                                    : dots[toneFor(tab.value)],
                                            )}
                                            aria-hidden="true"
                                        />
                                    )}
                                    {tab.label}
                                    <span
                                        className={cn(
                                            'min-w-6 rounded-full px-1.5 py-0.5 text-center text-xs font-semibold tabular-nums',
                                            active
                                                ? 'bg-primary-foreground/20 text-primary-foreground'
                                                : 'bg-secondary text-secondary-foreground',
                                        )}
                                    >
                                        {formatNumber(tab.count)}
                                    </span>
                                </button>
                            </li>
                        );
                    })}
                </ul>
            </nav>

            <form
                onSubmit={submit}
                className="surface space-y-4 p-4 sm:p-5"
                aria-label="Filtres des dossiers"
            >
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 2xl:grid-cols-[minmax(16rem,2fr)_repeat(5,minmax(0,1fr))]">
                    <Field
                        id="filter-search"
                        label="Recherche"
                        className="sm:col-span-2 2xl:col-span-1"
                    >
                        <div className="relative">
                            <Search
                                className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                aria-hidden="true"
                            />
                            <Input
                                id="filter-search"
                                type="search"
                                className="pl-9"
                                placeholder="N° DDB, CIN, nom ou prénom"
                                maxLength={100}
                                value={form.data.search}
                                onChange={(e) =>
                                    form.setData('search', e.target.value)
                                }
                            />
                        </div>
                    </Field>
                    {selects.map((select) => (
                        <Field
                            key={select.key}
                            id={`filter-${select.key}`}
                            label={select.label}
                        >
                            <Select
                                value={form.data[select.key] || 'all'}
                                onValueChange={(item) =>
                                    form.setData(
                                        select.key,
                                        item === 'all' ? '' : item,
                                    )
                                }
                            >
                                <SelectTrigger
                                    id={`filter-${select.key}`}
                                    className="w-full"
                                >
                                    <SelectValue placeholder={select.label} />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Tous</SelectItem>
                                    {select.items.map((item) => (
                                        <SelectItem
                                            key={item.value}
                                            value={item.value}
                                        >
                                            {item.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </Field>
                    ))}
                    <Field id="filter-received-from" label="Reçu à partir du">
                        <Input
                            id="filter-received-from"
                            type="date"
                            value={form.data.received_from}
                            onChange={(e) =>
                                form.setData('received_from', e.target.value)
                            }
                        />
                    </Field>
                    <Field id="filter-received-to" label="Reçu jusqu’au">
                        <Input
                            id="filter-received-to"
                            type="date"
                            value={form.data.received_to}
                            aria-invalid={errors.received_to ? true : undefined}
                            onChange={(e) =>
                                form.setData('received_to', e.target.value)
                            }
                        />
                    </Field>
                </div>
                <div className="flex flex-wrap items-center justify-between gap-3 border-t pt-4">
                    {fieldError ? (
                        <p
                            role="alert"
                            className="flex items-center gap-1.5 text-sm text-destructive"
                        >
                            <AlertCircle
                                className="size-4 shrink-0"
                                aria-hidden="true"
                            />
                            {fieldError}
                        </p>
                    ) : (
                        hasFilters && (
                            <p className="text-xs text-muted-foreground">
                                Filtres appliqués à la liste
                                {canExport && ' et à l’export'}.
                            </p>
                        )
                    )}
                    <div className="ml-auto flex flex-wrap gap-2">
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={reset}
                            disabled={
                                form.processing || (!hasFilters && !hasEdits)
                            }
                        >
                            <RotateCcw aria-hidden="true" />
                            Réinitialiser
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            <SlidersHorizontal aria-hidden="true" />
                            Filtrer
                        </Button>
                    </div>
                </div>
            </form>

            <section
                className="surface overflow-hidden"
                aria-labelledby="applications-total"
            >
                <div className="flex flex-wrap items-center justify-between gap-2 border-b px-4 py-3 sm:px-5">
                    <p
                        id="applications-total"
                        className="text-sm text-muted-foreground"
                        aria-live="polite"
                    >
                        <span className="font-heading text-base font-bold text-foreground tabular-nums">
                            {formatNumber(applications.total)}
                        </span>{' '}
                        {applications.total > 1 ? 'dossiers' : 'dossier'}
                        {activeStatus && ` · ${statusLabel(activeStatus)}`}
                    </p>
                    {activeStatus && (
                        <StatusBadge
                            status={activeStatus}
                            label={statusLabel(activeStatus)}
                        />
                    )}
                </div>
                {applications.data.length === 0 ? (
                    <EmptyState
                        illustration={hasFilters ? 'search' : 'scholarship'}
                        title="Aucun dossier ne correspond à ces critères."
                        description={
                            hasFilters
                                ? 'Élargissez la recherche ou réinitialisez les filtres.'
                                : 'Les dossiers papier réceptionnés apparaîtront ici.'
                        }
                        action={
                            (hasFilters || canReceive) && (
                                <div className="flex flex-wrap justify-center gap-2">
                                    {hasFilters && (
                                        <Button
                                            variant="outline"
                                            onClick={reset}
                                        >
                                            <RotateCcw aria-hidden="true" />
                                            Réinitialiser les filtres
                                        </Button>
                                    )}
                                    {canReceive && (
                                        <Button asChild>
                                            <Link href="/scholarships/reception">
                                                <Inbox aria-hidden="true" />
                                                Réceptionner un dossier
                                            </Link>
                                        </Button>
                                    )}
                                </div>
                            )
                        }
                    />
                ) : (
                    <div className="overflow-x-auto">
                        <table className="data-table min-w-[60rem]">
                            <caption className="sr-only">
                                Dossiers de bourse
                            </caption>
                            <thead>
                                <tr>
                                    <th scope="col" className="w-10">
                                        <Checkbox
                                            aria-label="Sélectionner tous les dossiers de la page"
                                            checked={
                                                allSelected
                                                    ? true
                                                    : selected.length > 0
                                                      ? 'indeterminate'
                                                      : false
                                            }
                                            onCheckedChange={(checked) =>
                                                setSelected(
                                                    checked === true
                                                        ? pageUuids
                                                        : [],
                                                )
                                            }
                                        />
                                    </th>
                                    {[
                                        'N° DDB',
                                        'Étudiant',
                                        'CIN',
                                        'Étab.',
                                        'Niveau',
                                        'Statut',
                                        'Réception',
                                    ].map((label) => (
                                        <th key={label} scope="col">
                                            {label}
                                        </th>
                                    ))}
                                    <th scope="col">
                                        <span className="sr-only">Actions</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {applications.data.map((item) => {
                                    const checked = selected.includes(
                                        item.uuid,
                                    );
                                    const reference = item.reference;
                                    return (
                                        <tr
                                            key={item.id}
                                            data-state={
                                                checked ? 'selected' : undefined
                                            }
                                            className="data-[state=selected]:bg-accent/40"
                                        >
                                            <td>
                                                <Checkbox
                                                    aria-label={`Sélectionner le dossier ${reference}`}
                                                    checked={checked}
                                                    onCheckedChange={(value) =>
                                                        setSelected(
                                                            (current) =>
                                                                value === true
                                                                    ? [
                                                                          ...current,
                                                                          item.uuid,
                                                                      ]
                                                                    : current.filter(
                                                                          (
                                                                              uuid,
                                                                          ) =>
                                                                              uuid !==
                                                                              item.uuid,
                                                                      ),
                                                        )
                                                    }
                                                />
                                            </td>
                                            <td className="whitespace-nowrap">
                                                <Link
                                                    href={`${LIST_URL}/${item.uuid}`}
                                                    className="rounded font-mono text-xs font-semibold text-foreground hover:text-primary hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                                >
                                                    {reference}
                                                </Link>
                                            </td>
                                            <td>
                                                <p className="font-semibold text-foreground">
                                                    {item.student.last_name}{' '}
                                                    {item.student.first_names}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    Né(e) le{' '}
                                                    {formatDate(
                                                        item.student.birth_date,
                                                    )}
                                                </p>
                                            </td>
                                            <td className="font-mono text-xs whitespace-nowrap">
                                                {item.student.cin ?? (
                                                    <span className="text-muted-foreground">
                                                        —
                                                    </span>
                                                )}
                                            </td>
                                            <td className="whitespace-nowrap">
                                                <Abbr
                                                    code={
                                                        item.establishment.code
                                                    }
                                                    name={
                                                        item.establishment.name
                                                    }
                                                />
                                            </td>
                                            <td className="whitespace-nowrap">
                                                <Abbr
                                                    code={item.level.code}
                                                    name={item.level.name}
                                                />
                                            </td>
                                            <td>
                                                <StatusBadge
                                                    status={item.status}
                                                    label={item.status_label}
                                                />
                                                {item.awaiting_list &&
                                                    item.status !==
                                                        'awaiting_list' &&
                                                    item.status !==
                                                        'rejected' && (
                                                        <p className="mt-1 text-xs text-muted-foreground">
                                                            Liste attendue
                                                        </p>
                                                    )}
                                            </td>
                                            <td className="whitespace-nowrap text-muted-foreground tabular-nums">
                                                <span className="inline-flex items-center gap-1.5">
                                                    <CalendarDays
                                                        className="size-3.5"
                                                        aria-hidden="true"
                                                    />
                                                    {formatDate(
                                                        item.received_at,
                                                    )}
                                                </span>
                                            </td>
                                            <td>
                                                <div className="flex items-center justify-end gap-1">
                                                    <DecisionMenu
                                                        actions={item.actions}
                                                        blockers={
                                                            item.validationBlockers
                                                        }
                                                        reference={reference}
                                                        onChoose={(decision) =>
                                                            ask(
                                                                decision,
                                                                [item.uuid],
                                                                reference,
                                                            )
                                                        }
                                                    />
                                                    <IconAction
                                                        icon={Eye}
                                                        label="Consulter le dossier"
                                                        href={`${LIST_URL}/${item.uuid}`}
                                                    />
                                                    <TrashButton
                                                        iconOnly
                                                        kind="applications"
                                                        uuid={item.uuid}
                                                        label={reference}
                                                    />
                                                </div>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                )}
            </section>
            <Pagination links={applications.links} />

            {selected.length > 0 && (
                <div className="sticky bottom-0 z-20 pb-3 md:pb-4">
                    <div
                        role="toolbar"
                        aria-label="Décisions sur la sélection"
                        className="flex flex-col gap-3 rounded-xl border bg-card/90 px-4 py-3 shadow-elevated backdrop-blur-md sm:flex-row sm:items-center sm:justify-between"
                    >
                        <p className="text-sm font-semibold text-foreground">
                            {selected.length} dossier
                            {selected.length > 1 ? 's' : ''} sélectionné
                            {selected.length > 1 ? 's' : ''}
                            <span className="font-normal text-muted-foreground">
                                {' '}
                                sur cette page
                            </span>
                        </p>
                        <div className="flex flex-wrap gap-2">
                            {DECISION_ORDER.filter((decision) =>
                                bulkActions.includes(decision),
                            ).map((decision) => {
                                const meta = decisions[decision];
                                return (
                                    <Button
                                        key={decision}
                                        variant="outline"
                                        size="sm"
                                        className={meta.tone}
                                        onClick={() =>
                                            ask(decision, selected, 'selection')
                                        }
                                    >
                                        <meta.icon aria-hidden="true" />
                                        {meta.label}
                                    </Button>
                                );
                            })}
                            {canNotifyStudents && (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setNotifying(true)}
                                >
                                    <Mail aria-hidden="true" />
                                    Prévenir par email
                                </Button>
                            )}
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => setSelected([])}
                            >
                                <X aria-hidden="true" />
                                Annuler
                            </Button>
                        </div>
                    </div>
                </div>
            )}

            <Dialog
                open={pending !== null}
                onOpenChange={(open) => {
                    if (!open && !decide.processing) setPending(null);
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {pending
                                ? `${decisions[pending.decision].label} ${
                                      pending.label === 'selection'
                                          ? `${pending.uuids.length} dossier${pending.uuids.length > 1 ? 's' : ''}`
                                          : `le dossier ${pending.label}`
                                  }`
                                : ''}
                        </DialogTitle>
                        <DialogDescription>
                            {pending
                                ? decisions[pending.decision].description
                                : ''}
                            {pending?.label === 'selection' &&
                                ' Chaque dossier est contrôlé séparément : ceux qui ne le permettent pas sont ignorés et signalés.'}
                        </DialogDescription>
                    </DialogHeader>
                    {pending && decisions[pending.decision].reason && (
                        <div className="space-y-2">
                            <Label htmlFor="decision-reason">Motif</Label>
                            <textarea
                                id="decision-reason"
                                autoFocus
                                className="min-h-28 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive"
                                maxLength={1000}
                                value={decide.data.reason}
                                aria-invalid={
                                    decideErrors.reason ? true : undefined
                                }
                                onChange={(event) =>
                                    decide.setData('reason', event.target.value)
                                }
                            />
                            {decideErrors.reason ? (
                                <p
                                    role="alert"
                                    className="text-sm text-destructive"
                                >
                                    {decideErrors.reason}
                                </p>
                            ) : (
                                <p className="text-xs text-muted-foreground">
                                    {decide.data.reason.length}/1000 caractères,
                                    10 au minimum.
                                </p>
                            )}
                        </div>
                    )}
                    {pending?.decision === 'needs_correction' &&
                        canNotifyStudents && (
                            <Label
                                htmlFor="decision-notify"
                                className="flex items-start gap-2.5 font-normal"
                            >
                                <Checkbox
                                    id="decision-notify"
                                    className="mt-0.5"
                                    checked={decide.data.notify_student}
                                    onCheckedChange={(checked) =>
                                        decide.setData(
                                            'notify_student',
                                            checked === true,
                                        )
                                    }
                                />
                                <span className="text-sm">
                                    Prévenir par email
                                    <span className="block text-xs text-muted-foreground">
                                        Chaque étudiant reçoit ce motif et la
                                        liste des informations à compléter de sa
                                        fiche (s’il a un email).
                                    </span>
                                </span>
                            </Label>
                        )}
                    {(decideErrors.status ?? decideErrors.applications) && (
                        <p role="alert" className="text-sm text-destructive">
                            {decideErrors.status ?? decideErrors.applications}
                        </p>
                    )}
                    <DialogFooter>
                        <Button
                            variant="outline"
                            disabled={decide.processing}
                            onClick={() => setPending(null)}
                        >
                            Annuler
                        </Button>
                        <Button
                            variant={
                                pending?.decision === 'rejected'
                                    ? 'destructive'
                                    : 'default'
                            }
                            disabled={decide.processing}
                            onClick={confirm}
                        >
                            {decide.processing && <Spinner />}
                            {pending ? decisions[pending.decision].label : ''}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog
                open={notifying}
                onOpenChange={(open) => !notifyProcessing && setNotifying(open)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            Prévenir {selected.length} étudiant
                            {selected.length > 1 ? 's' : ''} par email
                        </DialogTitle>
                        <DialogDescription>
                            Chaque étudiant reçoit la liste des informations
                            vides ou douteuses de sa fiche et les corrige depuis
                            le portail. Ceux sans email ou sans information à
                            signaler sont ignorés et comptés.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            disabled={notifyProcessing}
                            onClick={() => setNotifying(false)}
                        >
                            Annuler
                        </Button>
                        <Button
                            disabled={notifyProcessing}
                            onClick={notifySelection}
                        >
                            {notifyProcessing && <Spinner />}
                            Envoyer les emails
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </PageBody>
    );
}
/** Short code in the table, full name on hover and for screen readers. */
function Abbr({ code, name }: { code: string; name: string }) {
    if (code === name) return <span>{name}</span>;
    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <abbr
                    title={name}
                    className="cursor-help font-medium no-underline"
                >
                    {code}
                </abbr>
            </TooltipTrigger>
            <TooltipContent>{name}</TooltipContent>
        </Tooltip>
    );
}

/** Row action (icon only, label in the tooltip) opening the decisions possible on the file. */
function DecisionMenu({
    actions,
    blockers,
    reference,
    onChoose,
}: {
    actions: string[];
    /** Why « Valider » is greyed: shown under it. */
    blockers: string[];
    reference: string;
    onChoose: (decision: Decision) => void;
}) {
    const available = DECISION_ORDER.filter((decision) =>
        actions.includes(decision),
    );
    if (available.length === 0)
        return <span className="size-8" aria-hidden="true" />;
    return (
        <DropdownMenu>
            <Tooltip>
                <TooltipTrigger asChild>
                    <DropdownMenuTrigger asChild>
                        <Button
                            variant="ghost"
                            size="icon"
                            className="size-8 text-primary"
                            aria-label={`Décider du dossier ${reference}`}
                        >
                            <ClipboardCheck
                                className="size-4"
                                aria-hidden="true"
                            />
                        </Button>
                    </DropdownMenuTrigger>
                </TooltipTrigger>
                <TooltipContent>Décider</TooltipContent>
            </Tooltip>
            <DropdownMenuContent align="end" className="w-72">
                <DropdownMenuLabel className="font-mono text-xs text-muted-foreground">
                    {reference}
                </DropdownMenuLabel>
                <DropdownMenuSeparator />
                {available.map((decision) => {
                    const meta = decisions[decision];
                    const blocked =
                        decision === 'validated' && blockers.length > 0;
                    return (
                        <DropdownMenuItem
                            key={decision}
                            disabled={blocked}
                            className={cn(
                                'cursor-pointer items-start',
                                meta.tone,
                            )}
                            onSelect={() => onChoose(decision)}
                        >
                            <meta.icon
                                className={cn('mt-0.5 size-4', meta.tone)}
                                aria-hidden="true"
                            />
                            <span className="min-w-0">
                                {meta.label}
                                {blocked && (
                                    <span className="mt-0.5 block text-xs leading-snug text-muted-foreground">
                                        {blockers.join(' ')}
                                    </span>
                                )}
                            </span>
                        </DropdownMenuItem>
                    );
                })}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

ApplicationsIndex.layout = {
    breadcrumbs: [{ title: 'Dossiers', href: '/scholarships/applications' }],
};
