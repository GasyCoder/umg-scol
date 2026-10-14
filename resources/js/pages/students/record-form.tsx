import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
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
    student: Pick<Student, 'id' | 'last_name' | 'first_names'>;
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
        if (record) form.put(`/student-records/${record.id}`);
        else form.post(`/students/${student.id}/records`);
    };
    return (
        <div className="mx-auto w-full max-w-3xl space-y-6 p-4 md:p-6">
            <Head title="Déclaration annuelle" />
            <Heading
                title={
                    record
                        ? 'Modifier la déclaration annuelle'
                        : 'Ajouter une déclaration annuelle'
                }
                description={`${student.last_name} ${student.first_names ?? ''}`}
            />
            <form onSubmit={submit} className="space-y-5">
                <RecordFields
                    options={options}
                    data={form.data}
                    errors={form.errors}
                    setData={(key, value) => form.setData(key, value)}
                    immutable={record !== null}
                />
                <div className="flex gap-3">
                    <Button disabled={form.processing}>Enregistrer</Button>
                    <Button variant="outline" asChild>
                        <Link href={`/students/${student.id}`}>Annuler</Link>
                    </Button>
                </div>
            </form>
        </div>
    );
}
AnnualRecordForm.layout = {
    breadcrumbs: [{ title: 'Étudiants', href: '/students' }],
};
