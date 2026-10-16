import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, CalendarCog, CalendarPlus, Save } from 'lucide-react';
import type { FormEvent } from 'react';
import { ErrorSummary, FormActions } from '@/components/forms/form-kit';
import { PageBody, PageHeader } from '@/components/page-header';
import RecordFields from '@/components/students/record-fields';
import type {
    AnnualRecord,
    Options,
    Student,
} from '@/components/students/types';

const initials = (lastName: string, firstNames: string | null) =>
    [lastName, firstNames]
        .filter(Boolean)
        .map((part) => String(part).trim().charAt(0))
        .join('')
        .slice(0, 2)
        .toUpperCase();

export default function AnnualRecordForm({
    student,
    record,
    options,
}: {
    student: Pick<Student, 'id' | 'uuid' | 'last_name' | 'first_names'>;
    record: AnnualRecord | null;
    options: Options;
}) {
    const form = useForm<Record<string, string>>({
        establishment_id: String(
            record?.establishment_id ?? options.fixedEstablishmentId ?? '',
        ),
        academic_year_id: String(
            record?.academic_year_id ??
                options.academicYears.find(
                    (year) => year.is_current && year.is_active,
                )?.id ??
                '',
        ),
        level_id: String(record?.level_id ?? ''),
        program_id: String(record?.program_id ?? ''),
        repeat_code: record?.repeat_code ?? '',
        scholarship_requested_at:
            record?.scholarship_requested_at?.slice(0, 10) ?? '',
        scholarship_request_reference:
            record?.scholarship_request_reference ?? '',
    });
    const submit = (event: FormEvent) => {
        event.preventDefault();
        if (record) form.put(`/student-records/${record.uuid}`);
        else form.post(`/students/${student.uuid}/records`);
    };
    const fullName = `${student.last_name} ${student.first_names ?? ''}`.trim();
    return (
        <>
            <Head title="Situation de l’année" />
            <PageBody className="pb-0 md:pb-0 lg:pb-0">
                <PageHeader
                    icon={record ? CalendarCog : CalendarPlus}
                    title={
                        record
                            ? 'Modifier la situation de l’année'
                            : 'Ajouter la situation de l’année'
                    }
                    description={`${fullName}. ${record ? 'L’établissement et l’année ne changent pas ; niveau, parcours, code de redoublement et demande de bourse peuvent être corrigés.' : 'Nouvelle situation d’un étudiant déjà régulièrement inscrit.'}`}
                />
                <div className="space-y-6">
                    <Link
                        href={`/students/${student.uuid}`}
                        className="surface group flex items-center gap-3 p-3 pr-4 transition-all hover:border-primary/40 hover:shadow-elevated focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    >
                        <span
                            className="flex size-11 shrink-0 items-center justify-center rounded-xl bg-primary font-heading text-sm font-extrabold text-primary-foreground"
                            aria-hidden="true"
                        >
                            {initials(student.last_name, student.first_names)}
                        </span>
                        <span className="min-w-0 flex-1">
                            <span className="block text-xs text-muted-foreground">
                                Étudiant concerné
                            </span>
                            <span className="block truncate font-semibold text-foreground">
                                {fullName}
                            </span>
                        </span>
                        <span className="inline-flex items-center gap-1 text-sm font-medium text-muted-foreground transition-colors group-hover:text-primary">
                            <ArrowLeft className="size-4" aria-hidden="true" />
                            <span className="hidden sm:inline">
                                Retour à la fiche
                            </span>
                        </span>
                    </Link>
                    <form onSubmit={submit} className="space-y-6" noValidate>
                        <ErrorSummary
                            errors={
                                form.errors as Record<
                                    string,
                                    string | undefined
                                >
                            }
                            labels={{
                                establishment_id: 'Établissement',
                                academic_year_id: 'Année universitaire',
                                level_id: 'Niveau',
                                program_id: 'Parcours',
                                repeat_code: 'Code de redoublement',
                                scholarship_requested_at:
                                    'Date de dépôt de la demande de bourse',
                                scholarship_request_reference:
                                    'Référence demande de bourse',
                            }}
                        />
                        <RecordFields
                            options={options}
                            data={form.data}
                            errors={form.errors}
                            setData={(key, value) => form.setData(key, value)}
                            immutable={record !== null}
                        />
                        <FormActions
                            processing={form.processing}
                            submitIcon={record ? Save : CalendarPlus}
                            submitLabel={
                                record
                                    ? 'Enregistrer la situation'
                                    : 'Ajouter la situation'
                            }
                            cancelHref={`/students/${student.uuid}`}
                        />
                    </form>
                </div>
            </PageBody>
        </>
    );
}
AnnualRecordForm.layout = {
    breadcrumbs: [{ title: 'Étudiants', href: '/students' }],
};
