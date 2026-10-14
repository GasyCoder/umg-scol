import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
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
import { identitySections } from '@/components/students/types';
import type { AnnualRecord, Student } from '@/components/students/types';
export default function StudentShow({
    student,
    records,
    canUpdate,
    canDeactivate,
    canCreateRecord,
}: {
    student: Student;
    records: AnnualRecord[];
    canUpdate: boolean;
    canDeactivate: boolean;
    canCreateRecord: boolean;
}) {
    const [confirmation, setConfirmation] = useState<{
        url: string;
        data: Record<string, string | boolean>;
        title: string;
    } | null>(null);
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState('');
    const confirm = () => {
        if (!confirmation) return;
        setProcessing(true);
        setError('');
        router.patch(confirmation.url, confirmation.data, {
            preserveScroll: true,
            onSuccess: () => setConfirmation(null),
            onError: (errors) =>
                setError(
                    Object.values(errors)[0] ?? 'La modification a échoué.',
                ),
            onFinish: () => setProcessing(false),
        });
    };
    return (
        <div className="space-y-6 p-4 md:p-6">
            <Head title={`${student.last_name} ${student.first_names ?? ''}`} />
            <div className="flex flex-wrap justify-between gap-4">
                <Heading
                    title={`${student.last_name} ${student.first_names ?? ''}`}
                    description="Fiche d’un étudiant déjà régulièrement inscrit, déclarée par son établissement."
                />
                <div className="flex flex-wrap gap-2">
                    <Badge
                        variant={student.is_active ? 'secondary' : 'outline'}
                    >
                        {student.is_active
                            ? 'Identité active'
                            : 'Identité inactive'}
                    </Badge>
                    {canUpdate && (
                        <Button variant="outline" asChild>
                            <Link href={`/students/${student.id}/edit`}>
                                Modifier l’identité
                            </Link>
                        </Button>
                    )}
                    {canDeactivate && (
                        <Button
                            variant="outline"
                            onClick={() =>
                                setConfirmation({
                                    url: `/students/${student.id}/status`,
                                    data: { is_active: !student.is_active },
                                    title: student.is_active
                                        ? 'Désactiver l’identité'
                                        : 'Réactiver l’identité',
                                })
                            }
                        >
                            {student.is_active ? 'Désactiver' : 'Réactiver'}
                        </Button>
                    )}
                </div>
            </div>
            <div className="grid gap-5 lg:grid-cols-2">
                {identitySections.map((section) => (
                    <section
                        key={section.title}
                        className="rounded-lg border p-5"
                    >
                        <h2 className="mb-4 font-semibold">{section.title}</h2>
                        <dl className="grid gap-3">
                            {section.fields.map((field) => {
                                const value = student[field.key];
                                return (
                                    <div
                                        className="grid gap-1 sm:grid-cols-2"
                                        key={field.key}
                                    >
                                        <dt className="text-sm text-muted-foreground">
                                            {field.label}
                                        </dt>
                                        <dd className="text-sm break-words">
                                            {value === null ||
                                            value === undefined ||
                                            value === ''
                                                ? '—'
                                                : 'type' in field &&
                                                    field.type === 'date'
                                                  ? String(value).slice(0, 10)
                                                  : String(value)}
                                        </dd>
                                    </div>
                                );
                            })}
                        </dl>
                    </section>
                ))}
            </div>
            <section className="space-y-4">
                <div className="flex flex-wrap justify-between gap-3">
                    <h2 className="text-lg font-semibold">
                        Historique des déclarations annuelles
                    </h2>
                    {canCreateRecord && student.is_active && (
                        <Button asChild>
                            <Link
                                href={`/students/${student.id}/records/create`}
                            >
                                Ajouter une déclaration annuelle
                            </Link>
                        </Button>
                    )}
                </div>
                <p className="text-sm text-muted-foreground">
                    Une déclaration active signifie que l’établissement la
                    maintient valide dans cette plateforme. Cela ne constitue
                    pas une inscription universitaire.
                </p>
                {records.length === 0 ? (
                    <p>Aucune déclaration visible.</p>
                ) : (
                    records.map((record) => (
                        <article
                            key={record.id}
                            className="flex flex-wrap justify-between gap-4 rounded-lg border p-5"
                        >
                            <div className="space-y-2">
                                <h3 className="font-medium">
                                    {record.academic_year?.name} —{' '}
                                    {record.establishment?.name}
                                </h3>
                                <p className="text-sm">
                                    {record.level?.name}
                                    {record.program
                                        ? ` · ${record.program.name}`
                                        : ''}
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    Déclaré le {record.declared_at.slice(0, 10)}{' '}
                                    par {record.declared_by?.name ?? '—'}
                                </p>
                                <Badge
                                    variant={
                                        record.status === 'active'
                                            ? 'secondary'
                                            : 'outline'
                                    }
                                >
                                    {record.status === 'active'
                                        ? 'Déclaration active'
                                        : 'Déclaration inactive'}
                                </Badge>
                            </div>
                            {record.canUpdate && (
                                <div className="flex items-start gap-2">
                                    <Button variant="outline" asChild>
                                        <Link
                                            href={`/student-records/${record.id}/edit`}
                                        >
                                            Modifier la déclaration
                                        </Link>
                                    </Button>
                                    <Button
                                        variant="outline"
                                        onClick={() =>
                                            setConfirmation({
                                                url: `/student-records/${record.id}/status`,
                                                data: {
                                                    status:
                                                        record.status ===
                                                        'active'
                                                            ? 'inactive'
                                                            : 'active',
                                                },
                                                title:
                                                    record.status === 'active'
                                                        ? 'Désactiver la déclaration'
                                                        : 'Réactiver la déclaration',
                                            })
                                        }
                                    >
                                        {record.status === 'active'
                                            ? 'Désactiver'
                                            : 'Réactiver'}
                                    </Button>
                                </div>
                            )}
                        </article>
                    ))
                )}
            </section>
            <Button variant="outline" asChild>
                <Link href="/students">Retour à la liste</Link>
            </Button>
            <Dialog
                open={confirmation !== null}
                onOpenChange={(open) => {
                    if (!open && !processing) setConfirmation(null);
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{confirmation?.title}</DialogTitle>
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
                            onClick={() => setConfirmation(null)}
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
