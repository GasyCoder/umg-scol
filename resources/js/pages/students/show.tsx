import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    BadgeCheck,
    BookMarked,
    Building2,
    CalendarDays,
    CalendarRange,
    GraduationCap,
    IdCard,
    Layers,
    Mail,
    Pencil,
    Phone,
    Plus,
    Power,
    Route,
    ShieldCheck,
    UserRound,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { EmptyState } from '@/components/empty-state';
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
import type { AnnualRecord, Student } from '@/components/students/types';
import { cn } from '@/lib/utils';

type DeclarationRow = AnnualRecord & {
    uuid: string;
    academic_year?: { id: number; name: string; is_current?: boolean };
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
} | null;
type Tab = 'informations' | 'declarations' | 'verification';

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
    { key: 'declarations', label: 'Déclarations', icon: CalendarDays },
    { key: 'verification', label: 'Vérification', icon: BadgeCheck },
];
const date = (value: string | null | undefined) =>
    value ? new Date(value).toLocaleDateString('fr-FR') : null;
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
}: {
    label: string;
    value: string | number | null | undefined;
    wide?: boolean;
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
                {empty ? 'Non renseigné' : value}
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
}: {
    icon: LucideIcon;
    label: string;
    value: string | null | undefined;
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
                    {value || (
                        <span className="font-normal text-muted-foreground/80 italic">
                            Non renseigné
                        </span>
                    )}
                </dd>
            </div>
        </div>
    );
}

