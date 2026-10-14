import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { ErrorSummary, FormActions } from '@/components/forms/form-kit';
import Heading from '@/components/heading';
import RecordFields from '@/components/students/record-fields';
import type {
    AnnualRecord,
    Options,
    Student,
} from '@/components/students/types';
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
    });
    const submit = (event: FormEvent) => {
        event.preventDefault();
        if (record) form.put(`/student-records/${record.uuid}`);
        else form.post(`/students/${student.uuid}/records`);
    };
    return (
        <div className="space-y-6 p-4 pb-0 md:p-6 md:pb-0">
            <Head title="Déclaration annuelle" />
            <Heading
                title={
                    record
                        ? 'Modifier la déclaration annuelle'
                        : 'Ajouter une déclaration annuelle'
                }
                description={`${student.last_name} ${student.first_names ?? ''} — ${record ? 'l’établissement et l’année ne changent pas ; niveau et parcours peuvent être corrigés.' : 'nouvelle situation d’un étudiant déjà régulièrement inscrit.'}`}
            />
            <form onSubmit={submit} className="space-y-6" noValidate>
                <ErrorSummary
                    errors={form.errors as Record<string, string | undefined>}
                    labels={{
                        establishment_id: 'Établissement',
                        academic_year_id: 'Année universitaire',
                        level_id: 'Niveau',
                        program_id: 'Parcours',
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
                    submitLabel={
                        record
                            ? 'Enregistrer la déclaration'
                            : 'Ajouter la déclaration'
                    }
                    cancelHref={`/students/${student.uuid}`}
                />
            </form>
        </div>
    );
}
AnnualRecordForm.layout = {
    breadcrumbs: [{ title: 'Étudiants', href: '/students' }],
};
