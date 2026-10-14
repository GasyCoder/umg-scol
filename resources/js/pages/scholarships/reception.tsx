import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    AlertCircle,
    Building2,
    CalendarDays,
    CalendarX2,
    Check,
    ClipboardList,
    FileText,
    GraduationCap,
    Search,
    UserRound,
    UserSearch,
    X,
} from 'lucide-react';
import { useRef, useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import { ErrorSummary, FormField } from '@/components/forms/form-kit';
import Heading from '@/components/heading';
import { formatDate } from '@/components/scholarships/shared';
import { Badge } from '@/components/ui/badge';
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
import { Spinner } from '@/components/ui/spinner';

type Result = {
    id: number;
    student_number: string | null;
    last_name: string;
    first_names: string | null;
    birth_date: string | null;
    is_active: boolean;
    records: {
        establishment: string;
        level: string;
        program: string | null;
        status: string;
    }[];
    application: {
        id: number;
        uuid: string;
        application_number: string;
    } | null;
};
type DeclarationRecord = Result['records'][number];

const activeRecord = (result: Result) =>
    result.records.find((record) => record.status === 'active');

const initials = (result: Result) =>
    `${result.last_name.charAt(0)}${result.first_names?.charAt(0) ?? ''}`.toUpperCase();

/** Why a result cannot be received, or null when it can. */
const blockReason = (result: Result, record: DeclarationRecord | undefined) => {
    if (!result.is_active)
        return 'Fiche étudiant inactive : réception impossible.';
    if (!record)
        return result.records.length === 0
            ? 'Aucune déclaration pour l’année de la campagne.'
            : 'Déclaration inactive pour l’année de la campagne.';
    return null;
};

function Avatar({
    result,
    large = false,
}: {
    result: Result;
    large?: boolean;
}) {
    return (
        <span
            className={`flex shrink-0 items-center justify-center rounded-full bg-accent font-heading font-bold text-primary ${large ? 'size-12 text-base' : 'size-11 text-sm'}`}
            aria-hidden="true"
        >
            {initials(result)}
        </span>
    );
}

function EmptyState({
    icon: Icon,
    title,
    children,
}: {
    icon: typeof Search;
    title: string;
    children?: ReactNode;
}) {
    return (
        <div
            role="status"
            className="flex flex-col items-center gap-3 rounded-lg border border-dashed bg-card px-6 py-10 text-center"
        >
            <span
                className="flex size-14 items-center justify-center rounded-full bg-accent text-primary"
                aria-hidden="true"
            >
                <Icon className="size-7" />
            </span>
            <p className="font-bold text-foreground">{title}</p>
            {children && (
                <div className="max-w-md text-sm text-muted-foreground">
                    {children}
                </div>
            )}
        </div>
    );
}

function Detail({
    icon: Icon,
    children,
}: {
    icon: typeof Search;
    children: ReactNode;
}) {
    return (
        <span className="inline-flex min-w-0 items-center gap-1.5">
            <Icon
                className="size-3.5 shrink-0 text-muted-foreground"
                aria-hidden="true"
            />
            <span className="min-w-0">{children}</span>
        </span>
    );
}

export default function Reception({
    campaigns,
    campaignId,
    search,
    results,
}: {
    campaigns: { id: number; name: string; academic_year: string }[];
    campaignId: number | null;
    search: string;
    results: Result[];
}) {
    const [query, setQuery] = useState(search);
    const [selected, setSelected] = useState<Result | null>(null);
    const panelRef = useRef<HTMLElement>(null);
    const ddbRef = useRef<HTMLInputElement>(null);
    const form = useForm({
        campaign_id: String(campaignId ?? ''),
        student_id: '',
        ddb_number: '',
        internal_notes: '',
    });
    const errors = form.errors as { [key: string]: string | undefined };
    const campaign = campaigns.find((item) => item.id === campaignId);
    const find = (event: FormEvent) => {
        event.preventDefault();
        setSelected(null);
        router.get(
            '/scholarships/reception',
            { campaign_id: campaignId ?? '', search: query },
            { preserveState: true, replace: true },
        );
    };
    const choose = (result: Result) => {
        setSelected(result);
        form.clearErrors();
        form.setData({
            campaign_id: String(campaignId ?? ''),
            student_id: String(result.id),
            ddb_number: '',
            internal_notes: '',
        });
        // On one-column layouts the form sits below the results: bring it into view.
        requestAnimationFrame(() => {
            if (window.matchMedia('(max-width: 1023px)').matches) {
                panelRef.current?.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start',
                });
            }
            ddbRef.current?.focus({ preventScroll: true });
        });
    };
    const receive = (event: FormEvent) => {
        event.preventDefault();
        form.post('/scholarships/applications');
    };
    const selectedRecord = selected ? activeRecord(selected) : undefined;

    return (
        <div className="space-y-6 p-4 md:p-6">
            <Head title="Réception des dossiers" />
            <Heading
                title="Réception des dossiers papier"
                description="Recherchez l’étudiant qui dépose son dossier, vérifiez qu’il s’agit de la bonne personne, puis enregistrez la réception. Le dossier est rattaché à la déclaration annuelle de l’année de la campagne."
            />

            {campaigns.length === 0 ? (
                <EmptyState
                    icon={CalendarX2}
                    title="Aucune campagne ouverte : la réception est impossible."
                >
                    Les dossiers papier ne peuvent être enregistrés que pendant
                    une campagne de bourse ouverte. Contactez le responsable des
                    campagnes.
                </EmptyState>
            ) : (
                <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_24rem] xl:grid-cols-[minmax(0,1fr)_28rem]">
                    <div className="min-w-0 space-y-4">
                        <form
                            onSubmit={find}
                            role="search"
                            className="space-y-4 rounded-lg border bg-card p-4 sm:p-5"
                        >
                            <div className="grid gap-1.5">
                                <Label htmlFor="reception-campaign">
                                    Campagne
                                </Label>
                                <Select
                                    value={campaignId ? String(campaignId) : ''}
                                    onValueChange={(value) => {
                                        setSelected(null);
                                        router.get(
                                            '/scholarships/reception',
                                            {
                                                campaign_id: value,
                                                search: query,
                                            },
                                            {
                                                preserveState: true,
                                                replace: true,
                                            },
                                        );
                                    }}
                                >
                                    <SelectTrigger
                                        id="reception-campaign"
                                        className="w-full sm:max-w-md"
                                    >
                                        <SelectValue placeholder="Choisissez une campagne ouverte" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {campaigns.map((item) => (
                                            <SelectItem
                                                key={item.id}
                                                value={String(item.id)}
                                            >
                                                {item.name} (
                                                {item.academic_year})
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="grid gap-1.5">
                                <Label htmlFor="reception-search">
                                    Rechercher l’étudiant
                                </Label>
                                <div className="flex flex-col gap-2 sm:flex-row">
                                    <div className="relative flex-1">
                                        <Search
                                            className="pointer-events-none absolute top-1/2 left-3.5 size-5 -translate-y-1/2 text-muted-foreground"
                                            aria-hidden="true"
                                        />
                                        <Input
                                            id="reception-search"
                                            type="search"
                                            className="h-11 pl-11 text-base md:text-base"
                                            placeholder="Matricule, nom, prénom, CIN, numéro BAC, téléphone ou email"
                                            maxLength={100}
                                            value={query}
                                            onChange={(e) =>
                                                setQuery(e.target.value)
                                            }
                                            disabled={!campaignId}
                                            aria-describedby="reception-search-hint"
                                        />
                                    </div>
                                    <Button
                                        type="submit"
                                        className="h-11 px-5"
                                        disabled={
                                            !campaignId ||
                                            query.trim().length < 2
                                        }
                                    >
                                        <Search aria-hidden="true" />
                                        Rechercher
                                    </Button>
                                </div>
                                <p
                                    id="reception-search-hint"
                                    className="text-xs text-muted-foreground"
                                >
                                    {campaign
                                        ? `Campagne : ${campaign.name} — année ${campaign.academic_year}. Saisissez au moins deux caractères ; 20 résultats au plus sont affichés.`
                                        : 'Choisissez d’abord la campagne ouverte concernée.'}
                                </p>
                            </div>
                        </form>

                        {!campaignId ? (
                            <EmptyState
                                icon={CalendarDays}
                                title="Choisissez une campagne"
                            >
                                Plusieurs campagnes sont ouvertes : précisez
                                celle à laquelle le dossier est rattaché.
                            </EmptyState>
                        ) : search.length < 2 ? (
                            <EmptyState
                                icon={UserSearch}
                                title="Recherchez l’étudiant qui dépose son dossier"
                            >
                                Les résultats s’affichent ici avec leur
                                déclaration pour l’année de la campagne.
                            </EmptyState>
                        ) : results.length === 0 ? (
                            <EmptyState
                                icon={UserSearch}
                                title="Aucun étudiant ne correspond à cette recherche."
                            >
                                Vérifiez l’orthographe ou essayez avec le
                                matricule, le CIN ou le numéro BAC.
                            </EmptyState>
                        ) : (
                            <section
                                aria-labelledby="reception-results"
                                className="space-y-3"
                            >
                                <h2
                                    id="reception-results"
                                    className="text-sm text-muted-foreground"
                                >
                                    {results.length}{' '}
                                    {results.length > 1
                                        ? 'étudiants trouvés'
                                        : 'étudiant trouvé'}
                                </h2>
                                <ul className="space-y-3">
                                    {results.map((result) => {
                                        const record = activeRecord(result);
                                        const reason = blockReason(
                                            result,
                                            record,
                                        );
                                        const isSelected =
                                            selected?.id === result.id;
                                        return (
                                            <li
                                                key={result.id}
                                                className={`rounded-lg border bg-card p-4 transition-colors ${isSelected ? 'border-primary ring-1 ring-primary' : 'hover:border-primary/40'}`}
                                            >
                                                <article className="flex flex-col gap-4 sm:flex-row sm:items-start">
                                                    <Avatar result={result} />
                                                    <div className="min-w-0 flex-1 space-y-2">
                                                        <div>
                                                            <h3 className="font-bold text-foreground">
                                                                {
                                                                    result.last_name
                                                                }{' '}
                                                                {
                                                                    result.first_names
                                                                }
                                                            </h3>
                                                            <p className="text-sm text-muted-foreground">
                                                                <span className="font-mono">
                                                                    {result.student_number ??
                                                                        'Sans matricule'}
                                                                </span>
                                                                {' · '}
                                                                Né(e) le{' '}
                                                                {formatDate(
                                                                    result.birth_date,
                                                                )}
                                                            </p>
                                                        </div>
                                                        {record && (
                                                            <p className="flex flex-wrap gap-x-4 gap-y-1 text-sm">
                                                                <Detail
                                                                    icon={
                                                                        Building2
                                                                    }
                                                                >
                                                                    {
                                                                        record.establishment
                                                                    }
                                                                </Detail>
                                                                <Detail
                                                                    icon={
                                                                        GraduationCap
                                                                    }
                                                                >
                                                                    {
                                                                        record.level
                                                                    }
                                                                    {record.program &&
                                                                        ` · ${record.program}`}
                                                                </Detail>
                                                            </p>
                                                        )}
                                                        <div className="flex flex-wrap gap-2">
                                                            {result.is_active ? (
                                                                <Badge variant="secondary">
                                                                    <UserRound aria-hidden="true" />
                                                                    Fiche active
                                                                </Badge>
                                                            ) : (
                                                                <Badge variant="destructive">
                                                                    <X aria-hidden="true" />
                                                                    Fiche
                                                                    inactive
                                                                </Badge>
                                                            )}
                                                            {record ? (
                                                                <Badge variant="secondary">
                                                                    <Check aria-hidden="true" />
                                                                    Déclaration
                                                                    active
                                                                </Badge>
                                                            ) : (
                                                                <Badge variant="outline">
                                                                    <AlertCircle aria-hidden="true" />
                                                                    {result
                                                                        .records
                                                                        .length ===
                                                                    0
                                                                        ? 'Non déclaré'
                                                                        : 'Déclaration inactive'}
                                                                </Badge>
                                                            )}
                                                            {result.application && (
                                                                <Badge variant="outline">
                                                                    <FileText aria-hidden="true" />
                                                                    Déjà reçu
                                                                </Badge>
                                                            )}
                                                        </div>
                                                        {!result.application &&
                                                            reason && (
                                                                <p className="flex items-center gap-1.5 text-sm text-destructive">
                                                                    <AlertCircle
                                                                        className="size-4 shrink-0"
                                                                        aria-hidden="true"
                                                                    />
                                                                    {reason}
                                                                </p>
                                                            )}
                                                    </div>
                                                    <div className="shrink-0 sm:self-center">
                                                        {result.application ? (
                                                            <Button
                                                                variant="outline"
                                                                size="sm"
                                                                className="w-full sm:w-auto"
                                                                asChild
                                                            >
                                                                <Link
                                                                    href={`/scholarships/applications/${result.application.uuid}`}
                                                                >
                                                                    <FileText aria-hidden="true" />
                                                                    Dossier
                                                                    existant n°{' '}
                                                                    {
                                                                        result
                                                                            .application
                                                                            .application_number
                                                                    }
                                                                </Link>
                                                            </Button>
                                                        ) : (
                                                            <Button
                                                                type="button"
                                                                size="sm"
                                                                className="w-full sm:w-auto"
                                                                variant={
                                                                    isSelected
                                                                        ? 'default'
                                                                        : 'outline'
                                                                }
                                                                disabled={
                                                                    reason !==
                                                                    null
                                                                }
                                                                aria-pressed={
                                                                    isSelected
                                                                }
                                                                title={
                                                                    reason ??
                                                                    undefined
                                                                }
                                                                onClick={() =>
                                                                    choose(
                                                                        result,
                                                                    )
                                                                }
                                                            >
                                                                {isSelected && (
                                                                    <Check aria-hidden="true" />
                                                                )}
                                                                {isSelected
                                                                    ? 'Sélectionné'
                                                                    : 'Sélectionner'}
                                                            </Button>
                                                        )}
                                                    </div>
                                                </article>
                                            </li>
                                        );
                                    })}
                                </ul>
                            </section>
                        )}
                    </div>

                    <aside
                        ref={panelRef}
                        aria-labelledby="reception-panel-title"
                        className="scroll-mt-4 rounded-lg border bg-card lg:sticky lg:top-6"
                    >
                        <header className="flex items-center gap-3 border-b px-5 py-4">
                            <span
                                className="flex size-9 shrink-0 items-center justify-center rounded-full bg-accent text-primary"
                                aria-hidden="true"
                            >
                                <ClipboardList className="size-4" />
                            </span>
                            <div className="min-w-0">
                                <h2
                                    id="reception-panel-title"
                                    className="text-base font-bold text-foreground"
                                >
                                    Dossier à enregistrer
                                </h2>
                                <p className="text-xs text-muted-foreground">
                                    {campaign
                                        ? `${campaign.name} · ${campaign.academic_year}`
                                        : 'Aucune campagne choisie'}
                                </p>
                            </div>
                        </header>
                        {selected && campaign ? (
                            <form onSubmit={receive} className="space-y-5 p-5">
                                <ErrorSummary
                                    errors={errors}
                                    labels={{
                                        student_id: 'Étudiant',
                                        campaign_id: 'Campagne',
                                        ddb_number: 'Numéro DDB',
                                        internal_notes: 'Observations',
                                    }}
                                />
                                <div className="flex items-center gap-3">
                                    <Avatar result={selected} large />
                                    <div className="min-w-0">
                                        <p className="font-bold text-foreground">
                                            {selected.last_name}{' '}
                                            {selected.first_names}
                                        </p>
                                        <p className="font-mono text-sm text-muted-foreground">
                                            {selected.student_number ??
                                                'Sans matricule'}
                                        </p>
                                    </div>
                                </div>
                                <dl className="grid grid-cols-2 gap-x-4 gap-y-3 rounded-lg bg-muted p-4 text-sm">
                                    {[
                                        [
                                            'Date de naissance',
                                            formatDate(selected.birth_date),
                                        ],
                                        [
                                            'Année universitaire',
                                            campaign.academic_year,
                                        ],
                                        [
                                            'Établissement',
                                            selectedRecord?.establishment ??
                                                '—',
                                        ],
                                        [
                                            'Niveau',
                                            selectedRecord?.level ?? '—',
                                        ],
                                        [
                                            'Parcours',
                                            selectedRecord?.program ??
                                                'Non renseigné',
                                        ],
                                    ].map(([label, value]) => (
                                        <div key={label} className="min-w-0">
                                            <dt className="text-xs text-muted-foreground">
                                                {label}
                                            </dt>
                                            <dd className="font-medium break-words text-foreground">
                                                {value}
                                            </dd>
                                        </div>
                                    ))}
                                </dl>
                                <FormField
                                    id="ddb_number"
                                    label="Numéro DDB (formulaire papier)"
                                    hint="Numéro inscrit sur le formulaire papier déposé."
                                    error={errors.ddb_number}
                                >
                                    <Input
                                        id="ddb_number"
                                        ref={ddbRef}
                                        maxLength={100}
                                        value={form.data.ddb_number}
                                        aria-invalid={
                                            errors.ddb_number ? true : undefined
                                        }
                                        onChange={(e) =>
                                            form.setData(
                                                'ddb_number',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </FormField>
                                <FormField
                                    id="internal_notes"
                                    label="Observations internes"
                                    hint="Visibles uniquement par la Scolarité Centrale."
                                    error={errors.internal_notes}
                                >
                                    <textarea
                                        id="internal_notes"
                                        className="min-h-24 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive"
                                        maxLength={2000}
                                        value={form.data.internal_notes}
                                        aria-invalid={
                                            errors.internal_notes
                                                ? true
                                                : undefined
                                        }
                                        onChange={(e) =>
                                            form.setData(
                                                'internal_notes',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </FormField>
                                <div className="flex flex-col-reverse gap-2 border-t pt-4 sm:flex-row sm:justify-end">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        disabled={form.processing}
                                        onClick={() => setSelected(null)}
                                    >
                                        Annuler
                                    </Button>
                                    <Button
                                        type="submit"
                                        disabled={form.processing}
                                    >
                                        {form.processing && <Spinner />}
                                        {form.processing
                                            ? 'Enregistrement…'
                                            : 'Enregistrer la réception'}
                                    </Button>
                                </div>
                            </form>
                        ) : (
                            <div className="flex flex-col items-center gap-3 px-6 py-10 text-center">
                                <span
                                    className="flex size-14 items-center justify-center rounded-full bg-muted text-muted-foreground"
                                    aria-hidden="true"
                                >
                                    <UserRound className="size-7" />
                                </span>
                                <p className="font-bold text-foreground">
                                    Sélectionnez un étudiant
                                </p>
                                <p className="max-w-xs text-sm text-muted-foreground">
                                    Choisissez l’étudiant dans les résultats
                                    pour vérifier son identité et sa
                                    déclaration, puis saisir le numéro DDB.
                                </p>
                            </div>
                        )}
                    </aside>
                </div>
            )}
        </div>
    );
}
Reception.layout = {
    breadcrumbs: [
        { title: 'Réception des dossiers', href: '/scholarships/reception' },
    ],
};