export default function StudentShow({
    student,
    records,
    confirmation: studentConfirmation,
    portalLinked,
    canUpdate,
    canDeactivate,
    canCreateRecord,
}: {
    student: Student;
    records: DeclarationRow[];
    confirmation: StudentConfirmation;
    portalLinked: boolean;
    canUpdate: boolean;
    canDeactivate: boolean;
    canCreateRecord: boolean;
}) {
    const [tab, setTab] = useState<Tab>('informations');
    const [pending, setPending] = useState<Pending>(null);
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState('');
    const confirm = () => {
        if (!pending) return;
        setProcessing(true);
        setError('');
        router.patch(pending.url, pending.data, {
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
    const current =
        records.find(
            (record) =>
                record.academic_year?.is_current && record.status === 'active',
        ) ?? null;
    const activeCount = records.filter(
        (record) => record.status === 'active',
    ).length;
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
                        <>
                            Fiche étudiant · Matricule{' '}
                            <span className="font-mono font-semibold text-foreground">
                                {student.student_number ?? 'non attribué'}
                            </span>
                        </>
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
                                                ? 'Désactiver l’identité'
                                                : 'Réactiver l’identité',
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
                                <p className="mt-0.5 font-mono text-xs text-muted-foreground">
                                    {student.student_number ??
                                        'Matricule non attribué'}
                                </p>
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
                                    />
                                </dl>
                            </div>
                            <div className="grid grid-cols-2 divide-x border-t bg-muted/30 text-center">
                                <div className="p-3">
                                    <p className="font-heading text-xl font-extrabold text-foreground tabular-nums">
                                        {records.length}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        Déclaration
                                        {records.length > 1 ? 's' : ''}
                                    </p>
                                </div>
                                <div className="p-3">
                                    <p className="font-heading text-xl font-extrabold text-success tabular-nums">
                                        {activeCount}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        Active{activeCount > 1 ? 's' : ''}
                                    </p>
                                </div>
                            </div>
                        </section>

                        <section
                            className="surface p-5"
                            aria-labelledby="current-title"
                        >
                            <div className="mb-2 flex items-center justify-between gap-2">
                                <h2
                                    id="current-title"
                                    className="flex items-center gap-2 text-sm font-bold text-foreground"
                                >
                                    <span
                                        className="flex size-8 items-center justify-center rounded-lg bg-accent text-primary"
                                        aria-hidden="true"
                                    >
                                        <CalendarRange className="size-4" />
                                    </span>
                                    Année courante
                                </h2>
                                {current && (
                                    <ToneBadge tone="success">Active</ToneBadge>
                                )}
                            </div>
                            {current ? (
                                <dl className="divide-y">
                                    <SideRow
                                        icon={CalendarDays}
                                        label="Année universitaire"
                                        value={current.academic_year?.name}
                                    />
                                    <SideRow
                                        icon={Building2}
                                        label="Établissement"
                                        value={current.establishment?.name}
                                    />
                                    <SideRow
                                        icon={Layers}
                                        label="Niveau"
                                        value={current.level?.name}
                                    />
                                    <SideRow
                                        icon={BookMarked}
                                        label="Mention"
                                        value={
                                            current.program?.mention?.name ??
                                            'Sans mention'
                                        }
                                    />
                                    <SideRow
                                        icon={Route}
                                        label="Parcours"
                                        value={
                                            current.program?.name ??
                                            'Sans parcours'
                                        }
                                    />
                                </dl>
                            ) : (
                                <p className="rounded-lg border border-dashed bg-muted/30 p-3 text-sm text-muted-foreground">
                                    Aucune déclaration active visible pour
                                    l’année courante.
                                </p>
                            )}
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
                                        {item.key === 'declarations' && (
                                            <span
                                                className={cn(
                                                    'rounded-full px-2 text-xs tabular-nums',
                                                    selected
                                                        ? 'bg-accent text-accent-foreground'
                                                        : 'bg-secondary text-muted-foreground',
                                                )}
                                            >
                                                {records.length}
                                            </span>
                                        )}
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
                                            label="Date de naissance"
                                            value={date(student.birth_date)}
                                        />
                                        <Field
                                            label="Lieu de naissance"
                                            value={student.birth_place}
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
                                            label="Adresse"
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
                                            label="Numéro d’inscription"
                                            value={
                                                student.bac_registration_number
                                            }
                                        />
                                        <Field
                                            label="Série"
                                            value={student.bac_series}
                                        />
                                        <Field
                                            label="Année"
                                            value={student.bac_year}
                                        />
                                        <Field
                                            label="Mention"
                                            value={student.bac_mention}
                                        />
                                    </Section>
                                </div>
                            </div>
                        )}

                        {tab === 'declarations' && (
                            <div
                                role="tabpanel"
                                id="panel-declarations"
                                aria-labelledby="tab-declarations"
                            >
                                <div className="flex flex-wrap items-center justify-between gap-3 p-5 sm:p-6">
                                    <div>
                                        <h2 className="text-lg font-bold text-foreground">
                                            Déclarations annuelles
                                        </h2>
                                        <p className="text-sm text-muted-foreground">
                                            Une déclaration active ne constitue
                                            pas une inscription universitaire.
                                        </p>
                                    </div>
                                    {canCreateRecord && student.is_active && (
                                        <Button asChild>
                                            <Link
                                                href={`/students/${student.uuid}/records/create`}
                                            >
                                                <Plus aria-hidden="true" />
                                                Ajouter une déclaration
                                            </Link>
                                        </Button>
                                    )}
                                </div>
                                {records.length === 0 ? (
                                    <EmptyState
                                        compact
                                        illustration="inbox"
                                        title="Aucune déclaration visible."
                                        description="Les déclarations annuelles de l’établissement apparaîtront ici."
                                        className="border-t"
                                    />
                                ) : (
                                    <div className="overflow-x-auto border-t">
                                        <table className="data-table min-w-[48rem]">
                                            <caption className="sr-only">
                                                Déclarations annuelles de
                                                l’étudiant
                                            </caption>
                                            <thead>
                                                <tr>
                                                    {[
                                                        'Année',
                                                        'Établissement',
                                                        'Niveau',
                                                        'Mention · parcours',
                                                        'Statut',
                                                    ].map((label) => (
                                                        <th
                                                            key={label}
                                                            scope="col"
                                                        >
                                                            {label}
                                                        </th>
                                                    ))}
                                                    <th scope="col">
                                                        <span className="sr-only">
                                                            Actions
                                                        </span>
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {records.map((record) => (
                                                    <tr
                                                        key={record.id}
                                                        className={cn(
                                                            record.academic_year
                                                                ?.is_current &&
                                                                'bg-accent/30',
                                                        )}
                                                    >
                                                        <td>
                                                            <span className="flex items-center gap-2 font-semibold text-foreground">
                                                                {
                                                                    record
                                                                        .academic_year
                                                                        ?.name
                                                                }
                                                                {record
                                                                    .academic_year
                                                                    ?.is_current && (
                                                                    <span className="rounded-full bg-primary px-1.5 py-px text-[10px] font-bold tracking-wide text-primary-foreground uppercase">
                                                                        Courante
                                                                    </span>
                                                                )}
                                                            </span>
                                                            <span className="block text-xs text-muted-foreground">
                                                                le{' '}
                                                                {date(
                                                                    record.declared_at,
                                                                )}{' '}
                                                                par{' '}
                                                                {record
                                                                    .declared_by
                                                                    ?.name ??
                                                                    '—'}
                                                            </span>
                                                        </td>
                                                        <td>
                                                            {
                                                                record
                                                                    .establishment
                                                                    ?.name
                                                            }
                                                        </td>
                                                        <td>
                                                            {record.level?.name}
                                                        </td>
                                                        <td>
                                                            {record.program ? (
                                                                <>
                                                                    <span className="block text-xs text-muted-foreground">
                                                                        {record
                                                                            .program
                                                                            .mention
                                                                            ?.name ??
                                                                            'Sans mention'}
                                                                    </span>
                                                                    {
                                                                        record
                                                                            .program
                                                                            .name
                                                                    }
                                                                </>
                                                            ) : (
                                                                <span className="text-muted-foreground">
                                                                    Sans
                                                                    parcours
                                                                </span>
                                                            )}
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
                                                            <div className="flex items-center justify-end gap-1">
                                                                {record.canUpdate && (
                                                                    <>
                                                                        <Button
                                                                            variant="ghost"
                                                                            size="sm"
                                                                            asChild
                                                                        >
                                                                            <Link
                                                                                href={`/student-records/${record.uuid}/edit`}
                                                                            >
                                                                                <Pencil aria-hidden="true" />
                                                                                Modifier
                                                                            </Link>
                                                                        </Button>
                                                                        <Button
                                                                            variant="ghost"
                                                                            size="sm"
                                                                            onClick={() =>
                                                                                setPending(
                                                                                    {
                                                                                        url: `/student-records/${record.uuid}/status`,
                                                                                        data: {
                                                                                            status:
                                                                                                record.status ===
                                                                                                'active'
                                                                                                    ? 'inactive'
                                                                                                    : 'active',
                                                                                        },
                                                                                        title:
                                                                                            record.status ===
                                                                                            'active'
                                                                                                ? 'Désactiver la déclaration'
                                                                                                : 'Réactiver la déclaration',
                                                                                    },
                                                                                )
                                                                            }
                                                                        >
                                                                            <Power aria-hidden="true" />
                                                                            {record.status ===
                                                                            'active'
                                                                                ? 'Désactiver'
                                                                                : 'Réactiver'}
                                                                        </Button>
                                                                    </>
                                                                )}
                                                                <TrashButton
                                                                    kind="student-records"
                                                                    uuid={
                                                                        record.uuid
                                                                    }
                                                                    label="Déclaration annuelle"
                                                                />
                                                            </div>
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
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
                                Confirmez cette modification. L’identité, les
                                déclarations et leur historique seront
                                conservés. Les statuts de l’identité et des
                                déclarations sont distincts.
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
