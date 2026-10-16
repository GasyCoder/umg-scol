import { Head, Link, router } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowLeft,
    BadgeCheck,
    CalendarRange,
    GraduationCap,
    HandCoins,
    History,
    IdCard,
    Mail,
    Pencil,
    Phone,
    Plus,
    Power,
    ShieldCheck,
    UserRound,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { IconAction } from '@/components/icon-action';
import { PageBody, PageHeader } from '@/components/page-header';
import { ToneBadge, toneFor } from '@/components/tone-badge';
import type { Tone } from '@/components/tone-badge';
import { TrashButton } from '@/components/trash/trash-button';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { REPEAT_CODE_LABELS, SEX_LABELS } from '@/components/students/types';
import type {
    AnnualRecord,
    Eligibility,
    Student,
} from '@/components/students/types';
import { cn } from '@/lib/utils';

type DeclarationRow = AnnualRecord & {
    uuid: string;
    academic_year?: {
        id: number;
        name: string;
        is_current?: boolean;
        scholarship_max_bac_year?: number | null;
    };
    program?: {
        id: number;
        name: string;
        mention?: { id: number; name: string } | null;
    } | null;
};
type StudentConfirmation = {
    status: 'never_confirmed' | 'confirmed' | 'stale';
    confirmed_at: string | null;
    academic_year: string | null;
};
type Pending = {
    url: string;
    data: Record<string, string | boolean>;
    title: string;
    description?: string;
    method?: 'patch' | 'delete';
} | null;
type Tab = 'informations' | 'verification';

const confirmationLabels: Record<StudentConfirmation['status'], string> = {
    never_confirmed: 'Jamais confirmée',
    confirmed: 'Confirmée',
    stale: 'À confirmer de nouveau',
};
const confirmationTones: Record<StudentConfirmation['status'], Tone> = {
    never_confirmed: 'neutral',
    confirmed: 'success',
    stale: 'warning',
};
const tabs: { key: Tab; label: string; icon: LucideIcon }[] = [
    { key: 'informations', label: 'Informations', icon: IdCard },
    { key: 'verification', label: 'Vérification', icon: BadgeCheck },
];
const date = (value: string | null | undefined) =>
    value ? new Date(value).toLocaleDateString('fr-FR') : null;
const eligibilityTones: Record<Eligibility['status'], Tone> = {
    eligible: 'success',
    not_eligible: 'danger',
    undetermined: 'neutral',
};
const eligibilityLabel = (eligibility: Eligibility) =>
    eligibility.status === 'eligible'
        ? `Éligible au taux de ${eligibility.rate}`
        : eligibility.status === 'not_eligible'
          ? 'Non éligible'
          : 'Indéterminé';
const initials = (student: Student) =>
    [student.last_name, student.first_names]
        .filter(Boolean)
        .map((part) => String(part).trim().charAt(0))
        .join('')
        .slice(0, 2)
        .toUpperCase();

/** Label above value; empty values read « Non renseigné ». */
function Field({
    label,
    value,
    wide = false,
    extra,
}: {
    label: string;
    value: string | number | null | undefined;
    wide?: boolean;
    /** Optional marker shown after a filled value (e.g. « CIN non standard »). */
    extra?: ReactNode;
}) {
    const empty = value === null || value === undefined || value === '';
    return (
        <div className={cn('min-w-0', wide && 'sm:col-span-2')}>
            <dt className="text-xs font-medium text-muted-foreground">
                {label}
            </dt>
            <dd
                className={cn(
                    'mt-0.5 text-sm break-words',
                    empty
                        ? 'text-muted-foreground/80 italic'
                        : 'font-semibold text-foreground',
                )}
            >
                {empty ? (
                    'Non renseigné'
                ) : (
                    <>
                        {value}
                        {extra && <span className="ml-2">{extra}</span>}
                    </>
                )}
            </dd>
        </div>
    );
}

/** Group of official information: icon tile, title and a two-column key/value grid. */
function Section({
    title,
    icon: Icon,
    children,
}: {
    title: string;
    icon: LucideIcon;
    children: ReactNode;
}) {
    return (
        <section className="rounded-xl border bg-muted/20 p-4 sm:p-5">
            <h3 className="mb-4 flex items-center gap-2.5 text-sm font-bold text-foreground">
                <span
                    className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-accent text-primary"
                    aria-hidden="true"
                >
                    <Icon className="size-4" />
                </span>
                {title}
            </h3>
            <dl className="grid gap-x-6 gap-y-4 sm:grid-cols-2">{children}</dl>
        </section>
    );
}

