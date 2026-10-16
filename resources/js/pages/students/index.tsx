import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    BookMarked,
    CircleCheck,
    CircleOff,
    HandCoins,
    Power,
    PowerOff,
    Trash2,
    X,
    Building2,
    ChevronRight,
    Download,
    Eye,
    LayoutGrid,
    Layers,
    List,
    RotateCcw,
    Route,
    Search,
    UserPlus,
    Users,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useEffect, useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import { EmptyState } from '@/components/empty-state';
import { IconAction } from '@/components/icon-action';
import { PageBody, PageHeader } from '@/components/page-header';
import { Pagination } from '@/components/scholarships/shared';
import type { PageLink } from '@/components/scholarships/shared';
import { ToneBadge, toneFor } from '@/components/tone-badge';
import { TrashButton } from '@/components/trash/trash-button';
import InputError from '@/components/input-error';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { REPEAT_CODE_LABELS, SEX_LABELS } from '@/components/students/types';
import type { AnnualRecord, Options } from '@/components/students/types';

type Row = AnnualRecord & {
    program?: {
        id: number;
        code?: string | null;
        name: string;
        mention?: { name: string } | null;
    } | null;
};
type MentionOption = {
    id: number;
    establishment_id: number;
    code: string | null;
    name: string;
};
type Choice = { value: string; label: string };
type FilterKey =
    | 'search'
    | 'academic_year_id'
    | 'establishment_id'
    | 'level_id'
    | 'mention_id'
    | 'program_id'
    | 'status';
type Filters = Partial<Record<FilterKey | 'view', string>>;
type Step = 'establishments' | 'levels' | 'mentions' | 'programs' | 'list';
type Card = {
    id: number | string;
    label: string;
    code: string | null;
    active: number;
    inactive: number;
};
type Params = Record<string, string | number>;
type Stats = {
    total: number;
    active: number;
    inactive: number;
    requests: number;
    incomplete: number;
    establishments: number;
};
type BulkAction = 'deactivate' | 'activate' | 'trash';

const bulkLabels: Record<
    BulkAction,
    { title: string; description: string; confirm: string }
> = {
    deactivate: {
        title: 'Désactiver les situations sélectionnées',
        description:
            'La situation de l’année de chaque étudiant sélectionné passera à « Inactive ». Rien n’est supprimé.',
        confirm: 'Désactiver',
    },
    activate: {
        title: 'Réactiver les situations sélectionnées',
        description:
            'La situation de l’année de chaque étudiant sélectionné redevient « Active » (si l’étudiant et ses référentiels sont actifs).',
        confirm: 'Réactiver',
    },
    trash: {
        title: 'Mettre les étudiants sélectionnés à la corbeille',
        description:
            'Chaque étudiant sélectionné et ses situations vont dans la corbeille ; ils restent restaurables depuis la corbeille.',
        confirm: 'Mettre à la corbeille',
    },
};

/** Reads the XSRF cookie Laravel sets, for the export of the selection (a file, not an Inertia visit). */
const xsrfToken = () =>
    decodeURIComponent(
        document.cookie
            .split('; ')
            .find((item) => item.startsWith('XSRF-TOKEN='))
            ?.split('=')[1] ?? '',
    );

/** Counter tile above the list; clickable when it filters the list. */
function StatTile({
    icon: Icon,
    label,
    value,
    tone,
    href,
    active = false,
    hint,
}: {
    icon: LucideIcon;
    label: string;
    value: number;
    tone: string;
    href?: string;
    active?: boolean;
    hint?: string;
}) {
    const body = (
        <>
            <span
                className={`flex size-9 shrink-0 items-center justify-center rounded-lg ${tone}`}
                aria-hidden="true"
            >
                <Icon className="size-4" />
            </span>
            <span className="min-w-0">
                <span className="block font-heading text-xl leading-tight font-extrabold text-foreground tabular-nums">
                    {number(value)}
                </span>
                <span className="block truncate text-xs text-muted-foreground">
                    {label}
                </span>
            </span>
        </>
    );
    const className = `surface flex items-center gap-3 px-4 py-3 ${active ? 'ring-2 ring-primary/60' : ''}`;
    return href ? (
        <Link
            href={href}
            title={hint}
            aria-current={active ? 'true' : undefined}
            className={`${className} transition-colors hover:border-primary/40 hover:bg-accent/40 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none`}
        >
            {body}
        </Link>
    ) : (
        <div className={className} title={hint}>
            {body}
        </div>
    );
}

/** Card steps: what the cards are, and which parameter a card sets. */
const steps: Record<
    Exclude<Step, 'list'>,
    { title: string; hint: string; icon: LucideIcon; param: string }
