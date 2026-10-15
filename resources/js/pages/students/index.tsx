import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowUpRight,
    BookMarked,
    Building2,
    CalendarRange,
    ChevronRight,
    Eye,
    Layers,
    LayoutGrid,
    List,
    ListFilter,
    Route,
    Search,
    UserPlus,
    Users,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { FormEvent } from 'react';
import { EmptyState } from '@/components/empty-state';
import { PageBody, PageHeader } from '@/components/page-header';
import { Pagination } from '@/components/scholarships/shared';
import { ToneBadge, toneFor } from '@/components/tone-badge';
import { TrashButton } from '@/components/trash/trash-button';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { AnnualRecord, Options } from '@/components/students/types';
import { cn } from '@/lib/utils';

type Step = 'establishments' | 'levels' | 'mentions' | 'programs' | 'list';
type Params = Record<string, string | number>;
type Card = {
    id: number | string;
    label: string;
    code: string | null;
    active: number;
    inactive: number;
};
type Row = AnnualRecord & {
    program?: {
        id: number;
        name: string;
        mention?: { name: string } | null;
    } | null;
};

const steps: Record<
    Exclude<Step, 'list'>,
    { title: string; hint: string; param: string; icon: LucideIcon }
> = {
    establishments: {
        title: 'Établissements',
        hint: 'Choisissez un établissement pour voir ses niveaux.',
        param: 'establishment_id',
        icon: Building2,
    },
    levels: {
        title: 'Niveaux',
        hint: 'Choisissez un niveau pour voir ses mentions.',
        param: 'level_id',
        icon: Layers,
    },
    mentions: {
        title: 'Mentions',
        hint: 'Choisissez une mention pour voir ses parcours.',
        param: 'mention_id',
        icon: BookMarked,
    },
    programs: {
        title: 'Parcours',
        hint: 'Choisissez un parcours pour afficher la liste des étudiants.',
        param: 'program_id',
        icon: Route,
    },
};

/** Icon of a trail entry, from the deepest filter it introduces. */
const trailIcon = (params: Params): LucideIcon =>
    'program_id' in params
        ? Route
        : 'mention_id' in params
          ? BookMarked
          : 'level_id' in params
            ? Layers
            : 'establishment_id' in params
              ? Building2
              : LayoutGrid;

const number = (value: number) => new Intl.NumberFormat('fr-FR').format(value);

const url = (params: Params) => {
    const query = new URLSearchParams(
        Object.entries(params).map(([key, value]) => [key, String(value)]),
    ).toString();
    return query ? `/students?${query}` : '/students';
};
const plural = (count: number, word: string) =>
    `${number(count)} ${word}${count > 1 ? 's' : ''}`;
const initials = (lastName?: string | null, firstNames?: string | null) =>
    [lastName, firstNames]
        .filter(Boolean)
        .map((part) => String(part).trim().charAt(0))
        .join('')
        .slice(0, 2)
        .toUpperCase();

