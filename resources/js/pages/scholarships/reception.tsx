import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    AlertCircle,
    Building2,
    ChevronRight,
    FolderOpen,
    GraduationCap,
    Inbox,
    Search,
    Tags,
    UserPlus,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useEffect, useState } from 'react';
import type { FormEvent, KeyboardEvent, ReactNode } from 'react';
import { EmptyState } from '@/components/empty-state';
import { PageBody, PageHeader } from '@/components/page-header';
import { ReceptionActivity } from '@/components/scholarships/reception-activity';
import type { ReceptionActivityData } from '@/components/scholarships/reception-activity';
import {
    missingLabels,
    splitMissing,
} from '@/components/scholarships/reception-shared';
import type { ReceptionRecord } from '@/components/scholarships/reception-shared';
import { formatDate } from '@/components/scholarships/shared';
import { ToneBadge } from '@/components/tone-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';

type Result = {
    id: number;
    uuid: string;
    /** Code bourse (PNES): carried by the paper file of a scholarship holder. */
    scholarship_code: string | null;
    last_name: string;
    first_names: string | null;
    birth_date: string | null;
    is_active: boolean;
    /** Identity fields still empty on the file. */
    missing: string[];
    records: ReceptionRecord[];
    application: {
        id: number;
        uuid: string;
        reference: string;
    } | null;
};

/** Short wording of a situation not yet confirmed by the establishment's list. */
const verificationShort: Record<string, string> = {
    pending: 'Pas encore dans la liste',
    matched: 'À valider',
};

const activeRecord = (result: Result) =>
    result.records.find((record) => record.status === 'active');

const initials = (result: Result) =>
    `${result.last_name.charAt(0)}${result.first_names?.charAt(0) ?? ''}`.toUpperCase();

function Detail({
    icon: Icon,
    children,
}: {
    icon: LucideIcon;
    children: ReactNode;
}) {
    return (
        <span className="inline-flex min-w-0 items-center gap-1.5">
            <Icon
                className="size-4 shrink-0 text-muted-foreground"
                aria-hidden="true"
            />
            <span className="min-w-0">{children}</span>
        </span>
    );
}

