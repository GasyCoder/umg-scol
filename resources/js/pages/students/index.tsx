import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    BookMarked,
    Building2,
    ChevronRight,
    Layers,
    List,
    Route,
    Search,
    Users,
} from 'lucide-react';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
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
    { title: string; hint: string; param: string; icon: typeof Building2 }
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

const url = (params: Params) => {
    const query = new URLSearchParams(
        Object.entries(params).map(([key, value]) => [key, String(value)]),
    ).toString();
    return query ? `/students?${query}` : '/students';
};
const plural = (count: number, word: string) =>
    `${new Intl.NumberFormat('fr-FR').format(count)} ${word}${count > 1 ? 's' : ''}`;

function BrowserCard({
    card,
    icon: Icon,
    href,
}: {
    card: Card;
    icon: typeof Building2;
    href: string;
}) {
    const empty = card.id === 'none';
    return (
        <Link
            href={href}
            className="group flex flex-col gap-4 rounded-lg border bg-card p-5 transition-all hover:border-primary/60 hover:shadow-md focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
        >
            <div className="flex items-start justify-between gap-3">
                <span
                    className={`flex size-11 shrink-0 items-center justify-center rounded-full ${empty ? 'bg-muted text-muted-foreground' : 'bg-accent text-primary'}`}
                    aria-hidden="true"
                >
                    <Icon className="size-5" />
                </span>
                <ChevronRight
                    className="size-5 text-muted-foreground transition-transform group-hover:translate-x-0.5 group-hover:text-primary"
                    aria-hidden="true"
                />
            </div>
            <div className="min-w-0">
                <h3 className="truncate text-base font-bold text-foreground">
                    {card.label}
                </h3>
                {card.code && (
                    <p className="text-xs tracking-wide text-muted-foreground uppercase">
                        {card.code}
                    </p>
                )}
            </div>
            <div className="mt-auto flex items-end justify-between gap-3 border-t pt-4">
                <div>
                    <p className="font-heading text-2xl leading-none font-bold text-foreground tabular-nums">
                        {new Intl.NumberFormat('fr-FR').format(card.active)}
                    </p>
                    <p className="mt-1 text-xs text-muted-foreground">
                        {card.active > 1
                            ? 'étudiants actifs'
                            : 'étudiant actif'}
                    </p>
                </div>
                {card.inactive > 0 && (
                    <Badge variant="outline">
                        {card.inactive} inactive{card.inactive > 1 ? 's' : ''}
                    </Badge>
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
    return (
        <div className="space-y-6 p-4 md:p-6">
            <Head title="Étudiants déclarés" />
            <div className="flex flex-wrap items-start justify-between gap-3">
                <Heading
                    title="Étudiants déclarés"
                    description="Parcourez les étudiants déjà régulièrement inscrits : établissement, niveau, mention, parcours, puis liste."
                />
                {canCreate && (
                    <Button asChild>
                        <Link href="/students/create">Ajouter un étudiant</Link>
                    </Button>
                )}
            </div>

            <form
                onSubmit={search}
                className="flex flex-col gap-3 rounded-lg border bg-card p-4 lg:flex-row lg:items-center"
            >
                <div className="relative flex-1">
                    <Search
                        className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <Input
                        aria-label="Recherche étudiant"
                        className="pl-9"
                        placeholder="Matricule, nom, prénom, CIN, numéro BAC, téléphone ou email"
                        value={form.data.search}
                        onChange={(event) =>
                            form.setData('search', event.target.value)
                        }
                        maxLength={100}
                    />
                </div>
                <Select
                    value={year ? String(year.id) : undefined}
                    onValueChange={(value) =>
                        router.get(url({ academic_year_id: value }))
                    }
                >
                    <SelectTrigger
                        className="w-full lg:w-56"
                        aria-label="Année universitaire"
                    >
                        <SelectValue placeholder="Année universitaire" />
                    </SelectTrigger>
                    <SelectContent>
                        {options.academicYears.map((item) => (
                            <SelectItem key={item.id} value={String(item.id)}>
                                {item.name}
                                {item.is_current ? ' (courante)' : ''}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <Button type="submit" variant="outline">
                    Rechercher
                </Button>
            </form>
            <InputError message={Object.values(form.errors)[0]} />

            {/* The first step needs no trail: its only entry would repeat the step title. */}
            {!(step === 'establishments' && trail.length <= 1) && (
                <nav
                    aria-label="Navigation des étudiants"
                    className="flex flex-wrap items-center gap-1 text-sm"
                >
                    {trail.map((item, index) => (
                        <span key={index} className="flex items-center gap-1">
                            {index > 0 && (
                                <ChevronRight
                                    className="size-4 text-muted-foreground"
                                    aria-hidden="true"
                                />
                            )}
                            {index === trail.length - 1 && step !== 'list' ? (
                                <span
                                    className="font-medium text-foreground"
                                    aria-current="page"
                                >
                                    {item.label}
                                </span>
                            ) : (
                                <Link
                                    href={url(item.params)}
                                    className="text-primary underline-offset-4 hover:underline"
                                >
                                    {item.label}
                                </Link>
                            )}
                        </span>
                    ))}
                    {step === 'list' && (
                        <span className="flex items-center gap-1">
                            {trail.length > 0 && (
                                <ChevronRight
                                    className="size-4 text-muted-foreground"
                                    aria-hidden="true"
                                />
                            )}
                            <span className="font-medium" aria-current="page">
                                {filters.search
                                    ? `Recherche « ${filters.search} »`
                                    : 'Liste des étudiants'}
                            </span>
                        </span>
                    )}
                </nav>
            )}

            {!year && (
                <p
                    role="status"
                    className="rounded-lg border border-dashed bg-card p-4 text-sm"
                >
                    Aucune année universitaire courante n’est définie :
                    choisissez une année pour parcourir les étudiants.
                </p>
            )}

            {definition && (
                <section className="space-y-4" aria-labelledby="step-title">
                    <div className="flex flex-wrap items-end justify-between gap-3">
                        <div>
                            <h2
                                id="step-title"
                                className="text-lg font-bold text-foreground"
                            >
                                {definition.title}
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                {definition.hint}
                            </p>
                        </div>
                        {step !== 'establishments' && (
                            <Button variant="outline" size="sm" asChild>
                                <Link href={url({ ...current, view: 'list' })}>
                                    <List aria-hidden="true" />
                                    Voir toute la sélection en liste
                                </Link>
                            </Button>
                        )}
                    </div>
                    {cards.length === 0 ? (
                        <div className="flex flex-col items-center gap-2 rounded-lg border border-dashed bg-card p-10 text-center">
                            <Users
                                className="size-8 text-muted-foreground"
                                aria-hidden="true"
                            />
                            <p className="font-medium">
                                Aucune déclaration pour cette sélection.
                            </p>
                            <p className="text-sm text-muted-foreground">
                                Changez d’année universitaire ou remontez d’un
                                niveau dans la navigation.
                            </p>
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
                <section className="space-y-4">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <p className="text-sm text-muted-foreground">
                            {plural(records.total, 'déclaration')}
                        </p>
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
                                className="w-full sm:w-52"
                                aria-label="Statut de la déclaration"
                            >
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
                    <div className="overflow-x-auto rounded-lg border bg-card">
                        <table className="w-full min-w-[56rem] text-left text-sm">
                            <caption className="sr-only">
                                Liste des étudiants déclarés
                            </caption>
                            <thead className="border-b bg-muted text-muted-foreground">
                                <tr>
                                    {[
                                        'Matricule',
                                        'Nom et prénoms',
                                        'Établissement',
                                        'Niveau',
                                        'Mention',
                                        'Parcours',
                                        'Statut',
                                        'Actions',
                                    ].map((label) => (
                                        <th
                                            key={label}
                                            className="px-4 py-3 font-medium"
                                            scope="col"
                                        >
                                            {label}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {records.data.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={8}
                                            className="p-8 text-center text-muted-foreground"
                                        >
                                            Aucun étudiant ne correspond à cette
                                            sélection.
                                        </td>
                                    </tr>
                                ) : (
                                    records.data.map((record) => (
                                        <tr
                                            key={record.id}
                                            className="hover:bg-muted/50"
                                        >
                                            <td className="px-4 py-3 font-medium">
                                                {record.student
                                                    ?.student_number ?? '—'}
                                            </td>
                                            <td className="px-4 py-3">
                                                {record.student?.last_name}{' '}
                                                {record.student?.first_names}
                                            </td>
                                            <td className="px-4 py-3">
                                                {record.establishment?.code}
                                            </td>
                                            <td className="px-4 py-3">
                                                {record.level?.code}
                                            </td>
                                            <td className="px-4 py-3">
                                                {record.program?.mention
                                                    ?.name ?? 'Sans mention'}
                                            </td>
                                            <td className="px-4 py-3">
                                                {record.program?.name ??
                                                    'Sans parcours'}
                                            </td>
                                            <td className="px-4 py-3">
                                                <Badge
                                                    variant={
                                                        record.status ===
                                                        'active'
                                                            ? 'secondary'
                                                            : 'outline'
                                                    }
                                                >
                                                    {record.status === 'active'
                                                        ? 'Active'
                                                        : 'Inactive'}
                                                </Badge>
                                                {record.student?.is_active ===
                                                    false && (
                                                    <p className="mt-1 text-xs text-muted-foreground">
                                                        Identité inactive
                                                    </p>
                                                )}
                                            </td>
                                            <td className="px-4 py-3">
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    asChild
                                                >
                                                    <Link
                                                        href={`/students/${record.student?.uuid}`}
                                                    >
                                                        Consulter
                                                    </Link>
                                                </Button>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                    <nav
                        className="flex flex-wrap gap-1"
                        aria-label="Pagination"
                    >
                        {records.links.map((link, index) => {
                            const label =
                                index === 0
                                    ? 'Précédent'
                                    : index === records.links.length - 1
                                      ? 'Suivant'
                                      : link.label;
                            return link.url ? (
                                <Button
                                    key={index}
                                    size="sm"
                                    variant={
                                        link.active ? 'default' : 'outline'
                                    }
                                    asChild
                                >
                                    <Link
                                        href={link.url}
                                        aria-current={
                                            link.active ? 'page' : undefined
                                        }
                                    >
                                        {label}
                                    </Link>
                                </Button>
                            ) : null;
                        })}
                    </nav>
                </section>
            )}
        </div>
    );
}
StudentsIndex.layout = {
    breadcrumbs: [{ title: 'Étudiants', href: '/students' }],
};
