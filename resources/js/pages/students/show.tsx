import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    BadgeCheck,
    CalendarDays,
    IdCard,
    Pencil,
    Plus,
    Power,
    UserRound,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useState } from 'react';
import { TrashButton } from '@/components/trash/trash-button';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
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

/** Label above value, as in the DashWind detail pages; empty values read « Non renseigné ». */
function Field({
    label,
    value,
}: {
    label: string;
    value: string | number | null | undefined;
}) {
    const empty = value === null || value === undefined || value === '';
    return (
        <div className="py-2.5">
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd
                className={`mt-1 text-sm break-words ${empty ? 'text-muted-foreground' : 'font-medium text-foreground'}`}
            >
                {empty ? 'Non renseigné' : value}
            </dd>
        </div>
    );
}

function Section({
    title,
    children,
}: {
    title: string;
    children: React.ReactNode;
}) {
    return (
        <section>
            <h3 className="mb-1 text-xs font-bold tracking-[0.2em] text-muted-foreground uppercase">
                {title}
            </h3>
            <dl className="grid gap-x-12 sm:grid-cols-2">{children}</dl>
        </section>
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

    return (
        <div className="space-y-6 p-4 md:p-6">
            <Head title={fullName} />

            <div className="flex flex-wrap items-end justify-between gap-4">
                <div className="min-w-0">
                    <h1 className="text-2xl leading-tight font-bold tracking-tight text-foreground lg:text-[1.75rem]">
                        Étudiants /{' '}
                        <strong className="font-normal text-primary">
                            {fullName}
                        </strong>
                    </h1>
                    <ul className="mt-2 flex flex-wrap gap-x-5 gap-y-1 text-sm text-muted-foreground">
                        <li>
                            Matricule :{' '}
                            <span className="font-medium text-foreground">
                                {student.student_number ?? 'non attribué'}
                            </span>
                        </li>
                        <li>
                            Identité :{' '}
                            <span className="font-medium text-foreground">
                                {student.is_active ? 'active' : 'inactive'}
                            </span>
                        </li>
                        <li>
                            Compte portail :{' '}
                            <span className="font-medium text-foreground">
                                {portalLinked ? 'rattaché' : 'aucun'}
                            </span>
                        </li>
                    </ul>
                </div>
                <div className="flex flex-wrap gap-2">
                    <TrashButton
                        kind="students"
                        uuid={student.uuid}
                        label={`${student.last_name} ${student.first_names ?? ''}`}
                        redirect="/students"
                    />
                    {canUpdate && (
                        <Button variant="outline" asChild>
                            <Link href={`/students/${student.uuid}/edit`}>
                                <Pencil aria-hidden="true" />
                                Modifier
                            </Link>
                        </Button>
                    )}
                    {canDeactivate && (
                        <Button
                            variant="outline"
                            onClick={() =>
                                setPending({
                                    url: `/students/${student.uuid}/status`,
                                    data: { is_active: !student.is_active },
                                    title: student.is_active
                                        ? 'Désactiver l’identité'
                                        : 'Réactiver l’identité',
                                })
                            }
                        >
                            <Power aria-hidden="true" />
                            {student.is_active ? 'Désactiver' : 'Réactiver'}
                        </Button>
                    )}
                    <Button variant="outline" asChild>
                        <Link href="/students">
                            <ArrowLeft aria-hidden="true" />
                            Retour
                        </Link>
                    </Button>
                </div>
            </div>

            <div className="flex flex-col-reverse overflow-hidden rounded-lg border bg-card xl:flex-row">
                <div className="min-w-0 flex-1">
                    <div
                        role="tablist"
                        aria-label="Sections de la fiche"
                        className="flex gap-6 overflow-x-auto border-b px-5 sm:px-6"
                    >
                        {tabs.map((item) => (
                            <button
                                key={item.key}
                                type="button"
                                role="tab"
                                id={`tab-${item.key}`}
                                aria-selected={tab === item.key}
                                aria-controls={`panel-${item.key}`}
                                onClick={() => setTab(item.key)}
                                className={`relative -mb-px inline-flex shrink-0 items-center gap-2 border-b-2 py-4 font-heading text-sm font-bold transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none ${tab === item.key ? 'border-primary text-primary' : 'border-transparent text-sidebar-foreground hover:text-primary'}`}
                            >
                                <item.icon
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                {item.label}
                                {item.key === 'declarations' && (
                                    <span className="rounded-full bg-muted px-2 text-xs text-muted-foreground">
                                        {records.length}
                                    </span>
                                )}
                            </button>
                        ))}
                    </div>

                    {tab === 'informations' && (
                        <div
                            role="tabpanel"
                            id="panel-informations"
                            aria-labelledby="tab-informations"
                            className="space-y-6 p-5 sm:p-6"
                        >
                            <div>
                                <h2 className="text-xl font-bold text-foreground">
                                    Informations officielles
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    Données déclarées par l’établissement. Les
                                    corrections demandées par l’étudiant passent
                                    par une revue.
                                </p>
                            </div>
                            <Section title="Identité">
                                <Field label="Nom" value={student.last_name} />
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
                            <Section title="Contact">
                                <Field
                                    label="Téléphone"
                                    value={student.phone}
                                />
                                <Field label="Email" value={student.email} />
                                <Field
                                    label="Adresse"
                                    value={student.address}
                                />
                            </Section>
                            <Section title="Pièce d’identité (CIN)">
                                <Field label="Numéro" value={student.cin} />
                                <Field
                                    label="Délivrance"
                                    value={join(
                                        date(student.cin_issued_at),
                                        student.cin_issued_place,
                                    )}
                                />
                            </Section>
                            <Section title="Baccalauréat">
                                <Field
                                    label="Numéro d’inscription"
                                    value={student.bac_registration_number}
                                />
                                <Field
                                    label="Série"
                                    value={student.bac_series}
                                />
                                <Field label="Année" value={student.bac_year} />
                                <Field
                                    label="Mention"
                                    value={student.bac_mention}
                                />
                            </Section>
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
                                    <h2 className="text-xl font-bold text-foreground">
                                        Déclarations annuelles
                                    </h2>
                                    <p className="text-sm text-muted-foreground">
                                        Une déclaration active ne constitue pas
                                        une inscription universitaire.
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
                                <p className="px-6 pb-6 text-sm text-muted-foreground">
                                    Aucune déclaration visible.
                                </p>
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="w-full min-w-[44rem] text-left text-sm">
                                        <caption className="sr-only">
                                            Déclarations annuelles de l’étudiant
                                        </caption>
                                        <thead className="border-y bg-muted/60 text-xs text-muted-foreground">
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
                                                        className="px-6 py-3 font-medium"
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
                                        <tbody className="divide-y">
                                            {records.map((record) => (
                                                <tr
                                                    key={record.id}
                                                    className="hover:bg-muted/40"
                                                >
                                                    <td className="px-6 py-3">
                                                        <span className="font-medium text-foreground">
                                                            {
                                                                record
                                                                    .academic_year
                                                                    ?.name
                                                            }
                                                        </span>
                                                        <span className="block text-xs text-muted-foreground">
                                                            le{' '}
                                                            {date(
                                                                record.declared_at,
                                                            )}{' '}
                                                            par{' '}
                                                            {record.declared_by
                                                                ?.name ?? '—'}
                                                        </span>
                                                    </td>
                                                    <td className="px-6 py-3">
                                                        {
                                                            record.establishment
                                                                ?.name
                                                        }
                                                    </td>
                                                    <td className="px-6 py-3">
                                                        {record.level?.name}
                                                    </td>
                                                    <td className="px-6 py-3">
                                                        {record.program
                                                            ? `${record.program.mention?.name ?? 'Sans mention'} · ${record.program.name}`
                                                            : 'Sans parcours'}
                                                    </td>
                                                    <td className="px-6 py-3">
                                                        <Badge
                                                            variant={
                                                                record.status ===
                                                                'active'
                                                                    ? 'secondary'
                                                                    : 'outline'
                                                            }
                                                        >
                                                            {record.status ===
                                                            'active'
                                                                ? 'Active'
                                                                : 'Inactive'}
                                                        </Badge>
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <TrashButton
                                                            kind="student-records"
                                                            uuid={record.uuid}
                                                            label="Déclaration annuelle"
                                                        />
                                                        {record.canUpdate && (
                                                            <div className="flex justify-end gap-1">
                                                                <Button
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    asChild
                                                                >
                                                                    <Link
                                                                        href={`/student-records/${record.uuid}/edit`}
                                                                    >
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
                                                                    {record.status ===
                                                                    'active'
                                                                        ? 'Désactiver'
                                                                        : 'Réactiver'}
                                                                </Button>
                                                            </div>
                                                        )}
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
                            className="space-y-4 p-5 sm:p-6"
                        >
                            <div>
                                <h2 className="text-xl font-bold text-foreground">
                                    Vérification par l’étudiant
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    L’étudiant confirme, depuis le portail
                                    bourse, que les informations affichées sont
                                    correctes. Ce n’est ni une inscription ni
                                    une validation de dossier.
                                </p>
                            </div>
                            <dl className="grid gap-x-12 sm:grid-cols-2">
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
                                    value={studentConfirmation.academic_year}
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

                <aside className="border-b bg-muted/30 xl:w-80 xl:shrink-0 xl:border-b-0 xl:border-l">
                    <div className="flex flex-col items-center border-b p-6 text-center">
                        <div
                            className="flex size-20 items-center justify-center rounded-full bg-primary font-heading text-2xl font-bold text-primary-foreground"
                            aria-hidden="true"
                        >
                            {initials(student) || <UserRound />}
                        </div>
                        <p className="mt-4 text-lg leading-tight font-bold text-foreground">
                            {fullName}
                        </p>
                        <p className="mt-1 text-sm break-all text-muted-foreground">
                            {student.email ?? 'Email non renseigné'}
                        </p>
                        <div className="mt-3 flex flex-wrap justify-center gap-2">
                            <Badge
                                variant={
                                    student.is_active ? 'secondary' : 'outline'
                                }
                            >
                                {student.is_active
                                    ? 'Identité active'
                                    : 'Identité inactive'}
                            </Badge>
                            <Badge
                                variant={
                                    studentConfirmation.status === 'confirmed'
                                        ? 'secondary'
                                        : 'outline'
                                }
                            >
                                {confirmationLabels[studentConfirmation.status]}
                            </Badge>
                        </div>
                    </div>
                    <div className="grid grid-cols-2 divide-x border-b text-center">
                        <div className="p-4">
                            <p className="text-lg font-bold text-foreground tabular-nums">
                                {records.length}
                            </p>
                            <p className="text-xs text-muted-foreground">
                                Déclaration{records.length > 1 ? 's' : ''}
                            </p>
                        </div>
                        <div className="p-4">
                            <p className="text-lg font-bold text-foreground tabular-nums">
                                {activeCount}
                            </p>
                            <p className="text-xs text-muted-foreground">
                                Active{activeCount > 1 ? 's' : ''}
                            </p>
                        </div>
                    </div>
                    <div className="p-6">
                        <h2 className="mb-1 text-xs font-bold tracking-[0.2em] text-muted-foreground uppercase">
                            Année courante
                        </h2>
                        {current ? (
                            <dl>
                                <Field
                                    label="Année universitaire"
                                    value={current.academic_year?.name}
                                />
                                <Field
                                    label="Établissement"
                                    value={current.establishment?.name}
                                />
                                <Field
                                    label="Niveau"
                                    value={current.level?.name}
                                />
                                <Field
                                    label="Mention"
                                    value={
                                        current.program?.mention?.name ??
                                        'Sans mention'
                                    }
                                />
                                <Field
                                    label="Parcours"
                                    value={
                                        current.program?.name ?? 'Sans parcours'
                                    }
                                />
                            </dl>
                        ) : (
                            <p className="mt-2 text-sm text-muted-foreground">
                                Aucune déclaration active visible pour l’année
                                courante.
                            </p>
                        )}
                    </div>
                </aside>
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
                            déclarations et leur historique seront conservés.
                            Les statuts de l’identité et des déclarations sont
                            distincts.
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
        </div>
    );
}
StudentShow.layout = {
    breadcrumbs: [{ title: 'Étudiants', href: '/students' }],
};
