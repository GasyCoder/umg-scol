import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    BadgeCheck,
    CalendarDays,
    GraduationCap,
    Pencil,
    Plus,
    Power,
    UserRound,
} from 'lucide-react';
import { useState } from 'react';
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

const confirmationLabels: Record<StudentConfirmation['status'], string> = {
    never_confirmed: 'Jamais confirmée',
    confirmed: 'Confirmée',
    stale: 'À confirmer de nouveau',
};
const date = (value: string | null | undefined) =>
    value ? new Date(value).toLocaleDateString('fr-FR') : null;
const initials = (student: Student) =>
    [student.last_name, student.first_names]
        .filter(Boolean)
        .map((part) => String(part).trim().charAt(0))
        .join('')
        .slice(0, 2)
        .toUpperCase();

/** Compact label/value pair; empty values read « Non renseigné ». */
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
        <div className={wide ? 'sm:col-span-2' : undefined}>
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd
                className={`mt-0.5 text-sm break-words ${empty ? 'text-muted-foreground' : 'font-medium text-foreground'}`}
            >
                {empty ? 'Non renseigné' : value}
            </dd>
        </div>
    );
}

function Group({
    title,
    children,
}: {
    title: string;
    children: React.ReactNode;
}) {
    return (
        <section className="space-y-3">
            <h3 className="text-xs font-bold tracking-wider text-muted-foreground uppercase">
                {title}
            </h3>
            <dl className="grid gap-x-6 gap-y-3 sm:grid-cols-2">{children}</dl>
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
    const birth = [date(student.birth_date), student.birth_place]
        .filter(Boolean)
        .join(' à ');

    return (
        <div className="space-y-6 p-4 md:p-6">
            <Head title={fullName} />
            <div className="flex flex-wrap items-center justify-between gap-3">
                <Button variant="ghost" size="sm" asChild>
                    <Link href="/students">
                        <ArrowLeft aria-hidden="true" />
                        Étudiants
                    </Link>
                </Button>
                <div className="flex flex-wrap gap-2">
                    {canUpdate && (
                        <Button variant="outline" size="sm" asChild>
                            <Link href={`/students/${student.uuid}/edit`}>
                                <Pencil aria-hidden="true" />
                                Modifier l’identité
                            </Link>
                        </Button>
                    )}
                    {canDeactivate && (
                        <Button
                            variant="outline"
                            size="sm"
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
                </div>
            </div>

            <div className="grid gap-6 lg:grid-cols-3">
                <aside className="min-w-0 space-y-4 lg:col-span-1">
                    <section className="rounded-lg border bg-card p-6 text-center">
                        <div
                            className="mx-auto flex size-20 items-center justify-center rounded-full bg-accent font-heading text-2xl font-bold text-primary"
                            aria-hidden="true"
                        >
                            {initials(student) || <UserRound />}
                        </div>
                        <h1 className="mt-4 text-lg leading-tight font-bold text-foreground">
                            {fullName}
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {student.student_number
                                ? `Matricule ${student.student_number}`
                                : 'Sans matricule'}
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
                            <Badge variant="outline">
                                {portalLinked
                                    ? 'Compte portail rattaché'
                                    : 'Sans compte portail'}
                            </Badge>
                        </div>
                    </section>

                    <section className="rounded-lg border bg-card p-5">
                        <h2 className="flex items-center gap-2 text-sm font-bold text-foreground">
                            <GraduationCap
                                className="size-4 text-primary"
                                aria-hidden="true"
                            />
                            Situation de l’année courante
                        </h2>
                        {current ? (
                            <dl className="mt-3 grid gap-3">
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
                            <p className="mt-3 text-sm text-muted-foreground">
                                Aucune déclaration active visible pour l’année
                                courante.
                            </p>
                        )}
                    </section>

                    <section className="rounded-lg border bg-card p-5">
                        <h2 className="flex items-center gap-2 text-sm font-bold text-foreground">
                            <BadgeCheck
                                className="size-4 text-primary"
                                aria-hidden="true"
                            />
                            Vérification par l’étudiant
                        </h2>
                        <div className="mt-3 flex flex-wrap items-center gap-2">
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
                        {studentConfirmation.confirmed_at && (
                            <p className="mt-2 text-xs text-muted-foreground">
                                {studentConfirmation.status === 'confirmed'
                                    ? 'Confirmée'
                                    : 'Dernière confirmation'}{' '}
                                le {date(studentConfirmation.confirmed_at)}
                                {studentConfirmation.academic_year &&
                                    ` (année ${studentConfirmation.academic_year})`}
                            </p>
                        )}
                    </section>
                </aside>

                <div className="min-w-0 space-y-6 lg:col-span-2">
                    <section className="space-y-6 rounded-lg border bg-card p-6">
                        <h2 className="text-base font-bold text-foreground">
                            Informations officielles
                        </h2>
                        <Group title="Identité">
                            <Field label="Nom" value={student.last_name} />
                            <Field
                                label="Prénoms"
                                value={student.first_names}
                            />
                            <Field label="Naissance" value={birth} wide />
                        </Group>
                        <div className="border-t" />
                        <Group title="Contact">
                            <Field label="Téléphone" value={student.phone} />
                            <Field label="Email" value={student.email} />
                            <Field
                                label="Adresse"
                                value={student.address}
                                wide
                            />
                        </Group>
                        <div className="border-t" />
                        <Group title="Pièce d’identité (CIN)">
                            <Field label="Numéro" value={student.cin} />
                            <Field
                                label="Délivrance"
                                value={[
                                    date(student.cin_issued_at),
                                    student.cin_issued_place,
                                ]
                                    .filter(Boolean)
                                    .join(' à ')}
                            />
                        </Group>
                        <div className="border-t" />
                        <Group title="Baccalauréat">
                            <Field
                                label="Numéro d’inscription"
                                value={student.bac_registration_number}
                            />
                            <Field label="Série" value={student.bac_series} />
                            <Field label="Année" value={student.bac_year} />
                            <Field
                                label="Mention"
                                value={student.bac_mention}
                            />
                        </Group>
                    </section>

                    <section className="rounded-lg border bg-card">
                        <div className="flex flex-wrap items-center justify-between gap-3 border-b p-5">
                            <div>
                                <h2 className="flex items-center gap-2 text-base font-bold text-foreground">
                                    <CalendarDays
                                        className="size-4 text-primary"
                                        aria-hidden="true"
                                    />
                                    Déclarations annuelles
                                </h2>
                                <p className="text-xs text-muted-foreground">
                                    Une déclaration active ne constitue pas une
                                    inscription universitaire.
                                </p>
                            </div>
                            {canCreateRecord && student.is_active && (
                                <Button size="sm" asChild>
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
                            <p className="p-5 text-sm text-muted-foreground">
                                Aucune déclaration visible.
                            </p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[40rem] text-left text-sm">
                                    <caption className="sr-only">
                                        Déclarations annuelles de l’étudiant
                                    </caption>
                                    <thead className="text-xs text-muted-foreground">
                                        <tr className="border-b">
                                            {[
                                                'Année',
                                                'Établissement',
                                                'Niveau et parcours',
                                                'Statut',
                                                '',
                                            ].map((label, index) => (
                                                <th
                                                    key={index}
                                                    scope="col"
                                                    className="px-5 py-3 font-medium"
                                                >
                                                    {label || (
                                                        <span className="sr-only">
                                                            Actions
                                                        </span>
                                                    )}
                                                </th>
                                            ))}
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {records.map((record) => (
                                            <tr key={record.id}>
                                                <td className="px-5 py-3 font-medium">
                                                    {record.academic_year?.name}
                                                    <span className="block text-xs font-normal text-muted-foreground">
                                                        le{' '}
                                                        {date(
                                                            record.declared_at,
                                                        )}{' '}
                                                        par{' '}
                                                        {record.declared_by
                                                            ?.name ?? '—'}
                                                    </span>
                                                </td>
                                                <td className="px-5 py-3">
                                                    {record.establishment?.name}
                                                </td>
                                                <td className="px-5 py-3">
                                                    {record.level?.name}
                                                    <span className="block text-xs text-muted-foreground">
                                                        {record.program
                                                            ? `${record.program.mention?.name ?? 'Sans mention'} · ${record.program.name}`
                                                            : 'Sans parcours'}
                                                    </span>
                                                </td>
                                                <td className="px-5 py-3">
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
                                                <td className="px-5 py-3">
                                                    {record.canUpdate && (
                                                        <div className="flex justify-end gap-1">
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                                asChild
                                                            >
                                                                <Link
                                                                    href={`/student-records/${record.id}/edit`}
                                                                >
                                                                    Modifier
                                                                </Link>
                                                            </Button>
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                                onClick={() =>
                                                                    setPending({
                                                                        url: `/student-records/${record.id}/status`,
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
                                                                    })
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
                    </section>
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