export default function Reception({
    year,
    search,
    results,
    activity,
    canCreateStudent,
}: {
    /** The current academic year, whose files are received; null: the reception is closed. */
    year: { id: number; name: string } | null;
    search: string;
    results: Result[];
    activity: ReceptionActivityData;
    /** A search without result offers to add the student. */
    canCreateStudent: boolean;
}) {
    const { auth } = usePage().props;
    const [query, setQuery] = useState(search);
    const [searching, setSearching] = useState(false);
    const [highlight, setHighlight] = useState(-1);
    /** The student's file, on its own page; the search is kept for the way back. */
    const fileUrl = (result: Result) =>
        `/scholarships/reception/students/${result.uuid}${
            search.length >= 2
                ? `?${new URLSearchParams({ search }).toString()}`
                : ''
        }`;
    const runSearch = (term: string) => {
        router.get(
            '/scholarships/reception',
            term.length >= 2 ? { search: term } : {},
            {
                only: ['results', 'search'],
                preserveState: true,
                preserveScroll: true,
                replace: true,
                onStart: () => setSearching(true),
                onFinish: () => setSearching(false),
            },
        );
    };

    // Live search: results follow the typing after a short pause.
    useEffect(() => {
        const term = query.trim();
        if (!year || term === search || term.length === 1) return;
        const timer = setTimeout(() => runSearch(term), 250);
        return () => clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [query, year]);
    useEffect(() => setHighlight(results.length > 0 ? 0 : -1), [results]);

    const find = (event: FormEvent) => {
        event.preventDefault();
        const result = results[highlight];
        if (result && query.trim() === search) router.visit(fileUrl(result));
        else if (year && query.trim().length >= 2) runSearch(query.trim());
    };
    const onSearchKey = (event: KeyboardEvent<HTMLInputElement>) => {
        if (event.key === 'Escape') setQuery('');
        if (results.length === 0) return;
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            setHighlight((index) => Math.min(results.length - 1, index + 1));
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            setHighlight((index) => Math.max(0, index - 1));
        }
    };
    const hasResults =
        Boolean(year) && search.length >= 2 && results.length > 0;
    const reportHref =
        auth.permissions.includes('reports.view_own') ||
        auth.permissions.includes('reports.view')
            ? '/reports?scope=mine'
            : null;

    return (
        <PageBody>
            <Head title="Réception des dossiers" />
            <PageHeader
                icon={Inbox}
                title="Réception des dossiers papier"
                description="Recherchez l’étudiant, ouvrez son dossier, complétez ses informations puis enregistrez la réception."
                actions={
                    <>
                        {auth.scholarships.labels && (
                            <Button variant="outline" asChild>
                                <Link href="/scholarships/reception/labels">
                                    <Tags aria-hidden="true" />
                                    Fiche d’étiquettes
                                </Link>
                            </Button>
                        )}
                        <ReceptionActivity
                            activity={activity}
                            reportHref={reportHref}
                        />
                    </>
                }
                meta={
                    year && (
                        <ToneBadge tone="success" className="whitespace-normal">
                            Bourse nationale · {year.name}
                        </ToneBadge>
                    )
                }
            />

            {!year ? (
                <div className="surface">
                    <EmptyState
                        illustration="scholarship"
                        title="Aucune année universitaire courante : la réception est fermée."
                        description="Les dossiers papier sont reçus pour l’année marquée « courante » dans Années universitaires. Contactez l’administrateur."
                    />
                </div>
            ) : (
                <div className="space-y-4">
                    <form
                        onSubmit={find}
                        role="search"
                        className="surface space-y-4 p-4 sm:p-5"
                    >
                        <div className="grid gap-1.5">
                            <Label htmlFor="reception-search">
                                Rechercher l’étudiant
                            </Label>
                            <div className="relative">
                                <Search
                                    className="pointer-events-none absolute top-1/2 left-3.5 size-5 -translate-y-1/2 text-muted-foreground"
                                    aria-hidden="true"
                                />
                                <Input
                                    id="reception-search"
                                    type="search"
                                    autoFocus
                                    autoComplete="off"
                                    className="h-12 rounded-lg pr-11 pl-11 text-base md:text-base"
                                    placeholder="Nom, prénoms, CIN, téléphone, email ou code bourse"
                                    maxLength={100}
                                    value={query}
                                    onChange={(e) => setQuery(e.target.value)}
                                    onKeyDown={onSearchKey}
                                    role="combobox"
                                    aria-expanded={hasResults}
                                    aria-controls="reception-results-list"
                                    aria-activedescendant={
                                        highlight >= 0 && hasResults
                                            ? `reception-result-${results[highlight]?.id}`
                                            : undefined
                                    }
                                    aria-describedby="reception-search-hint"
                                />
                                {searching && (
                                    <Spinner className="absolute top-1/2 right-3.5 -translate-y-1/2 text-muted-foreground" />
                                )}
                            </div>
                            <p
                                id="reception-search-hint"
                                className="text-xs text-muted-foreground"
                            >
                                Les résultats s’affichent pendant la saisie. ↑ ↓
                                pour choisir, Entrée pour ouvrir le dossier.
                            </p>
                        </div>
                    </form>

                    {search.length < 2 ? (
                        <div className="surface">
                            <EmptyState
                                compact
                                illustration="students"
                                title="Tapez au moins deux caractères"
                                description="Les étudiants correspondants s’affichent ici. Cliquez sur l’un d’eux pour ouvrir son dossier."
                            />
                        </div>
                    ) : results.length === 0 ? (
                        <div className="surface">
                            <EmptyState
                                compact
                                illustration="search"
                                title="Aucun étudiant ne correspond à cette recherche."
                                description={
                                    canCreateStudent
                                        ? 'Vérifiez l’orthographe ou essayez avec le CIN, le téléphone ou l’email. S’il n’est pas encore enregistré, ajoutez-le : vous reviendrez ici pour recevoir son dossier.'
                                        : 'Vérifiez l’orthographe ou essayez avec le CIN, le téléphone ou l’email.'
                                }
                                action={
                                    canCreateStudent && (
                                        <Button asChild>
                                            <Link
                                                href={`/students/create?${new URLSearchParams(
                                                    {
                                                        from_reception: '1',
                                                        reception_search:
                                                            search,
                                                    },
                                                ).toString()}`}
                                            >
                                                <UserPlus aria-hidden="true" />
                                                Ajouter « {search} »
                                            </Link>
                                        </Button>
                                    )
                                }
                            />
                        </div>
                    ) : (
                        <section
                            aria-labelledby="reception-results"
                            aria-busy={searching}
                            className={cn(
                                'space-y-2 transition-opacity',
                                searching && 'opacity-60',
                            )}
                        >
                            <h2
                                id="reception-results"
                                className="text-sm font-semibold text-foreground"
                            >
                                {results.length}{' '}
                                {results.length > 1
                                    ? 'étudiants trouvés'
                                    : 'étudiant trouvé'}
                                {results.length === 20 &&
                                    ' (affinez la recherche)'}
                            </h2>
                            <ul
                                id="reception-results-list"
                                role="listbox"
                                aria-label="Résultats"
                                className="surface divide-y overflow-hidden p-0"
                            >
                                {results.map((result, index) => (
                                    <ResultRow
                                        key={result.id}
                                        result={result}
                                        term={search}
                                        active={index === highlight}
                                        href={fileUrl(result)}
                                        onHover={() => setHighlight(index)}
                                    />
                                ))}
                            </ul>
                        </section>
                    )}
                </div>
            )}
        </PageBody>
    );
}