> = {
    establishments: {
        title: 'Établissements',
        hint: 'Choisissez un établissement pour voir ses niveaux.',
        icon: Building2,
        param: 'establishment_id',
    },
    levels: {
        title: 'Niveaux',
        hint: 'Choisissez un niveau pour voir ses mentions.',
        icon: Layers,
        param: 'level_id',
    },
    mentions: {
        title: 'Mentions',
        hint: 'Choisissez une mention pour voir ses parcours.',
        icon: BookMarked,
        param: 'mention_id',
    },
    programs: {
        title: 'Parcours',
        hint: 'Choisissez un parcours pour voir la liste de ses étudiants.',
        icon: Route,
        param: 'program_id',
    },
};

const LIST_URL = '/students';
const ALL = 'all';
const NONE = 'none';

const number = (value: number) => new Intl.NumberFormat('fr-FR').format(value);
/** "2001-02-03…" → "03/02/2001", without a time zone shift. */
const formatBirthDate = (value: string | null | undefined) =>
    value ? value.slice(0, 10).split('-').reverse().join('/') : null;
const url = (params: Params) => {
    const query = new URLSearchParams(
        Object.entries(params).map(([key, value]) => [key, String(value)]),
    ).toString();
    return query ? `${LIST_URL}?${query}` : LIST_URL;
};

/** One card step: compact row card, counts on the right, chevron to enter. */
function BrowserCard({
    card,
    icon: Icon,
    href,
}: {
    card: Card;
    icon: LucideIcon;
    href: string;
}) {
    const empty = card.id === NONE;
    return (
        <Link
            href={href}
            className="surface group flex items-center gap-3 px-4 py-3 transition-colors hover:border-primary/40 hover:bg-accent/40 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
        >
            <span
                className={
                    empty
                        ? 'flex size-10 shrink-0 items-center justify-center rounded-lg bg-muted text-muted-foreground'
                        : 'flex size-10 shrink-0 items-center justify-center rounded-lg bg-accent text-primary transition-colors group-hover:bg-primary group-hover:text-primary-foreground'
                }
                aria-hidden="true"
            >
                <Icon className="size-[18px]" />
            </span>
            <span className="min-w-0 flex-1">
                <span
                    className={
                        empty
                            ? 'block truncate text-sm font-semibold text-muted-foreground'
                            : 'block truncate text-sm font-semibold text-foreground'
                    }
                    title={card.label}
                >
                    {card.label}
                </span>
                {card.code && (
                    <span className="mt-0.5 inline-flex rounded bg-secondary px-1.5 font-mono text-[11px] font-semibold tracking-wide text-muted-foreground uppercase">
                        {card.code}
                    </span>
                )}
            </span>
            <span className="shrink-0 text-right">
                <span className="block font-heading text-lg leading-tight font-extrabold text-foreground tabular-nums">
                    {number(card.active)}
                </span>
                <span className="block text-[11px] text-muted-foreground">
                    {card.active > 1 ? 'actifs' : 'actif'}
                    {card.inactive > 0 &&
                        ` · ${number(card.inactive)} inactif${card.inactive > 1 ? 's' : ''}`}
                </span>
            </span>
            <ChevronRight
                className="size-4 shrink-0 text-muted-foreground transition-transform group-hover:translate-x-0.5 group-hover:text-primary"
                aria-hidden="true"
            />
        </Link>
    );
}

/** Query string without empty values. */
const clean = (filters: Filters) =>
    Object.fromEntries(
        Object.entries(filters).filter(
            (entry): entry is [string, string] =>
                entry[1] !== undefined && entry[1] !== '',
        ),
    );

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

