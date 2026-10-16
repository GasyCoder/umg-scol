import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    AlertCircle,
    ArrowRight,
    Building2,
    CalendarDays,
    Check,
    ClipboardList,
    FileCheck2,
    FileText,
    GraduationCap,
    Inbox,
    Info,
    Search,
    ShieldCheck,
    UserRound,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useRef, useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import { EmptyState } from '@/components/empty-state';
import { ErrorSummary, FormField } from '@/components/forms/form-kit';
import { PageBody, PageHeader } from '@/components/page-header';
import { formatDate } from '@/components/scholarships/shared';
import { ToneBadge } from '@/components/tone-badge';
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
import { cn } from '@/lib/utils';

type Result = {
    id: number;
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
            ? 'Aucune situation pour l’année de la campagne.'
            : 'Situation inactive pour l’année de la campagne.';
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
            className={cn(
                'flex shrink-0 items-center justify-center rounded-full font-heading font-bold',
                large
                    ? 'size-14 bg-primary text-lg text-primary-foreground ring-4 ring-accent'
                    : 'size-11 bg-accent text-sm text-primary',
            )}
            aria-hidden="true"
        >
            {initials(result)}
        </span>
    );
}

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

const alertTones = {
    success: 'border-success/30 bg-success-soft text-success-foreground',
    warning: 'border-warning/30 bg-warning-soft text-warning-foreground',
    info: 'border-info/30 bg-info-soft text-info-foreground',
    danger: 'border-danger/30 bg-danger-soft text-danger-foreground',
};

/** Soft inline alert: tinted surface, icon and text (never colour alone). */
function SoftAlert({
    tone,
    icon: Icon,
    title,
    children,
    action,
}: {
    tone: keyof typeof alertTones;
    icon: LucideIcon;
    title?: string;
    children?: ReactNode;
    action?: ReactNode;
}) {
    return (
        <div
            className={cn(
                'flex flex-col gap-3 rounded-lg border px-3.5 py-3 text-sm sm:flex-row sm:items-center',
                alertTones[tone],
            )}
        >
            <div className="flex min-w-0 flex-1 items-start gap-2.5">
                <Icon className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                <div className="min-w-0">
                    {title && <p className="font-semibold">{title}</p>}
                    {children && <div>{children}</div>}
                </div>
            </div>
            {action && <div className="shrink-0">{action}</div>}
        </div>
    );
}

type StepState = 'done' | 'current' | 'todo';