/** Puts the searched term in bold within a name. */
function Highlight({ text, term }: { text: string; term: string }) {
    const needle = term.trim().toLowerCase();
    const index = needle.length >= 2 ? text.toLowerCase().indexOf(needle) : -1;
    if (index < 0) return <>{text}</>;
    return (
        <>
            {text.slice(0, index)}
            <mark className="rounded-sm bg-warning-soft px-0.5 text-foreground">
                {text.slice(index, index + needle.length)}
            </mark>
            {text.slice(index + needle.length)}
        </>
    );
}

/** One student found: the whole row opens the student's file. */
function ResultRow({
    result,
    term,
    active,
    href,
    onHover,
}: {
    result: Result;
    term: string;
    active: boolean;
    href: string;
    onHover: () => void;
}) {
    const record = activeRecord(result);
    // Only required fields are « to complete »; optional ones are just counted.
    const missing = splitMissing(result.missing);
    return (
        <li
            id={`reception-result-${result.id}`}
            role="option"
            aria-selected={active}
            onMouseEnter={onHover}
        >
            <Link
                href={href}
                prefetch="hover"
                className={cn(
                    'flex w-full items-center gap-4 px-4 py-3.5 text-left transition-colors focus-visible:outline-none sm:px-5',
                    active ? 'bg-accent/60' : 'hover:bg-accent/40',
                )}
            >
                <span
                    className="flex size-11 shrink-0 items-center justify-center rounded-full bg-accent font-heading text-sm font-bold text-primary"
                    aria-hidden="true"
                >
                    {initials(result)}
                </span>
                <span className="min-w-0 flex-1 space-y-1">
                    <span className="block text-base leading-snug font-bold break-words text-foreground">
                        <Highlight
                            text={`${result.last_name} ${result.first_names ?? ''}`.trim()}
                            term={term}
                        />
                    </span>
                    <span className="block text-sm text-muted-foreground">
                        Né(e) le {formatDate(result.birth_date)}
                        {result.scholarship_code &&
                            ` · Code bourse ${result.scholarship_code}`}
                    </span>
                    {record && (
                        <span className="flex flex-wrap gap-x-4 gap-y-1 text-sm text-foreground">
                            <Detail icon={Building2}>
                                {record.establishment}
                            </Detail>
                            <Detail icon={GraduationCap}>
                                {record.level}
                                {record.program && ` · ${record.program}`}
                            </Detail>
                        </span>
                    )}
                    <span className="flex flex-wrap gap-2 pt-1">
                        {!result.is_active && (
                            <ToneBadge tone="danger">Fiche inactive</ToneBadge>
                        )}
                        {!record && (
                            <ToneBadge tone="warning">
                                {result.records.length === 0
                                    ? 'Non déclaré'
                                    : 'Situation inactive'}
                            </ToneBadge>
                        )}
                        {record?.is_scholarship_holder && (
                            <ToneBadge tone="info">Boursier PNES</ToneBadge>
                        )}
                        {record && record.verification !== 'verified' && (
                            <ToneBadge
                                tone={
                                    record.verification === 'matched'
                                        ? 'warning'
                                        : 'neutral'
                                }
                            >
                                {verificationShort[record.verification] ??
                                    record.verification_label}
                            </ToneBadge>
                        )}
                        {missing.required.length > 0 ? (
                            <span
                                title={missing.required
                                    .map(
                                        (field) =>
                                            missingLabels[field] ?? field,
                                    )
                                    .join(', ')}
                            >
                                <ToneBadge tone="warning">
                                    <AlertCircle
                                        className="size-3"
                                        aria-hidden="true"
                                    />
                                    {missing.required.length} info
                                    {missing.required.length > 1 && 's'} à
                                    compléter
                                </ToneBadge>
                            </span>
                        ) : (
                            missing.optional.length > 0 && (
                                <span
                                    title={missing.optional
                                        .map(
                                            (field) =>
                                                missingLabels[field] ?? field,
                                        )
                                        .join(', ')}
                                >
                                    <ToneBadge tone="neutral">
                                        {missing.optional.length} info
                                        {missing.optional.length > 1 &&
                                            's'}{' '}
                                        facultative
                                        {missing.optional.length > 1 && 's'}
                                    </ToneBadge>
                                </span>
                            )
                        )}
                        {result.application && (
                            <ToneBadge tone="info">
                                Déjà reçu · N° DDB{' '}
                                {result.application.reference}
                            </ToneBadge>
                        )}
                    </span>
                </span>
                <span className="hidden shrink-0 items-center gap-1.5 text-sm font-semibold text-primary sm:flex">
                    <FolderOpen className="size-4" aria-hidden="true" />
                    Ouvrir le dossier
                    <ChevronRight className="size-4" aria-hidden="true" />
                </span>
            </Link>
        </li>
    );
}

Reception.layout = {
    breadcrumbs: [
        { title: 'Réception des dossiers', href: '/scholarships/reception' },
    ],
};