/** Compact row of the side panel: small icon, label and value. */
function SideRow({
    icon: Icon,
    label,
    value,
    extra,
}: {
    icon: LucideIcon;
    label: string;
    value: string | null | undefined;
    extra?: ReactNode;
}) {
    return (
        <div className="flex items-start gap-3 py-2.5">
            <span
                className="mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-md bg-secondary text-muted-foreground"
                aria-hidden="true"
            >
                <Icon className="size-3.5" />
            </span>
            <div className="min-w-0">
                <dt className="text-xs text-muted-foreground">{label}</dt>
                <dd className="text-sm font-semibold break-words text-foreground">
                    {value ? (
                        <>
                            {value}
                            {extra && <span className="ml-2">{extra}</span>}
                        </>
                    ) : (
                        <span className="font-normal text-muted-foreground/80 italic">
                            Non renseigné
                        </span>
                    )}
                </dd>
            </div>
        </div>
    );
}

/** Indicative scholarship rule of a declaration, with the reasons when it is not granted. */
export default function StudentShow({
    student,
    records,
    confirmation: studentConfirmation,
    portalLinked,
    canDetachPortal,
    canUpdate,
    canDeactivate,
    canCreateRecord,
    currentYear,
}: {
    student: Student & { cin_is_standard: boolean };
    records: DeclarationRow[];
    confirmation: StudentConfirmation;
    portalLinked: boolean;
    canDetachPortal: boolean;
    canUpdate: boolean;
    canDeactivate: boolean;
    canCreateRecord: boolean;
    currentYear: { id: number; name: string } | null;
}) {
    const [tab, setTab] = useState<Tab>('informations');
    const [pending, setPending] = useState<Pending>(null);
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState('');
    const confirm = () => {
        if (!pending) return;
        setProcessing(true);
        setError('');
        const send =
            pending.method === 'delete'
                ? (url: string, options: Parameters<typeof router.delete>[1]) =>
                      router.delete(url, options)
                : (url: string, options: Parameters<typeof router.delete>[1]) =>
                      router.patch(url, pending.data, options);
        send(pending.url, {
            preserveScroll: true,
            onSuccess: () => setPending(null),
            onError: (errors) =>
                setError(
                    Object.values(errors)[0] ?? 'La modification a échoué.',
                ),
            onFinish: () => setProcessing(false),
        });
    };
    const fullName = `${student.last_name} ${student.first_names ?? ''}`.trim();
    // Situation of the current year (the active one first), then the previous years as history.
    const situation =
        records.find(
            (record) =>
                record.academic_year?.is_current && record.status === 'active',
        ) ??
        records.find((record) => record.academic_year?.is_current) ??
        null;
    const previous = records.filter((record) => record !== situation);
    const yearCount = new Set(records.map((record) => record.academic_year_id))
        .size;
    const toggleSituation = (record: DeclarationRow) =>
        setPending({
            url: `/student-records/${record.uuid}/status`,
            data: {
                status: record.status === 'active' ? 'inactive' : 'active',
            },
            title:
                record.status === 'active'
                    ? `Désactiver la situation ${record.academic_year?.name ?? ''}`
                    : `Réactiver la situation ${record.academic_year?.name ?? ''}`,
        });
    const birth = [
        student.sex ? SEX_LABELS[student.sex] : null,
        student.birth_date
            ? `${student.sex === 'F' ? 'née' : 'né'} le ${date(student.birth_date)}`
            : null,
        student.nationality,
    ]
        .filter(Boolean)
        .join(' · ');
    // A CIN other than 12 digits is accepted but flagged for the agent.
    const cinFlag =
        student.cin && !student.cin_is_standard ? (
            <ToneBadge tone="warning">CIN non standard</ToneBadge>
        ) : undefined;
    const join = (...parts: (string | null | undefined)[]) =>
        parts.filter(Boolean).join(' à ');
    const moveTab = (index: number) => {
        const next = tabs[(index + tabs.length) % tabs.length];
        setTab(next.key);
        document.getElementById(`tab-${next.key}`)?.focus();
    };

    return (
        <>
            <Head title={fullName} />
            <PageBody>
                <PageHeader
                    icon={UserRound}
                    title={fullName}
                    description={
                        birth ? `Fiche étudiant · ${birth}` : 'Fiche étudiant'
                    }
                    meta={
                        <div className="flex flex-wrap gap-2">
                            <ToneBadge
                                tone={student.is_active ? 'success' : 'neutral'}
                            >
                                Identité{' '}
                                {student.is_active ? 'active' : 'inactive'}
                            </ToneBadge>
                            <ToneBadge
                                tone={
                                    confirmationTones[
                                        studentConfirmation.status
                                    ]
                                }
                            >
                                {confirmationLabels[studentConfirmation.status]}
                            </ToneBadge>
                            <ToneBadge tone={portalLinked ? 'info' : 'neutral'}>
                                Compte portail :{' '}
                                {portalLinked ? 'rattaché' : 'aucun'}
                            </ToneBadge>
                        </div>
                    }
                    actions={
                        <>
                            <Button variant="ghost" asChild>
                                <Link href="/students">
                                    <ArrowLeft aria-hidden="true" />
                                    Retour
                                </Link>
                            </Button>
                            <TrashButton
                                kind="students"
                                uuid={student.uuid}
                                label={`${student.last_name} ${student.first_names ?? ''}`}
                                redirect="/students"
                            />
                            {canDeactivate && (
                                <Button
                                    variant="outline"
                                    onClick={() =>
                                        setPending({
                                            url: `/students/${student.uuid}/status`,
                                            data: {
                                                is_active: !student.is_active,
                                            },
                                            title: student.is_active
                                                ? 'Désactiver l’étudiant'
                                                : 'Réactiver l’étudiant',
                                            description: student.is_active
                                                ? 'L’étudiant et sa situation de l’année courante passeront à « Inactive ». Les années précédentes sont conservées.'
                                                : 'L’étudiant redevient actif, ainsi que la situation de l’année désactivée en même temps que lui.',
                                        })
                                    }
                                >
                                    <Power aria-hidden="true" />
                                    {student.is_active
                                        ? 'Désactiver'
                                        : 'Réactiver'}
                                </Button>
                            )}
                            {canUpdate && (
                                <Button asChild>
                                    <Link
                                        href={`/students/${student.uuid}/edit`}
                                    >
                                        <Pencil aria-hidden="true" />
                                        Modifier
                                    </Link>
                                </Button>
                            )}
                        </>
                    }
                />

                <div className="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_20rem]">
                    <aside className="space-y-4 xl:order-last">
                        <section
                            className="surface overflow-hidden"
                            aria-label="Carte d’identité"
                        >
                            <div className="relative bg-sidebar px-5 pt-5 pb-12 text-sidebar-primary dark:bg-linear-to-br dark:from-accent dark:to-sidebar">
                                <div
                                    className="bg-grid absolute inset-0 text-sidebar-foreground opacity-25"
                                    aria-hidden="true"
                                />
                                <p className="relative text-xs font-semibold tracking-[0.18em] text-sidebar-muted uppercase">
                                    Identité déclarée
                                </p>
                                <p className="relative mt-0.5 text-sm font-medium text-sidebar-foreground">
                                    Université de Mahajanga
                                </p>
                            </div>
                            <div className="-mt-9 px-5 pb-5">
                                <div
                                    className="relative flex size-[4.5rem] items-center justify-center rounded-2xl bg-primary font-heading text-2xl font-extrabold text-primary-foreground shadow-elevated ring-4 ring-card"
                                    aria-hidden="true"
                                >
                                    {initials(student) || (
                                        <UserRound className="size-8" />
                                    )}
                                </div>
                                <p className="mt-3 font-heading text-lg leading-tight font-bold text-foreground">
                                    {fullName}
                                </p>
                                {birth && (
                                    <p className="mt-0.5 text-xs text-muted-foreground">
                                        {birth}
                                    </p>
                                )}
                                <dl className="mt-3 divide-y border-t">
                                    <SideRow
                                        icon={Mail}
                                        label="Email"
                                        value={student.email}
                                    />
                                    <SideRow
                                        icon={Phone}
                                        label="Téléphone"
                                        value={student.phone}
                                    />
                                    <SideRow
                                        icon={IdCard}
                                        label="CIN"
                                        value={student.cin}
                                        extra={cinFlag}
                                    />
                                </dl>
                            </div>
                            <div className="grid grid-cols-2 divide-x border-t bg-muted/30 text-center">
                                <div className="p-3">
                                    <p className="font-heading text-xl font-extrabold text-foreground tabular-nums">
                                        {yearCount}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        Année{yearCount > 1 ? 's' : ''}{' '}
                                        enregistrée{yearCount > 1 ? 's' : ''}
                                    </p>
                                </div>
                                <div className="p-3">
                                    <p
                                        className={cn(
                                            'font-heading text-xl font-extrabold',
                                            situation?.status === 'active'
                                                ? 'text-success'
                                                : 'text-muted-foreground',
                                        )}
                                    >
                                        {situation
                                            ? situation.status === 'active'
                                                ? 'Active'
                                                : 'Inactive'
                                            : '—'}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        Situation {currentYear?.name ?? ''}
                                    </p>
                                </div>
                            </div>
                        </section>
                    </aside>

                    <div className="surface min-w-0 overflow-hidden">
                        <div
                            role="tablist"
                            aria-label="Sections de la fiche"
                            className="flex gap-1 overflow-x-auto border-b bg-muted/30 px-2 pt-2 sm:px-4"
                        >
                            {tabs.map((item, index) => {
                                const selected = tab === item.key;
                                return (
                                    <button
                                        key={item.key}
                                        type="button"
                                        role="tab"
                                        id={`tab-${item.key}`}
                                        aria-selected={selected}
                                        aria-controls={`panel-${item.key}`}
                                        tabIndex={selected ? 0 : -1}
                                        onClick={() => setTab(item.key)}
                                        onKeyDown={(event) => {
                                            if (event.key === 'ArrowRight') {
                                                event.preventDefault();
                                                moveTab(index + 1);
                                            } else if (
                                                event.key === 'ArrowLeft'
                                            ) {
                                                event.preventDefault();
                                                moveTab(index - 1);
                                            }
                                        }}
                                        className={cn(
                                            'relative -mb-px inline-flex shrink-0 items-center gap-2 rounded-t-lg border border-b-0 px-4 py-2.5 font-heading text-sm font-bold transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                            selected
                                                ? 'border-border bg-card text-primary'
                                                : 'border-transparent text-muted-foreground hover:bg-card/60 hover:text-foreground',
                                        )}
                                    >
                                        <item.icon
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        {item.label}
                                    </button>
                                );
                            })}
                        </div>

                        {tab === 'informations' && (
                            <div
                                role="tabpanel"
                                id="panel-informations"
                                aria-labelledby="tab-informations"
                                className="space-y-5 p-5 sm:p-6"
                            >
                                <div>
                                    <h2 className="text-lg font-bold text-foreground">
                                        Informations officielles
                                    </h2>
                                    <p className="text-sm text-muted-foreground">
                                        Données déclarées par l’établissement.
                                        Les corrections demandées par l’étudiant
                                        passent par une revue.
                                    </p>
                                </div>
                                <section
                                    className="rounded-xl border bg-card p-4 shadow-card sm:p-5"
                                    aria-labelledby="situation-title"
                                >
                                    <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
                                        <h3
                                            id="situation-title"
                                            className="flex items-center gap-2.5 text-sm font-bold text-foreground"
                                        >
                                            <span
                                                className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-accent text-primary"
                                                aria-hidden="true"
                                            >
                                                <CalendarRange className="size-4" />
                                            </span>
                                            Situation{' '}
                                            {situation?.academic_year?.name ??
                                                currentYear?.name ??
                                                'de l’année'}
                                            {situation && (
                                                <ToneBadge
                                                    tone={toneFor(
                                                        situation.status,
                                                    )}
                                                >
                                                    {situation.status ===
                                                    'active'
                                                        ? 'Active'
                                                        : 'Inactive'}
                                                </ToneBadge>
                                            )}
                                        </h3>
                                        {situation?.canUpdate && (
                                            <div className="flex items-center gap-1">
                                                <IconAction
                                                    icon={Pencil}
                                                    label="Modifier la situation de l’année"
                                                    href={`/student-records/${situation.uuid}/edit`}
                                                />
                                                <IconAction
                                                    icon={Power}
                                                    tone={
                                                        situation.status ===
                                                        'active'
                                                            ? 'default'
                                                            : 'success'
                                                    }
                                                    label={
                                                        situation.status ===
                                                        'active'
                                                            ? 'Désactiver la situation'
                                                            : 'Réactiver la situation'
                                                    }
                                                    onClick={() =>
                                                        toggleSituation(
                                                            situation,
                                                        )
                                                    }
                                                />
                                            </div>
                                        )}
                                    </div>
                                    {situation ? (
                                        <div className="space-y-4">
                                            {!situation.repeat_code && (
                                                <p className="flex flex-wrap items-center gap-2 rounded-lg border border-warning/30 bg-warning-soft px-3 py-2 text-sm text-warning-foreground">
                                                    <AlertTriangle
                                                        className="size-4 shrink-0"
                                                        aria-hidden="true"
                                                    />
                                                    À compléter : le code de
                                                    redoublement n’est pas
                                                    renseigné.
                                                    {situation.canUpdate && (
                                                        <Link
                                                            href={`/student-records/${situation.uuid}/edit`}
                                                            className="font-semibold underline underline-offset-4"
                                                        >
                                                            Compléter
                                                        </Link>
                                                    )}
                                                </p>
                                            )}
                                            <dl className="grid gap-x-6 gap-y-4 sm:grid-cols-2 xl:grid-cols-3">
                                                <Field
                                                    label="Établissement"
                                                    value={
                                                        situation.establishment
                                                            ?.name
                                                    }
                                                />
                                                <Field
                                                    label="Niveau"
                                                    value={
                                                        situation.level?.name
                                                    }
                                                />
                                                <Field
                                                    label="Mention"
                                                    value={
                                                        situation.program
                                                            ?.mention?.name ??
                                                        'Sans mention'
                                                    }
                                                />
                                                <Field
                                                    label="Parcours"
                                                    value={
                                                        situation.program
                                                            ?.name ??
                                                        'Sans parcours'
                                                    }
                                                />
                                                <Field
                                                    label="Code de redoublement"
                                                    value={
                                                        situation.repeat_code
                                                            ? REPEAT_CODE_LABELS[
                                                                  situation
                                                                      .repeat_code
                                                              ]
                                                            : null
                                                    }
                                                />
                                                <Field
                                                    label="Demande de bourse"
                                                    value={
                                                        situation.scholarship_request_reference
                                                            ? `Réf. ${situation.scholarship_request_reference}${situation.scholarship_requested_at ? ` · déposée le ${date(situation.scholarship_requested_at)}` : ''}`
                                                            : 'Aucune demande'
                                                    }
                                                />
                                            </dl>
                                            {situation.eligibility && (
                                                <div className="flex flex-wrap items-start gap-x-3 gap-y-1 rounded-lg bg-muted/40 px-3 py-2">
                                                    <span className="flex items-center gap-2 text-xs font-medium text-muted-foreground">
                                                        <HandCoins
                                                            className="size-4"
                                                            aria-hidden="true"
                                                        />
                                                        Bourse (indicatif)
                                                    </span>
                                                    <ToneBadge
                                                        tone={
                                                            eligibilityTones[
                                                                situation
                                                                    .eligibility
                                                                    .status
                                                            ]
                                                        }
                                                    >
                                                        {eligibilityLabel(
                                                            situation.eligibility,
                                                        )}
                                                    </ToneBadge>
                                                    {situation.eligibility
                                                        .reasons.length > 0 && (
                                                        <span className="text-xs text-muted-foreground">
                                                            {situation.eligibility.reasons.join(
                                                                ' · ',
                                                            )}
                                                        </span>
                                                    )}
                                                </div>
                                            )}
                                            {situation.inactive_with_identity && (
                                                <p className="text-xs text-muted-foreground">
                                                    Désactivée en même temps que
                                                    l’étudiant ; elle sera
                                                    réactivée avec lui.
                                                </p>
                                            )}
                                        </div>
                                    ) : (
                                        <div className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-dashed bg-muted/30 p-4">
                                            <p className="text-sm text-muted-foreground">
                                                Aucune situation enregistrée
                                                pour{' '}
                                                {currentYear
                                                    ? currentYear.name
                                                    : 'l’année courante'}
                                                . Elle est créée par l’import du
                                                fichier de l’année ou par
                                                l’ajout d’un étudiant.
                                            </p>
                                            {canCreateRecord &&
                                                student.is_active &&
                                                currentYear && (
                                                    <Button size="sm" asChild>
                                                        <Link
                                                            href={`/students/${student.uuid}/records/create`}
                                                        >
                                                            <Plus aria-hidden="true" />
                                                            Ajouter la situation{' '}
                                                            {currentYear.name}
                                                        </Link>
                                                    </Button>
                                                )}
                                        </div>
                                    )}
                                </section>
                                <div className="grid gap-4 2xl:grid-cols-2">
                                    <Section title="Identité" icon={UserRound}>
                                        <Field
                                            label="Nom"
                                            value={student.last_name}
                                        />
                                        <Field
                                            label="Prénoms"
                                            value={student.first_names}
                                        />
                                        <Field
                                            label="Sexe"
                                            value={
                                                student.sex
                                                    ? SEX_LABELS[student.sex]
                                                    : null
                                            }
                                        />
                                        <Field
                                            label="Date de naissance"
                                            value={date(student.birth_date)}
                                        />
                                        <Field
                                            label="Nom et prénoms de la mère"
                                            value={student.mother_name}
                                        />
                                        <Field
                                            label="Nationalité"
                                            value={student.nationality}
                                        />
                                    </Section>
                                    <Section title="Contact" icon={Phone}>
                                        <Field
                                            label="Téléphone"
                                            value={student.phone}
                                        />
                                        <Field
                                            label="Email"
                                            value={student.email}
                                        />
                                        <Field
                                            label="Adresse exacte"
                                            value={student.address}
                                            wide
                                        />
                                    </Section>
                                    <Section
                                        title="Pièce d’identité (CIN)"
                                        icon={IdCard}
                                    >
                                        <Field
                                            label="Numéro"
                                            value={student.cin}
                                            extra={cinFlag}
                                        />
                                        <Field
                                            label="Délivrance"
                                            value={join(
                                                date(student.cin_issued_at),
                                                student.cin_issued_place,
                                            )}
                                        />
                                    </Section>
                                    <Section
                                        title="Baccalauréat"
                                        icon={GraduationCap}
                                    >
                                        <Field
                                            label="Année d’obtention"
                                            value={student.bac_year}
                                        />
                                        <Field
                                            label="Série"
                                            value={student.bac_series}
                                        />
                                    </Section>
                                </div>
                                {previous.length > 0 && (
                                    <section
                                        className="rounded-xl border bg-muted/20 p-4 sm:p-5"
                                        aria-labelledby="previous-title"
                                    >
                                        <h3
                                            id="previous-title"
                                            className="mb-3 flex items-center gap-2.5 text-sm font-bold text-foreground"
                                        >
                                            <span
                                                className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-accent text-primary"
                                                aria-hidden="true"
                                            >
                                                <History className="size-4" />
                                            </span>
                                            Années précédentes
                                        </h3>
                                        <ul className="divide-y rounded-lg border bg-card">
                                            {previous.map((record) => (
                                                <li
                                                    key={record.id}
                                                    className="flex flex-wrap items-center gap-x-4 gap-y-1 px-3 py-2 text-sm"
                                                >
                                                    <span className="w-24 font-semibold text-foreground">
                                                        {
                                                            record.academic_year
                                                                ?.name
                                                        }
                                                    </span>
                                                    <span
                                                        className="font-medium"
                                                        title={
                                                            record.establishment
                                                                ?.name
                                                        }
                                                    >
                                                        {record.establishment
                                                            ?.code ??
                                                            record.establishment
                                                                ?.name}
                                                    </span>
                                                    <span className="inline-flex rounded-md bg-secondary px-1.5 py-0.5 text-xs font-semibold text-secondary-foreground">
                                                        {record.level?.code ??
                                                            record.level?.name}
                                                    </span>
                                                    <span className="min-w-0 flex-1 truncate text-muted-foreground">
                                                        {record.program?.name ??
                                                            'Sans parcours'}
                                                    </span>
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
                                                </li>
                                            ))}
                                        </ul>
                                    </section>
                                )}
                            </div>
                        )}

                        {tab === 'verification' && (
                            <div
                                role="tabpanel"
                                id="panel-verification"
                                aria-labelledby="tab-verification"
                                className="space-y-5 p-5 sm:p-6"
                            >
                                <div>
                                    <h2 className="text-lg font-bold text-foreground">
                                        Vérification par l’étudiant
                                    </h2>
                                    <p className="text-sm text-muted-foreground">
                                        L’étudiant confirme, depuis le portail
                                        bourse, que les informations affichées
                                        sont correctes. Ce n’est ni une
                                        inscription ni une validation de
                                        dossier.
                                    </p>
                                </div>
                                <div
                                    className={cn(
                                        'flex items-center gap-4 rounded-xl border p-4',
                                        studentConfirmation.status ===
                                            'confirmed'
                                            ? 'border-success/30 bg-success-soft'
                                            : studentConfirmation.status ===
                                                'stale'
                                              ? 'border-warning/30 bg-warning-soft'
                                              : 'bg-muted/40',
                                    )}
                                >
                                    <span
                                        className={cn(
                                            'flex size-11 shrink-0 items-center justify-center rounded-xl bg-card shadow-card',
                                            studentConfirmation.status ===
                                                'confirmed'
                                                ? 'text-success'
                                                : studentConfirmation.status ===
                                                    'stale'
                                                  ? 'text-warning'
                                                  : 'text-muted-foreground',
                                        )}
                                        aria-hidden="true"
                                    >
                                        <ShieldCheck className="size-5" />
                                    </span>
                                    <div className="min-w-0">
                                        <p className="text-xs font-medium text-muted-foreground">
                                            Statut de la vérification
                                        </p>
                                        <ToneBadge
                                            tone={
                                                confirmationTones[
                                                    studentConfirmation.status
                                                ]
                                            }
                                            className="mt-1"
                                        >
                                            {
                                                confirmationLabels[
                                                    studentConfirmation.status
                                                ]
                                            }
                                        </ToneBadge>
                                    </div>
                                </div>
                                <dl className="grid gap-x-6 gap-y-4 rounded-xl border bg-muted/20 p-4 sm:grid-cols-2 sm:p-5">
                                    <Field
                                        label="Statut"
                                        value={
                                            confirmationLabels[
                                                studentConfirmation.status
                                            ]
                                        }
                                    />
                                    <Field
                                        label={
                                            studentConfirmation.status ===
                                            'confirmed'
                                                ? 'Confirmée le'
                                                : 'Dernière confirmation'
                                        }
                                        value={date(
                                            studentConfirmation.confirmed_at,
                                        )}
                                    />
                                    <Field
                                        label="Année concernée"
                                        value={
                                            studentConfirmation.academic_year
                                        }
                                    />
                                    <Field
                                        label="Compte portail"
                                        value={
                                            portalLinked
                                                ? 'Rattaché à cette fiche'
                                                : 'Aucun compte rattaché'
                                        }
                                    />
                                </dl>
                                {portalLinked && canDetachPortal && (
                                    <div className="flex flex-wrap items-center justify-between gap-3 rounded-lg border bg-muted/30 p-3 text-sm">
                                        <p className="text-muted-foreground">
                                            Un compte qui ne serait pas celui de
                                            l’étudiant peut être détaché ; le
                                            bon étudiant ouvrira de nouveau sa
                                            fiche avec ses informations.
                                        </p>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            className="border-danger/30 text-danger hover:bg-danger-soft hover:text-danger-foreground"
                                            onClick={() =>
                                                setPending({
                                                    url: `/students/${student.uuid}/portal`,
                                                    data: {},
                                                    method: 'delete',
                                                    title: 'Détacher le compte portail',
                                                    description:
                                                        'Le compte portail ne verra plus cette fiche. Il n’est pas supprimé, et l’étudiant pourra rouvrir sa fiche en vérifiant ses informations.',
                                                })
                                            }
                                        >
                                            Détacher le compte portail
                                        </Button>
                                    </div>
                                )}
                            </div>
                        )}
                    </div>
                </div>

                <Dialog
                    open={pending !== null}
                    onOpenChange={(open) => {
                        if (!open && !processing) setPending(null);
                    }}
                >
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>{pending?.title}</DialogTitle>
                            <DialogDescription>
                                {pending?.description ??
                                    'Confirmez cette modification. La situation et son historique seront conservés.'}
                            </DialogDescription>
                        </DialogHeader>
                        <InputError message={error} />
                        <DialogFooter>
                            <Button
                                variant="outline"
                                disabled={processing}
                                onClick={() => setPending(null)}
                            >
                                Annuler
                            </Button>
                            <Button disabled={processing} onClick={confirm}>
                                {processing ? 'Traitement…' : 'Confirmer'}
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </PageBody>
        </>
    );
}
StudentShow.layout = {
    breadcrumbs: [{ title: 'Étudiants', href: '/students' }],
};