/** Three-step guide of the counter: search, check, register. */
function Stepper({ states }: { states: StepState[] }) {
    const steps = [
        { title: 'Rechercher', hint: 'Trouver l’étudiant qui dépose' },
        { title: 'Vérifier', hint: 'Identité et situation active' },
        { title: 'Enregistrer', hint: 'Saisir le N° DDB et valider' },
    ];
    return (
        <nav aria-label="Étapes de la réception" className="surface p-3 sm:p-4">
            <ol className="grid grid-cols-3 gap-2 sm:gap-4">
                {steps.map((step, index) => {
                    const state = states[index];
                    return (
                        <li
                            key={step.title}
                            aria-current={
                                state === 'current' ? 'step' : undefined
                            }
                            className="relative flex min-w-0 flex-col items-center gap-2 text-center sm:flex-row sm:items-center sm:text-left"
                        >
                            <span
                                className={cn(
                                    'flex size-9 shrink-0 items-center justify-center rounded-full font-heading text-sm font-bold transition-colors',
                                    state === 'done' &&
                                        'bg-success text-primary-foreground',
                                    state === 'current' &&
                                        'bg-primary text-primary-foreground ring-4 ring-accent',
                                    state === 'todo' &&
                                        'border-2 border-border bg-card text-muted-foreground',
                                )}
                                aria-hidden="true"
                            >
                                {state === 'done' ? (
                                    <Check className="size-4" />
                                ) : (
                                    index + 1
                                )}
                            </span>
                            <span className="min-w-0">
                                <span
                                    className={cn(
                                        'block text-xs font-bold sm:text-sm',
                                        state === 'todo'
                                            ? 'text-muted-foreground'
                                            : 'text-foreground',
                                    )}
                                >
                                    {step.title}
                                    <span className="sr-only">
                                        {state === 'done'
                                            ? ' (terminé)'
                                            : state === 'current'
                                              ? ' (étape en cours)'
                                              : ' (à venir)'}
                                    </span>
                                </span>
                                <span className="hidden text-xs text-muted-foreground md:block">
                                    {step.hint}
                                </span>
                            </span>
                            {index < steps.length - 1 && (
                                <span
                                    className={cn(
                                        'ml-auto hidden h-0.5 min-w-6 flex-1 rounded-full lg:block',
                                        state === 'done'
                                            ? 'bg-success'
                                            : 'bg-border',
                                    )}
                                    aria-hidden="true"
                                />
                            )}
                        </li>
                    );
                })}
            </ol>
        </nav>
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
    const hasResults =
        Boolean(campaignId) && search.length >= 2 && results.length > 0;
    const steps: StepState[] =
        selected && campaign
            ? ['done', 'done', 'current']
            : hasResults
              ? ['done', 'current', 'todo']
              : ['current', 'todo', 'todo'];

    return (
        <PageBody>
            <Head title="Réception des dossiers" />
            <PageHeader
                icon={Inbox}
                title="Réception des dossiers papier"
                description="Recherchez l’étudiant qui dépose son dossier, vérifiez qu’il s’agit de la bonne personne, puis enregistrez la réception. Le dossier est rattaché à la situation de l’étudiant pour l’année de la campagne."
                meta={
                    campaign && (
                        <ToneBadge tone="success" className="whitespace-normal">
                            Campagne ouverte : {campaign.name} ·{' '}
                            {campaign.academic_year}
                        </ToneBadge>
                    )
                }
            />

            {campaigns.length === 0 ? (
                <div className="surface">
                    <EmptyState
                        illustration="scholarship"
                        title="Aucune campagne ouverte : la réception est impossible."
                        description="Les dossiers papier ne peuvent être enregistrés que pendant une campagne de bourse ouverte. Contactez le responsable des campagnes."
                    />
                </div>
            ) : (
                <>
                    <Stepper states={steps} />
                    <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_24rem] xl:grid-cols-[minmax(0,1fr)_28rem]">
                        <div className="min-w-0 space-y-4">
                            <form
                                onSubmit={find}
                                role="search"
                                className="surface space-y-4 p-4 sm:p-5"
                            >
                                <div className="flex items-center gap-3">
                                    <span
                                        className="flex size-8 shrink-0 items-center justify-center rounded-full bg-accent font-heading text-sm font-bold text-primary"
                                        aria-hidden="true"
                                    >
                                        1
                                    </span>
                                    <h2 className="text-base font-bold text-foreground">
                                        Trouver l’étudiant
                                    </h2>
                                </div>
                                <div className="grid gap-1.5">
                                    <Label htmlFor="reception-campaign">
                                        Campagne
                                    </Label>
                                    <Select
                                        value={
                                            campaignId ? String(campaignId) : ''
                                        }
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
                                            <CalendarDays
                                                className="text-muted-foreground"
                                                aria-hidden="true"
                                            />
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
                                                className="h-12 rounded-lg pl-11 text-base md:text-base"
                                                placeholder="Nom, prénoms, CIN, téléphone ou email"
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
                                            className="h-12 rounded-lg px-6 text-base"
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
                                            ? `Campagne : ${campaign.name} (année ${campaign.academic_year}). Saisissez au moins deux caractères ; 20 résultats au plus sont affichés.`
                                            : 'Choisissez d’abord la campagne ouverte concernée.'}
                                    </p>
                                </div>
                            </form>

                            {!campaignId ? (
                                <div className="surface">
                                    <EmptyState
                                        compact
                                        illustration="inbox"
                                        title="Choisissez une campagne"
                                        description="Plusieurs campagnes sont ouvertes : précisez celle à laquelle le dossier est rattaché."
                                    />
                                </div>
                            ) : search.length < 2 ? (
                                <div className="surface">
                                    <EmptyState
                                        compact
                                        illustration="students"
                                        title="Recherchez l’étudiant qui dépose son dossier"
                                        description="Les résultats s’affichent ici avec leur situation pour l’année de la campagne."
                                    />
                                </div>
                            ) : results.length === 0 ? (
                                <div className="surface">
                                    <EmptyState
                                        compact
                                        illustration="search"
                                        title="Aucun étudiant ne correspond à cette recherche."
                                        description="Vérifiez l’orthographe ou essayez avec le CIN, le téléphone ou l’email."
                                    />
                                </div>
                            ) : (
                                <section
                                    aria-labelledby="reception-results"
                                    className="space-y-3"
                                >
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <h2
                                            id="reception-results"
                                            className="flex items-center gap-2 text-sm font-semibold text-foreground"
                                        >
                                            <span
                                                className="flex size-6 items-center justify-center rounded-full bg-accent font-heading text-xs font-bold text-primary"
                                                aria-hidden="true"
                                            >
                                                2
                                            </span>
                                            {results.length}{' '}
                                            {results.length > 1
                                                ? 'étudiants trouvés'
                                                : 'étudiant trouvé'}
                                        </h2>
                                        <p className="text-xs text-muted-foreground">
                                            Vérifiez l’identité avant de
                                            sélectionner.
                                        </p>
                                    </div>
                                    <ul className="space-y-3">
                                        {results.map((result) => (
                                            <ResultCard
                                                key={result.id}
                                                result={result}
                                                selected={
                                                    selected?.id === result.id
                                                }
                                                onChoose={choose}
                                            />
                                        ))}
                                    </ul>
                                </section>
                            )}
                        </div>

                        <aside
                            ref={panelRef}
                            aria-labelledby="reception-panel-title"
                            className={cn(
                                'surface scroll-mt-4 overflow-hidden transition-shadow lg:sticky lg:top-6',
                                selected &&
                                    campaign &&
                                    'border-primary/40 shadow-elevated',
                            )}
                        >
                            <header className="flex items-center gap-3 border-b bg-muted/40 px-5 py-4">
                                <span
                                    className={cn(
                                        'flex size-9 shrink-0 items-center justify-center rounded-full',
                                        selected && campaign
                                            ? 'bg-primary text-primary-foreground'
                                            : 'bg-accent text-primary',
                                    )}
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
                                    <p className="truncate text-xs text-muted-foreground">
                                        {campaign
                                            ? `${campaign.name} · ${campaign.academic_year}`
                                            : 'Aucune campagne choisie'}
                                    </p>
                                </div>
                            </header>
                            {selected && campaign ? (
                                <form
                                    onSubmit={receive}
                                    className="space-y-5 p-5"
                                >
                                    <ErrorSummary
                                        errors={errors}
                                        labels={{
                                            student_id: 'Étudiant',
                                            campaign_id: 'Campagne',
                                            ddb_number: 'Numéro DDB',
                                            internal_notes: 'Observations',
                                        }}
                                    />
                                    <div className="flex items-center gap-4">
                                        <Avatar result={selected} large />
                                        <div className="min-w-0">
                                            <p className="font-heading text-lg leading-tight font-bold break-words text-foreground">
                                                {selected.last_name}{' '}
                                                {selected.first_names}
                                            </p>
                                            <p className="mt-1 text-sm text-muted-foreground">
                                                Né(e) le{' '}
                                                {formatDate(
                                                    selected.birth_date,
                                                )}
                                            </p>
                                        </div>
                                    </div>
                                    <SoftAlert
                                        tone="success"
                                        icon={ShieldCheck}
                                        title="Réception possible"
                                    >
                                        Fiche active et situation active pour{' '}
                                        {campaign.academic_year}.
                                    </SoftAlert>
                                    <dl className="grid grid-cols-2 gap-x-4 gap-y-3 rounded-lg border bg-muted/40 p-4 text-sm">
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
                                            <div
                                                key={label}
                                                className="min-w-0"
                                            >
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
                                            className="h-11 font-mono text-base md:text-base"
                                            maxLength={100}
                                            value={form.data.ddb_number}
                                            aria-invalid={
                                                errors.ddb_number
                                                    ? true
                                                    : undefined
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
                                    <div className="space-y-2 border-t pt-4">
                                        <Button
                                            type="submit"
                                            size="lg"
                                            className="h-12 w-full text-base"
                                            disabled={form.processing}
                                        >
                                            {form.processing ? (
                                                <Spinner />
                                            ) : (
                                                <FileCheck2 aria-hidden="true" />
                                            )}
                                            {form.processing
                                                ? 'Enregistrement…'
                                                : 'Enregistrer la réception'}
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            className="w-full"
                                            disabled={form.processing}
                                            onClick={() => setSelected(null)}
                                        >
                                            Annuler
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
                                        situation, puis saisir le numéro DDB.
                                    </p>
                                </div>
                            )}
                        </aside>
                    </div>
                </>
            )}
        </PageBody>
    );
}