function BrowserCard({
    card,
    icon: Icon,
    href,
}: {
    card: Card;
    icon: LucideIcon;
    href: string;
}) {
    const empty = card.id === 'none';
    const total = card.active + card.inactive;
    const share = total > 0 ? Math.round((card.active / total) * 100) : 0;
    return (
        <Link
            href={href}
            className="surface group relative flex flex-col gap-4 overflow-hidden p-5 transition-all hover:-translate-y-0.5 hover:border-primary/40 hover:shadow-elevated focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none motion-reduce:hover:translate-y-0"
        >
            <span
                className="pointer-events-none absolute -top-10 -right-10 size-32 rounded-full bg-accent opacity-0 blur-2xl transition-opacity group-hover:opacity-100"
                aria-hidden="true"
            />
            <div className="relative flex items-start justify-between gap-3">
                <span
                    className={cn(
                        'flex size-11 shrink-0 items-center justify-center rounded-xl ring-1 transition-colors',
                        empty
                            ? 'bg-muted text-muted-foreground ring-border'
                            : 'bg-accent text-primary ring-primary/10 group-hover:bg-primary group-hover:text-primary-foreground',
                    )}
                    aria-hidden="true"
                >
                    <Icon className="size-5" />
                </span>
                <span
                    className="flex size-8 items-center justify-center rounded-full text-muted-foreground transition-colors group-hover:bg-accent group-hover:text-primary"
                    aria-hidden="true"
                >
                    <ArrowUpRight className="size-4 transition-transform group-hover:translate-x-0.5 group-hover:-translate-y-0.5" />
                </span>
            </div>
            <div className="relative min-w-0">
                <h3
                    className={cn(
                        'line-clamp-2 text-base leading-snug font-bold',
                        empty ? 'text-muted-foreground' : 'text-foreground',
                    )}
                    title={card.label}
                >
                    {card.label}
                </h3>
                {card.code && (
                    <p className="mt-1 inline-flex rounded-md bg-secondary px-1.5 py-0.5 font-mono text-[11px] font-semibold tracking-wide text-muted-foreground uppercase">
                        {card.code}
                    </p>
                )}
            </div>
            <div className="relative mt-auto space-y-3 border-t pt-4">
                <div className="flex items-end justify-between gap-3">
                    <div>
                        <p className="font-heading text-2xl leading-none font-extrabold text-foreground tabular-nums">
                            {number(card.active)}
                        </p>
                        <p className="mt-1 text-xs text-muted-foreground">
                            {card.active > 1
                                ? 'étudiants actifs'
                                : 'étudiant actif'}
                        </p>
                    </div>
                    {card.inactive > 0 && (
                        <ToneBadge tone="neutral">
                            {card.inactive} inactive
                            {card.inactive > 1 ? 's' : ''}
                        </ToneBadge>
                    )}
                </div>
                {card.inactive > 0 && (
                    <div
                        className="h-1.5 overflow-hidden rounded-full bg-muted"
                        aria-hidden="true"
                    >
                        <div
                            className="h-full rounded-full bg-primary"
                            style={{ width: `${share}%` }}
                        />
                    </div>
                )}
            </div>
        </Link>
    );
}