/** Select of the filter bar: « all » stands for an empty filter. */
function FilterSelect({
    id,
    label,
    value,
    allLabel,
    choices,
    onChange,
}: {
    id: string;
    label: string;
    value: string | undefined;
    allLabel: string;
    choices: Choice[];
    onChange: (value: string | undefined) => void;
}) {
    return (
        <Field id={id} label={label}>
            <Select
                value={value || ALL}
                onValueChange={(item) =>
                    onChange(item === ALL ? undefined : item)
                }
            >
                <SelectTrigger id={id} className="w-full bg-background">
                    <SelectValue placeholder={allLabel} />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value={ALL}>{allLabel}</SelectItem>
                    {choices.map((choice) => (
                        <SelectItem key={choice.value} value={choice.value}>
                            {choice.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </Field>
    );
}

export default function StudentsIndex({
    step,
    cards,
    stats,
    trail,
    year,
    records,
    filters,
    options,
    mentions,
    canCreate,
    canExport,
}: {
    step: Step;
    cards: Card[];
    stats: Stats;
    trail: { label: string; params: Params }[];
    year: { id: number; name: string; is_current: boolean } | null;
    records: { data: Row[]; total: number; links: PageLink[] } | null;
    filters: Filters;
    options: Options;
    mentions: MentionOption[];
    canCreate: boolean;
    canExport: boolean;
}) {
    const { auth } = usePage().props;
    const [search, setSearch] = useState(filters.search ?? '');
    // Selection of the rows of the current page (by situation uuid), cleared when the page changes.
    const [selected, setSelected] = useState<string[]>([]);
    const [bulk, setBulk] = useState<BulkAction | null>(null);
    const [busy, setBusy] = useState(false);
    const [exporting, setExporting] = useState(false);
    const pageKey = records?.data.map((row) => row.uuid).join(',') ?? '';
    useEffect(() => setSelected([]), [pageKey]);
    const toggle = (uuid: string, on: boolean) =>
        setSelected((current) =>
            on
                ? [...new Set([...current, uuid])]
                : current.filter((item) => item !== uuid),
        );
    const pageUuids = records?.data.map((row) => row.uuid) ?? [];
    const allSelected =
        pageUuids.length > 0 &&
        pageUuids.every((uuid) => selected.includes(uuid));
    const runBulk = () => {
        if (!bulk) return;
        setBusy(true);
        router.post(
            '/students/bulk',
            { action: bulk, records: selected },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setSelected([]);
                    setBulk(null);
                },
                onFinish: () => setBusy(false),
            },
        );
    };
    // The export of a selection is a file: posted with fetch, then saved by the browser.
    const exportSelection = async () => {
        if (!year) return;
        setExporting(true);
        try {
            const response = await fetch('/students/export', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'X-XSRF-TOKEN': xsrfToken(),
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    academic_year_id: year.id,
                    records: selected,
                }),
            });
            if (!response.ok) throw new Error();
            const blob = await response.blob();
            const name =
                /filename="?([^";]+)"?/.exec(
                    response.headers.get('Content-Disposition') ?? '',
                )?.[1] ?? 'etudiants-selection.xlsx';
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = name;
            link.click();
            URL.revokeObjectURL(link.href);
        } catch {
            setError('L’export de la sélection a échoué. Réessayez.');
        } finally {
            setExporting(false);
        }
    };
    const [error, setError] = useState<string | undefined>();
    const fixed = options.fixedEstablishmentId;
    const establishment = fixed ? String(fixed) : filters.establishment_id;
    const codes = new Map(
        options.establishments.map((item) => [item.id, item.code ?? '']),
    );
    const withCode = (name: string, establishmentId?: number) =>
        establishment || establishmentId === undefined
            ? name
            : `${name} (${codes.get(establishmentId) ?? '?'})`;

    // Filters and searches always lead to the whole list ("Tous les étudiants").
    const apply = (changes: Filters) =>
        router.get(LIST_URL, clean({ ...filters, ...changes, view: 'list' }), {
            preserveState: true,
            preserveScroll: true,
            onError: (errors) => setError(Object.values(errors)[0]),
            onSuccess: () => setError(undefined),
        });
    const submit = (event: FormEvent) => {
        event.preventDefault();
        apply({ search: search.trim() || undefined });
    };

    const yearChoices: Choice[] = options.academicYears.map((item) => ({
        value: String(item.id),
        label: `${item.name}${item.is_current ? ' (courante)' : ''}`,
    }));
    const mentionChoices: Choice[] = [
        ...mentions
            .filter(
                (item) =>
                    !establishment ||
                    String(item.establishment_id) === establishment,
            )
            .map((item) => ({
                value: String(item.id),
                label: withCode(item.name, item.establishment_id),
            })),
        { value: NONE, label: 'Sans mention' },
    ];
    const programChoices: Choice[] = [
        ...options.programs
            .filter(
                (item) =>
                    (!establishment ||
                        String(item.establishment_id) === establishment) &&
                    (!filters.mention_id ||
                        (filters.mention_id === NONE
                            ? !item.mention
                            : String(item.mention?.id) === filters.mention_id)),
            )
            .map((item) => ({
                value: String(item.id),
                label: withCode(item.name, item.establishment_id),
            })),
        { value: NONE, label: 'Sans parcours' },
    ];

    // Any filter but the year (the year is a scope, not a narrowing of the list).
    const filtered = Boolean(
        filters.search ||
        filters.level_id ||
        filters.mention_id ||
        filters.program_id ||
        filters.status ||
        (!fixed && filters.establishment_id),
    );
    const resettable = filtered || Boolean(filters.academic_year_id);
    const exportUrl = `/students/export?${new URLSearchParams(
        clean({
            ...filters,
            ...(year ? { academic_year_id: String(year.id) } : {}),
        }),
    ).toString()}`;
    const listMode = step === 'list';
    // Counters link to the list of the current selection, by status.
    const statusHref = (status?: string) =>
        url(
            clean({
                ...(listMode ? filters : (scope as Filters)),
                view: 'list',
                status,
            }) as Params,
        );
    const total = records?.total ?? 0;
    // Scope of the current card step: the list view of « this selection ».
    const scope: Params =
        trail.at(-1)?.params ?? (year ? { academic_year_id: year.id } : {});
    const definition = listMode ? null : steps[step];
    const tiles = (
        <div className="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-5">
            <StatTile
                icon={Users}
                label={
                    stats.establishments > 1
                        ? `Étudiants · ${number(stats.establishments)} établissements`
                        : 'Étudiants'
                }
                value={stats.total}
                tone="bg-accent text-primary"
                href={statusHref()}
                active={listMode && !filters.status}
            />
            <StatTile
                icon={CircleCheck}
                label="Actifs"
                value={stats.active}
                tone="bg-success-soft text-success-foreground"
                href={statusHref('active')}
                active={filters.status === 'active'}
            />
            <StatTile
                icon={CircleOff}
                label="Inactifs"
                value={stats.inactive}
                tone="bg-muted text-muted-foreground"
                href={statusHref('inactive')}
                active={filters.status === 'inactive'}
            />
            <StatTile
                icon={HandCoins}
                label="Demandes de bourse"
                value={stats.requests}
                tone="bg-info-soft text-info-foreground"
                hint="Situations avec une référence de demande de bourse"
            />
            <StatTile
                icon={AlertTriangle}
                label="À compléter"
                value={stats.incomplete}
                tone="bg-warning-soft text-warning-foreground"
                hint="Situations sans code de redoublement"
            />
        </div>
    );
    const unit = year
        ? total > 1
            ? 'étudiants'
            : 'étudiant'
        : total > 1
          ? 'situations'
          : 'situation';

    return (
        <>
            <Head title="Étudiants déclarés" />
            <PageBody>
                <PageHeader
                    icon={Users}
                    title="Étudiants déclarés"
                    description="Tous les étudiants inscrits dans votre périmètre, avec leur établissement et leur situation de l’année."
                    meta={
                        <ToneBadge
                            tone={year?.is_current ? 'primary' : 'neutral'}
                        >
                            {listMode
                                ? `${number(total)} ${unit} · `
                                : 'Année '}
                            {year
                                ? `${year.name}${year.is_current ? '' : ' (non courante)'}`
                                : 'toutes les années'}
                        </ToneBadge>
                    }
                    actions={
                        <div className="flex flex-wrap gap-2">
                            {canExport &&
                                (year ? (
                                    <Button variant="outline" asChild>
                                        <a
                                            href={exportUrl}
                                            title="Fichier Excel au format du modèle établissement, réimportable tel quel (situations actives sauf statut choisi)"
                                        >
                                            <Download aria-hidden="true" />
                                            Exporter (modèle Excel)
                                        </a>
                                    </Button>
                                ) : (
                                    <Tooltip>
                                        <TooltipTrigger asChild>
                                            <span tabIndex={0}>
                                                <Button
                                                    variant="outline"
                                                    disabled
                                                >
                                                    <Download aria-hidden="true" />
                                                    Exporter (modèle Excel)
                                                </Button>
                                            </span>
                                        </TooltipTrigger>
                                        <TooltipContent>
                                            Choisissez une année universitaire
                                            pour exporter.
                                        </TooltipContent>
                                    </Tooltip>
                                ))}
                            {canCreate && (
                                <Button asChild>
                                    <Link href="/students/create">
                                        <UserPlus aria-hidden="true" />
                                        Ajouter un étudiant
                                    </Link>
                                </Button>
                            )}
                        </div>
                    }
                />

                <nav
                    aria-label="Affichage des étudiants"
                    className="inline-flex w-fit rounded-lg border bg-muted/40 p-1"
                >
                    {(
                        [
                            {
                                key: 'cards',
                                label: 'Par établissement',
                                icon: LayoutGrid,
                                href: url(
                                    year ? { academic_year_id: year.id } : {},
                                ),
                                active: !listMode,
                            },
                            {
                                key: 'list',
                                label: 'Tous les étudiants',
                                icon: List,
                                href: url({ ...scope, view: 'list' }),
                                active: listMode,
                            },
                        ] as const
                    ).map((item) => (
                        <Link
                            key={item.key}
                            href={item.href}
                            aria-current={item.active ? 'page' : undefined}
                            className={
                                item.active
                                    ? 'inline-flex items-center gap-2 rounded-md bg-card px-3 py-1.5 text-sm font-semibold text-primary shadow-card'
                                    : 'inline-flex items-center gap-2 rounded-md px-3 py-1.5 text-sm font-medium text-muted-foreground transition-colors hover:text-foreground'
                            }
                        >
                            <item.icon className="size-4" aria-hidden="true" />
                            {item.label}
                        </Link>
                    ))}
                </nav>

                {tiles}

                {!listMode && definition && (
                    <>
                        <form
                            onSubmit={submit}
                            role="search"
                            aria-label="Rechercher un étudiant"
                            className="surface flex flex-col gap-3 p-3 sm:flex-row sm:items-center sm:p-4"
                        >
                            <div className="relative min-w-0 flex-1">
                                <Search
                                    className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                    aria-hidden="true"
                                />
                                <Input
                                    type="search"
                                    aria-label="Recherche"
                                    className="bg-background pl-9"
                                    placeholder="Rechercher un étudiant : nom, prénoms, CIN, téléphone, email"
                                    value={search}
                                    onChange={(event) =>
                                        setSearch(event.target.value)
                                    }
                                    maxLength={100}
                                />
                            </div>
                            <Select
                                value={year ? String(year.id) : undefined}
                                onValueChange={(value) =>
                                    router.get(
                                        LIST_URL,
                                        { academic_year_id: value },
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                <SelectTrigger
                                    className="w-full bg-background sm:w-56"
                                    aria-label="Année universitaire"
                                >
                                    <SelectValue placeholder="Année" />
                                </SelectTrigger>
                                <SelectContent>
                                    {yearChoices.map((choice) => (
                                        <SelectItem
                                            key={choice.value}
                                            value={choice.value}
                                        >
                                            {choice.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <Button type="submit">
                                <Search aria-hidden="true" />
                                Rechercher
                            </Button>
                        </form>

                        {trail.length > 1 && (
                            <nav
                                aria-label="Fil de navigation"
                                className="flex flex-wrap items-center gap-1 text-sm"
                            >
                                {trail.map((item, index) => (
                                    <span
                                        key={index}
                                        className="inline-flex items-center gap-1"
                                    >
                                        {index > 0 && (
                                            <ChevronRight
                                                className="size-4 text-muted-foreground"
                                                aria-hidden="true"
                                            />
                                        )}
                                        {index === trail.length - 1 ? (
                                            <span className="font-semibold text-foreground">
                                                {item.label}
                                            </span>
                                        ) : (
                                            <Link
                                                href={url(item.params)}
                                                className="text-muted-foreground underline-offset-4 hover:text-primary hover:underline"
                                            >
                                                {item.label}
                                            </Link>
                                        )}
                                    </span>
                                ))}
                            </nav>
                        )}

                        <section
                            aria-labelledby="step-title"
                            className="space-y-3"
                        >
                            <div className="flex flex-wrap items-end justify-between gap-2">
                                <div>
                                    <h2
                                        id="step-title"
                                        className="flex items-center gap-2 text-base font-bold text-foreground"
                                    >
                                        <definition.icon
                                            className="size-4 text-primary"
                                            aria-hidden="true"
                                        />
                                        {definition.title}
                                        <span className="rounded-full bg-secondary px-2 text-xs font-semibold text-muted-foreground tabular-nums">
                                            {cards.length}
                                        </span>
                                    </h2>
                                    <p className="text-sm text-muted-foreground">
                                        {definition.hint}
                                    </p>
                                </div>
                                <Button variant="outline" size="sm" asChild>
                                    <Link
                                        href={url({ ...scope, view: 'list' })}
                                    >
                                        <List aria-hidden="true" />
                                        Voir la liste
                                        {trail.length > 1
                                            ? ' de cette sélection'
                                            : ' de tous les étudiants'}
                                    </Link>
                                </Button>
                            </div>
                            {cards.length === 0 ? (
                                <div className="surface">
                                    <EmptyState
                                        illustration="students"
                                        title="Aucune situation pour cette sélection."
                                        description="Changez d’année universitaire ou remontez d’un niveau dans la navigation."
                                    />
                                </div>
                            ) : (
                                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                                    {cards.map((card) => (
                                        <BrowserCard
                                            key={String(card.id)}
                                            card={card}
                                            icon={definition.icon}
                                            href={url({
                                                ...scope,
                                                [definition.param]: card.id,
                                            })}
                                        />
                                    ))}
                                </div>
                            )}
                        </section>
                    </>
                )}

                {listMode && records && (
                    <>
                        <form
                            onSubmit={submit}
                            role="search"
                            aria-label="Filtres des étudiants"
                            className="surface space-y-2 p-3 sm:p-4"
                        >
                            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 2xl:grid-cols-[minmax(14rem,1.6fr)_repeat(6,minmax(0,1fr))]">
                                <Field
                                    id="filter-search"
                                    label="Recherche"
                                    className="sm:col-span-2 2xl:col-span-1"
                                >
                                    <div className="flex gap-2">
                                        <div className="relative min-w-0 flex-1">
                                            <Search
                                                className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                                aria-hidden="true"
                                            />
                                            <Input
                                                id="filter-search"
                                                type="search"
                                                className="bg-background pl-9"
                                                placeholder="Nom, prénoms, CIN, téléphone, email"
                                                value={search}
                                                onChange={(event) =>
                                                    setSearch(
                                                        event.target.value,
                                                    )
                                                }
                                                maxLength={100}
                                            />
                                        </div>
                                        <Button
                                            type="submit"
                                            size="icon"
                                            aria-label="Rechercher"
                                            title="Rechercher"
                                        >
                                            <Search aria-hidden="true" />
                                        </Button>
                                    </div>
                                </Field>
                                <FilterSelect
                                    id="filter-year"
                                    label="Année"
                                    value={year ? String(year.id) : ALL}
                                    allLabel="Toutes les années"
                                    choices={yearChoices}
                                    onChange={(value) =>
                                        apply({
                                            academic_year_id: value ?? ALL,
                                        })
                                    }
                                />
                                {!fixed && (
                                    <FilterSelect
                                        id="filter-establishment"
                                        label="Établissement"
                                        value={filters.establishment_id}
                                        allLabel="Tous"
                                        choices={options.establishments.map(
                                            (item) => ({
                                                value: String(item.id),
                                                label: item.code
                                                    ? `${item.code} — ${item.name}`
                                                    : item.name,
                                            }),
                                        )}
                                        onChange={(value) =>
                                            apply({
                                                establishment_id: value,
                                                mention_id: undefined,
                                                program_id: undefined,
                                            })
                                        }
                                    />
                                )}
                                <FilterSelect
                                    id="filter-level"
                                    label="Niveau"
                                    value={filters.level_id}
                                    allLabel="Tous"
                                    choices={options.levels.map((item) => ({
                                        value: String(item.id),
                                        label: item.code
                                            ? `${item.code} — ${item.name}`
                                            : item.name,
                                    }))}
                                    onChange={(value) =>
                                        apply({ level_id: value })
                                    }
                                />
                                <FilterSelect
                                    id="filter-mention"
                                    label={
                                        establishment
                                            ? 'Mention'
                                            : 'Mention (établissement)'
                                    }
                                    value={filters.mention_id}
                                    allLabel="Toutes"
                                    choices={mentionChoices}
                                    onChange={(value) =>
                                        apply({
                                            mention_id: value,
                                            program_id: undefined,
                                        })
                                    }
                                />
                                <FilterSelect
                                    id="filter-program"
                                    label={
                                        establishment
                                            ? 'Parcours'
                                            : 'Parcours (établissement)'
                                    }
                                    value={filters.program_id}
                                    allLabel="Tous"
                                    choices={programChoices}
                                    onChange={(value) =>
                                        apply({ program_id: value })
                                    }
                                />
                                <FilterSelect
                                    id="filter-status"
                                    label="Statut"
                                    value={filters.status}
                                    allLabel="Toutes"
                                    choices={[
                                        { value: 'active', label: 'Actives' },
                                        {
                                            value: 'inactive',
                                            label: 'Inactives',
                                        },
                                    ]}
                                    onChange={(value) =>
                                        apply({ status: value })
                                    }
                                />
                            </div>
                            {(resettable || error) && (
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <InputError message={error} />
                                    {resettable && (
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            className="ml-auto"
                                            asChild
                                        >
                                            <Link href={url({ view: 'list' })}>
                                                <RotateCcw aria-hidden="true" />
                                                Réinitialiser
                                            </Link>
                                        </Button>
                                    )}
                                </div>
                            )}
                        </form>

                        <section
                            className="surface overflow-hidden"
                            aria-label="Liste des étudiants"
                        >
                            {records.data.length === 0 ? (
                                <EmptyState
                                    illustration={
                                        filtered ? 'search' : 'students'
                                    }
                                    title={
                                        filtered
                                            ? 'Aucun étudiant ne correspond à ces filtres.'
                                            : 'Aucun étudiant pour cette année.'
                                    }
                                    description={
                                        filtered
                                            ? 'Vérifiez l’orthographe, élargissez la recherche ou réinitialisez les filtres.'
                                            : 'Les étudiants apparaissent ici dès que leur situation de l’année est enregistrée (saisie ou import).'
                                    }
                                    action={
                                        filtered && (
                                            <Button variant="outline" asChild>
                                                <Link
                                                    href={url({ view: 'list' })}
                                                >
                                                    <RotateCcw aria-hidden="true" />
                                                    Réinitialiser les filtres
                                                </Link>
                                            </Button>
                                        )
                                    }
                                />
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="data-table min-w-[52rem] lg:min-w-0 [&_tbody_td]:px-3 [&_tbody_td]:py-2.5 [&_thead_th]:px-3">
                                        <caption className="sr-only">
                                            Liste des étudiants et de leur
                                            établissement
                                        </caption>
                                        <thead>
                                            <tr>
                                                <th
                                                    scope="col"
                                                    className="w-10"
                                                >
                                                    <Checkbox
                                                        aria-label="Sélectionner tous les étudiants de la page"
                                                        checked={
                                                            allSelected
                                                                ? true
                                                                : selected.length >
                                                                    0
                                                                  ? 'indeterminate'
                                                                  : false
                                                        }
                                                        onCheckedChange={(
                                                            value,
                                                        ) =>
                                                            setSelected(
                                                                value === true
                                                                    ? pageUuids
                                                                    : [],
                                                            )
                                                        }
                                                    />
                                                </th>
                                                <th scope="col">Étudiant</th>
                                                <th scope="col">
                                                    Établissement
                                                </th>
                                                <th scope="col">Niveau</th>
                                                <th scope="col">
                                                    Mention › Parcours
                                                </th>
                                                <th scope="col">Code</th>
                                                <th scope="col">Statut</th>
                                                <th
                                                    scope="col"
                                                    className="text-right"
                                                >
                                                    <span className="sr-only">
                                                        Actions
                                                    </span>
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {records.data.map((record) => (
                                                <StudentRow
                                                    key={record.id}
                                                    record={record}
                                                    selected={selected.includes(
                                                        record.uuid,
                                                    )}
                                                    onToggle={(on) =>
                                                        toggle(record.uuid, on)
                                                    }
                                                />
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                            <div className="flex justify-end border-t px-4 py-3 empty:hidden sm:px-5">
                                <Pagination links={records.links} />
                            </div>
                        </section>
                        {selected.length > 0 && (
                            <div className="sticky bottom-0 z-20 pb-3 md:pb-4">
                                <div
                                    role="toolbar"
                                    aria-label="Actions sur la sélection"
                                    className="flex flex-col gap-3 rounded-xl border bg-card/90 px-4 py-3 shadow-elevated backdrop-blur-md sm:flex-row sm:items-center sm:justify-between"
                                >
                                    <p className="text-sm font-semibold text-foreground">
                                        {number(selected.length)} étudiant
                                        {selected.length > 1 ? 's' : ''}{' '}
                                        sélectionné
                                        {selected.length > 1 ? 's' : ''}
                                        <span className="font-normal text-muted-foreground">
                                            {' '}
                                            sur cette page
                                        </span>
                                    </p>
                                    <div className="flex flex-wrap gap-2">
                                        {canExport && year && (
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={exportSelection}
                                                disabled={exporting}
                                            >
                                                {exporting ? (
                                                    <Spinner />
                                                ) : (
                                                    <Download aria-hidden="true" />
                                                )}
                                                Exporter la sélection
                                            </Button>
                                        )}
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() =>
                                                setBulk('deactivate')
                                            }
                                        >
                                            <PowerOff aria-hidden="true" />
                                            Désactiver
                                        </Button>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            className="text-success hover:text-success-foreground"
                                            onClick={() => setBulk('activate')}
                                        >
                                            <Power aria-hidden="true" />
                                            Réactiver
                                        </Button>
                                        {auth.canManageTrash && (
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                className="border-danger/30 text-danger hover:bg-danger-soft hover:text-danger-foreground"
                                                onClick={() => setBulk('trash')}
                                            >
                                                <Trash2 aria-hidden="true" />
                                                Supprimer
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
                            open={bulk !== null}
                            onOpenChange={(open) => {
                                if (!open && !busy) setBulk(null);
                            }}
                        >
                            <DialogContent>
                                <DialogHeader>
                                    <DialogTitle>
                                        {bulk ? bulkLabels[bulk].title : ''}
                                    </DialogTitle>
                                    <DialogDescription>
                                        {bulk
                                            ? bulkLabels[bulk].description
                                            : ''}{' '}
                                        {number(selected.length)} étudiant
                                        {selected.length > 1 ? 's' : ''}{' '}
                                        concerné{selected.length > 1 ? 's' : ''}
                                        . Chaque ligne est contrôlée séparément
                                        : celles qui ne sont pas autorisées sont
                                        ignorées et signalées.
                                    </DialogDescription>
                                </DialogHeader>
                                <DialogFooter>
                                    <Button
                                        variant="outline"
                                        disabled={busy}
                                        onClick={() => setBulk(null)}
                                    >
                                        Annuler
                                    </Button>
                                    <Button
                                        variant={
                                            bulk === 'trash'
                                                ? 'destructive'
                                                : 'default'
                                        }
                                        disabled={busy}
                                        onClick={runBulk}
                                    >
                                        {busy && <Spinner />}
                                        {bulk ? bulkLabels[bulk].confirm : ''}
                                    </Button>
                                </DialogFooter>
                            </DialogContent>
                        </Dialog>
                    </>
                )}
            </PageBody>
        </>
    );
}

function StudentRow({
    record,
    selected,
    onToggle,
}: {
    record: Row;
    selected: boolean;
    onToggle: (selected: boolean) => void;
}) {
    const student = record.student;
    const name = [student?.last_name, student?.first_names]
        .filter(Boolean)
        .join(' ');
    const details = [
        student?.sex ? SEX_LABELS[student.sex] : null,
        formatBirthDate(student?.birth_date),
    ].filter(Boolean);
    const mention = record.program
        ? (record.program.mention?.name ?? 'Sans mention')
        : null;
    const path = record.program
        ? `${mention} › ${record.program.name}`
        : 'Sans parcours';
    return (
        <tr className={selected ? 'bg-accent/40' : undefined}>
            <td className="w-10">
                <Checkbox
                    aria-label={`Sélectionner ${name}`}
                    checked={selected}
                    onCheckedChange={(value) => onToggle(value === true)}
                />
            </td>
            <td className="max-w-[18rem]">
                <p className="truncate text-foreground" title={name}>
                    <span className="font-semibold">{student?.last_name}</span>{' '}
                    {student?.first_names}
                </p>
                <div className="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-1">
                    {details.length > 0 && (
                        <span className="text-xs text-muted-foreground">
                            {details.join(' · ')}
                        </span>
                    )}
                    {student?.is_active === false && (
                        <ToneBadge tone="danger">Étudiant désactivé</ToneBadge>
                    )}
                </div>
            </td>
            <td>
                <span
                    className="font-medium whitespace-nowrap"
                    title={record.establishment?.name}
                >
                    {record.establishment?.code ?? record.establishment?.name}
                </span>
            </td>
            <td>
                <span
                    className="inline-flex rounded-md bg-secondary px-1.5 py-0.5 text-xs font-semibold whitespace-nowrap text-secondary-foreground"
                    title={record.level?.name}
                >
                    {record.level?.code ?? record.level?.name}
                </span>
            </td>
            <td className="max-w-[20rem]">
                <p
                    className={
                        record.program
                            ? 'truncate'
                            : 'truncate text-muted-foreground'
                    }
                    title={path}
                >
                    {record.program ? (
                        <>
                            <span className="text-muted-foreground">
                                {mention} ›
                            </span>{' '}
                            {record.program.name}
                        </>
                    ) : (
                        path
                    )}
                </p>
            </td>
            <td>
                {record.repeat_code ? (
                    <span
                        className="inline-flex rounded-md border px-1.5 py-0.5 font-mono text-xs font-semibold whitespace-nowrap"
                        title={REPEAT_CODE_LABELS[record.repeat_code]}
                    >
                        {record.repeat_code}
                    </span>
                ) : (
                    <span
                        className="inline-flex items-center gap-1 rounded-md bg-warning-soft px-1.5 py-0.5 text-xs font-semibold text-warning-foreground"
                        title="Code de redoublement manquant"
                    >
                        <AlertTriangle className="size-3" aria-hidden="true" />?
                        <span className="sr-only">
                            Code de redoublement manquant
                        </span>
                    </span>
                )}
            </td>
            <td>
                <ToneBadge tone={toneFor(record.status)}>
                    {record.status === 'active' ? 'Active' : 'Inactive'}
                </ToneBadge>
            </td>
            <td>
                <div className="flex items-center justify-end gap-1">
                    {student && (
                        <>
                            <IconAction
                                icon={Eye}
                                label="Consulter la fiche"
                                href={`/students/${student.uuid}`}
                            />
                            <TrashButton
                                iconOnly
                                kind="students"
                                uuid={student.uuid}
                                label={student.last_name}
                            />
                        </>
                    )}
                </div>
            </td>
        </tr>
    );
}

StudentsIndex.layout = {
    breadcrumbs: [{ title: 'Étudiants', href: '/students' }],
};