function ResultCard({
    result,
    selected,
    onChoose,
}: {
    result: Result;
    selected: boolean;
    onChoose: (result: Result) => void;
}) {
    const record = activeRecord(result);
    const reason = blockReason(result, record);
    return (
        <li
            className={cn(
                'surface p-4 transition-all sm:p-5',
                selected
                    ? 'border-primary ring-2 ring-primary/30'
                    : 'hover:border-primary/40 hover:shadow-elevated',
            )}
        >
            <article className="space-y-4">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start">
                    <div className="flex min-w-0 flex-1 items-start gap-3">
                        <Avatar result={result} />
                        <div className="min-w-0 flex-1 space-y-2">
                            <div>
                                <h3 className="text-base leading-snug font-bold break-words text-foreground">
                                    {result.last_name} {result.first_names}
                                </h3>
                                <p className="text-sm text-muted-foreground">
                                    Né(e) le {formatDate(result.birth_date)}
                                </p>
                            </div>
                            {record && (
                                <p className="flex flex-wrap gap-x-4 gap-y-1 text-sm text-foreground">
                                    <Detail icon={Building2}>
                                        {record.establishment}
                                    </Detail>
                                    <Detail icon={GraduationCap}>
                                        {record.level}
                                        {record.program &&
                                            ` · ${record.program}`}
                                    </Detail>
                                </p>
                            )}
                        </div>
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
                                    Dossier existant n°{' '}
                                    {result.application.application_number}
                                </Link>
                            </Button>
                        ) : (
                            <Button
                                type="button"
                                className="h-10 w-full sm:w-auto"
                                variant={selected ? 'default' : 'outline'}
                                disabled={reason !== null}
                                aria-pressed={selected}
                                title={reason ?? undefined}
                                onClick={() => onChoose(result)}
                            >
                                {selected ? (
                                    <Check aria-hidden="true" />
                                ) : (
                                    <ArrowRight aria-hidden="true" />
                                )}
                                {selected ? 'Sélectionné' : 'Sélectionner'}
                            </Button>
                        )}
                    </div>
                </div>
                <ul
                    className="flex flex-wrap gap-2"
                    aria-label="Contrôle d’éligibilité"
                >
                    <li>
                        {result.is_active ? (
                            <ToneBadge tone="success">Fiche active</ToneBadge>
                        ) : (
                            <ToneBadge tone="danger">Fiche inactive</ToneBadge>
                        )}
                    </li>
                    <li>
                        {record ? (
                            <ToneBadge tone="success">
                                Situation active
                            </ToneBadge>
                        ) : (
                            <ToneBadge tone="warning">
                                {result.records.length === 0
                                    ? 'Non déclaré'
                                    : 'Situation inactive'}
                            </ToneBadge>
                        )}
                    </li>
                    {result.application && (
                        <li>
                            <ToneBadge tone="info">Déjà reçu</ToneBadge>
                        </li>
                    )}
                </ul>
                {result.application ? (
                    <SoftAlert tone="info" icon={Info}>
                        Un dossier existe déjà pour cette campagne : ouvrez-le
                        plutôt que d’en enregistrer un second.
                    </SoftAlert>
                ) : (
                    reason && (
                        <SoftAlert tone="danger" icon={AlertCircle}>
                            {reason}
                        </SoftAlert>
                    )
                )}
            </article>
        </li>
    );
}
Reception.layout = {
    breadcrumbs: [
        { title: 'Réception des dossiers', href: '/scholarships/reception' },
    ],
};