export default function StudentsIndex({
    step,
    year,
    cards,
    trail,
    records,
    filters,
    options,
    canCreate,
}: {
    step: Step;
    year: { id: number; name: string; is_current: boolean } | null;
    cards: Card[];
    trail: { label: string; params: Params }[];
    records: {
        data: Row[];
        total: number;
        links: { url: string | null; label: string; active: boolean }[];
    } | null;
    filters: Record<string, string>;
    options: Options;
    canCreate: boolean;
}) {
    const current: Params =
        trail.at(-1)?.params ?? (year ? { academic_year_id: year.id } : {});
    const form = useForm<Record<string, string>>({
        search: filters.search ?? '',
        status: filters.status ?? '',
    });
    const search = (event: FormEvent) => {
        event.preventDefault();
        router.get(
            '/students',
            {
                ...current,
                ...(form.data.search ? { search: form.data.search } : {}),
                ...(form.data.status ? { status: form.data.status } : {}),
            },
            { preserveState: true },
        );
    };
    const definition = step === 'list' ? null : steps[step];
    const filtered = Boolean(filters.search || filters.status);
    return (
        <>
            <Head title="Étudiants déclarés" />
            <PageBody>
                <PageHeader
                    icon={Users}
                    title="Étudiants déclarés"
                    description="Parcourez les étudiants déjà régulièrement inscrits : établissement, niveau, mention, parcours, puis liste."
                    meta={
                        year && (
                            <ToneBadge
                                tone={year.is_current ? 'primary' : 'neutral'}
                            >
                                Année {year.name}
                                {!year.is_current && ' (non courante)'}
                            </ToneBadge>
                        )
                    }
                    actions={
                        canCreate && (
                            <Button asChild>
                                <Link href="/students/create">
                                    <UserPlus aria-hidden="true" />
                                    Ajouter un étudiant
                                </Link>
                            </Button>
                        )
                    }
                />

                <div className="space-y-2">
                    <form
                        onSubmit={search}
                        role="search"
                        className="surface flex flex-col gap-3 p-3 sm:p-4 lg:flex-row lg:items-center"
                    >
                        <div className="relative flex-1">
                            <Search
                                className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                aria-hidden="true"
                            />
                            <Input
                                aria-label="Recherche étudiant"
                                className="h-10 bg-background pl-9"
                                placeholder="Matricule, nom, prénom, CIN, numéro BAC, téléphone ou email"
                                value={form.data.search}
                                onChange={(event) =>
                                    form.setData('search', event.target.value)
                                }
                                maxLength={100}
                            />
                        </div>
                        <div className="flex flex-col gap-3 sm:flex-row">
                            <Select
                                value={year ? String(year.id) : undefined}
                                onValueChange={(value) =>
                                    router.get(url({ academic_year_id: value }))
                                }
                            >
                                <SelectTrigger
                                    className="h-10 w-full bg-background sm:flex-1 lg:w-56 lg:flex-none"
                                    aria-label="Année universitaire"
                                >
                                    <CalendarRange
                                        className="size-4 text-muted-foreground"
                                        aria-hidden="true"
                                    />
                                    <SelectValue placeholder="Année universitaire" />
                                </SelectTrigger>
                                <SelectContent>
                                    {options.academicYears.map((item) => (
                                        <SelectItem
                                            key={item.id}
                                            value={String(item.id)}
                                        >
                                            {item.name}
                                            {item.is_current
                                                ? ' (courante)'
                                                : ''}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <Button type="submit" className="h-10">
                                <Search aria-hidden="true" />
                                Rechercher
                            </Button>
                        </div>
                    </form>
                    <InputError message={Object.values(form.errors)[0]} />
                </div>

                {/* The first step needs no trail: its only entry would repeat the step title. */}
                {!(step === 'establishments' && trail.length <= 1) && (
                    <nav aria-label="Navigation des étudiants">
                        <ol className="flex flex-wrap items-center gap-1.5 text-sm">
                            {trail.map((item, index) => {
                                const Icon = trailIcon(item.params);
                                const last =
                                    index === trail.length - 1 &&
                                    step !== 'list';
                                return (
                                    <li
                                        key={index}
                                        className="flex min-w-0 items-center gap-1.5"
                                    >
                                        {index > 0 && (
                                            <ChevronRight
                                                className="size-4 shrink-0 text-muted-foreground/70"
                                                aria-hidden="true"
                                            />
                                        )}
                                        {last ? (
                                            <span
                                                className="inline-flex max-w-[16rem] items-center gap-1.5 rounded-full bg-primary px-3 py-1 font-semibold text-primary-foreground shadow-sm"
                                                aria-current="page"
                                            >
                                                <Icon
                                                    className="size-3.5 shrink-0"
                                                    aria-hidden="true"
                                                />
                                                <span className="truncate">
                                                    {item.label}
                                                </span>
                                            </span>
                                        ) : (
                                            <Link
                                                href={url(item.params)}
                                                className="inline-flex max-w-[16rem] items-center gap-1.5 rounded-full border bg-card px-3 py-1 font-medium text-foreground transition-colors hover:border-primary/40 hover:bg-accent hover:text-primary focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                            >
                                                <Icon
                                                    className="size-3.5 shrink-0 text-muted-foreground"
                                                    aria-hidden="true"
                                                />
                                                <span className="truncate">
                                                    {item.label}
                                                </span>
                                            </Link>
                                        )}
                                    </li>
                                );
                            })}
                            {step === 'list' && (
                                <li className="flex min-w-0 items-center gap-1.5">
                                    {trail.length > 0 && (
                                        <ChevronRight
                                            className="size-4 shrink-0 text-muted-foreground/70"
                                            aria-hidden="true"
                                        />
                                    )}
                                    <span
                                        className="inline-flex max-w-[20rem] items-center gap-1.5 rounded-full bg-primary px-3 py-1 font-semibold text-primary-foreground shadow-sm"
                                        aria-current="page"
                                    >
                                        {filters.search ? (
                                            <Search
                                                className="size-3.5 shrink-0"
                                                aria-hidden="true"
                                            />
                                        ) : (
                                            <List
                                                className="size-3.5 shrink-0"
                                                aria-hidden="true"
                                            />
                                        )}
                                        <span className="truncate">
                                            {filters.search
                                                ? `Recherche « ${filters.search} »`
                                                : 'Liste des étudiants'}
                                        </span>
                                    </span>
                                </li>
                            )}
                        </ol>
                    </nav>
                )}

                {!year && (
                    <p
                        role="status"
                        className="flex items-start gap-3 rounded-xl border border-warning/30 bg-warning-soft p-4 text-sm text-warning-foreground"
                    >
                        <AlertTriangle
                            className="mt-0.5 size-4 shrink-0"
                            aria-hidden="true"
                        />
                        Aucune année universitaire courante n’est définie :
                        choisissez une année pour parcourir les étudiants.
                    </p>
                )}

                {definition && (
                    <section className="space-y-4" aria-labelledby="step-title">
                        <div className="flex flex-wrap items-end justify-between gap-3">
                            <div className="flex items-start gap-3">
                                <span
                                    className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-accent text-primary"
                                    aria-hidden="true"
                                >
                                    <definition.icon className="size-[18px]" />
                                </span>
                                <div>
                                    <h2
                                        id="step-title"
                                        className="flex items-center gap-2 text-lg leading-tight font-bold text-foreground"
                                    >
                                        {definition.title}
                                        <span className="rounded-full bg-secondary px-2 py-0.5 text-xs font-semibold text-muted-foreground tabular-nums">
                                            {cards.length}
                                        </span>
                                    </h2>
                                    <p className="text-sm text-muted-foreground">
                                        {definition.hint}
                                    </p>
                                </div>
                            </div>
                            {step !== 'establishments' && (
                                <Button variant="outline" size="sm" asChild>
                                    <Link
                                        href={url({ ...current, view: 'list' })}
                                    >
                                        <List aria-hidden="true" />
                                        Voir toute la sélection en liste
                                    </Link>
                                </Button>
                            )}
                        </div>
                        {cards.length === 0 ? (
                            <div className="surface">
                                <EmptyState
                                    illustration="students"
                                    title="Aucune déclaration pour cette sélection."
                                    description="Changez d’année universitaire ou remontez d’un niveau dans la navigation."
                                />
                            </div>
                        ) : (
                            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                                {cards.map((card) => (
                                    <BrowserCard
                                        key={String(card.id)}
                                        card={card}
                                        icon={definition.icon}
                                        href={url({
                                            ...current,
                                            [definition.param]: card.id,
                                        })}
                                    />
                                ))}
                            </div>
                        )}
                    </section>
                )}

                {step === 'list' && records && (
                    <section
                        className="surface overflow-hidden"
                        aria-labelledby="list-title"
                    >
                        <div className="flex flex-col gap-3 border-b px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                            <div className="flex items-center gap-2">
                                <h2
                                    id="list-title"
                                    className="text-base font-bold text-foreground"
                                >
                                    Liste des étudiants
                                </h2>
                                <span className="rounded-full bg-accent px-2.5 py-0.5 text-xs font-semibold text-accent-foreground tabular-nums">
                                    {plural(records.total, 'déclaration')}
                                </span>
                            </div>
                            <Select
                                value={form.data.status || 'all'}
                                onValueChange={(value) =>
                                    router.get(
                                        url({
                                            ...current,
                                            ...(filters.search
                                                ? { search: filters.search }
                                                : {}),
                                            view: 'list',
                                            ...(value === 'all'
                                                ? {}
                                                : { status: value }),
                                        }),
                                    )
                                }
                            >
                                <SelectTrigger
                                    className="w-full sm:w-56"
                                    aria-label="Statut de la déclaration"
                                >
                                    <ListFilter
                                        className="size-4 text-muted-foreground"
                                        aria-hidden="true"
                                    />
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        Toutes les déclarations
                                    </SelectItem>
                                    <SelectItem value="active">
                                        Déclarations actives
                                    </SelectItem>
                                    <SelectItem value="inactive">
                                        Déclarations inactives
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        {records.data.length === 0 ? (
                            <EmptyState
                                illustration={filtered ? 'search' : 'students'}
                                title="Aucun étudiant ne correspond à cette sélection."
                                description={
                                    filtered
                                        ? 'Vérifiez l’orthographe, élargissez la recherche ou changez le statut.'
                                        : 'Aucune déclaration pour cette sélection et cette année.'
                                }
                            />
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="data-table min-w-[56rem]">
                                    <caption className="sr-only">
                                        Liste des étudiants déclarés
                                    </caption>
                                    <thead>
                                        <tr>
                                            {[
                                                'Matricule',
                                                'Nom et prénoms',
                                                'Établissement',
                                                'Niveau',
                                                'Mention',
                                                'Parcours',
                                                'Statut',
                                            ].map((label) => (
                                                <th key={label} scope="col">
                                                    {label}
                                                </th>
                                            ))}
                                            <th
                                                scope="col"
                                                className="text-right"
                                            >
                                                Actions
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {records.data.map((record) => (
                                            <tr key={record.id}>
                                                <td>
                                                    <span className="font-mono text-xs font-semibold text-foreground">
                                                        {record.student
                                                            ?.student_number ??
                                                            '—'}
                                                    </span>
                                                </td>
                                                <td>
                                                    <div className="flex items-center gap-3">
                                                        <span
                                                            className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-accent font-heading text-xs font-bold text-primary"
                                                            aria-hidden="true"
                                                        >
                                                            {initials(
                                                                record.student
                                                                    ?.last_name,
                                                                record.student
                                                                    ?.first_names,
                                                            ) || '?'}
                                                        </span>
                                                        <div className="min-w-0">
                                                            <p className="font-semibold text-foreground">
                                                                {
                                                                    record
                                                                        .student
                                                                        ?.last_name
                                                                }{' '}
                                                                <span className="font-normal">
                                                                    {
                                                                        record
                                                                            .student
                                                                            ?.first_names
                                                                    }
                                                                </span>
                                                            </p>
                                                            {record.student
                                                                ?.is_active ===
                                                                false && (
                                                                <p className="text-xs text-muted-foreground">
                                                                    Identité
                                                                    inactive
                                                                </p>
                                                            )}
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span
                                                        className="font-medium"
                                                        title={
                                                            record.establishment
                                                                ?.name
                                                        }
                                                    >
                                                        {
                                                            record.establishment
                                                                ?.code
                                                        }
                                                    </span>
                                                </td>
                                                <td>
                                                    <span className="inline-flex rounded-md bg-secondary px-1.5 py-0.5 text-xs font-semibold text-secondary-foreground">
                                                        {record.level?.code}
                                                    </span>
                                                </td>
                                                <td className="text-muted-foreground">
                                                    {record.program?.mention
                                                        ?.name ??
                                                        'Sans mention'}
                                                </td>
                                                <td>
                                                    {record.program?.name ??
                                                        'Sans parcours'}
                                                </td>
                                                <td>
                                                    <ToneBadge
                                                        tone={toneFor(
                                                            record.status,
                                                        )}
                                                    >
                                                        {record.status ===
                                                        'active'
                                                            ? 'Active'
                                                            : 'Inactive'}
                                                    </ToneBadge>
                                                </td>
                                                <td>
                                                    <div className="flex items-center justify-end gap-2">
                                                        <Button
                                                            variant="outline"
                                                            size="sm"
                                                            asChild
                                                        >
                                                            <Link
                                                                href={`/students/${record.student?.uuid}`}
                                                            >
                                                                <Eye aria-hidden="true" />
                                                                Consulter
                                                            </Link>
                                                        </Button>
                                                        {record.student && (
                                                            <TrashButton
                                                                kind="students"
                                                                uuid={
                                                                    record
                                                                        .student
                                                                        .uuid
                                                                }
                                                                label={
                                                                    record
                                                                        .student
                                                                        .last_name
                                                                }
                                                            />
                                                        )}
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                        {records.links.length > 3 && (
                            <div className="flex justify-end border-t px-4 py-3 empty:hidden sm:px-5">
                                <Pagination links={records.links} />
                            </div>
                        )}
                    </section>
                )}
            </PageBody>
        </>
    );
}
StudentsIndex.layout = {
    breadcrumbs: [{ title: 'Étudiants', href: '/students' }],
};
