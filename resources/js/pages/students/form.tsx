import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import RecordFields from '@/components/students/record-fields';
import { identitySections } from '@/components/students/types';
import type { Student, Options } from '@/components/students/types';
export default function StudentForm({
    student,
    options,
}: {
    student: Student | null;
    options: Options;
}) {
    const initial: Record<string, string> = {};
    for (const section of identitySections)
        for (const field of section.fields) {
            const value = student?.[field.key];
            initial[field.key] =
                value === null || value === undefined ? '' : String(value);
            if (field.key === 'birth_date' || field.key === 'cin_issued_at')
                initial[field.key] = initial[field.key].slice(0, 10);
        }
    if (!student)
        Object.assign(initial, {
            establishment_id: String(options.fixedEstablishmentId ?? ''),
            academic_year_id: String(
                options.academicYears.find(
                    (year) => year.is_current && year.is_active,
                )?.id ?? '',
            ),
            level_id: '',
            program_id: '',
        });
    const form = useForm(initial);
    const submit = (event: FormEvent) => {
        event.preventDefault();
        if (student) form.put(`/students/${student.uuid}`);
        else form.post('/students');
    };
    return (
        <div className="space-y-6 p-4 md:p-6">
            <Head
                title={student ? 'Modifier l’identité' : 'Ajouter un étudiant'}
            />
            <Heading
                title={student ? 'Modifier l’identité' : 'Ajouter un étudiant'}
                description="Étudiants déjà régulièrement inscrits dans leur établissement."
            />
            <form onSubmit={submit} className="space-y-6">
                {identitySections.map((section) => (
                    <fieldset
                        className="rounded-lg border p-5"
                        key={section.title}
                    >
                        <legend className="px-2 font-semibold">
                            {section.title}
                        </legend>
                        <div className="grid gap-4 sm:grid-cols-2">
                            {section.fields.map((field) => (
                                <div className="grid gap-2" key={field.key}>
                                    <Label htmlFor={field.key}>
                                        {field.label}
                                        {'required' in field && field.required
                                            ? ' *'
                                            : ''}
                                    </Label>
                                    <Input
                                        id={field.key}
                                        type={
                                            'type' in field
                                                ? field.type
                                                : 'text'
                                        }
                                        value={form.data[field.key]}
                                        onChange={(event) =>
                                            form.setData(
                                                field.key,
                                                event.target.value,
                                            )
                                        }
                                        required={
                                            'required' in field &&
                                            field.required
                                        }
                                        maxLength={
                                            'max' in field
                                                ? field.max
                                                : undefined
                                        }
                                    />
                                    <InputError
                                        message={form.errors[field.key]}
                                    />
                                </div>
                            ))}
                        </div>
                    </fieldset>
                ))}
                {!student && (
                    <RecordFields
                        options={options}
                        data={form.data}
                        errors={form.errors}
                        setData={(key, value) => form.setData(key, value)}
                    />
                )}
                <div className="flex gap-3">
                    <Button disabled={form.processing}>
                        {form.processing ? 'Enregistrement…' : 'Enregistrer'}
                    </Button>
                    <Button variant="outline" asChild>
                        <Link
                            href={
                                student
                                    ? `/students/${student.uuid}`
                                    : '/students'
                            }
                        >
                            Annuler
                        </Link>
                    </Button>
                </div>
            </form>
        </div>
    );
}
StudentForm.layout = {
    breadcrumbs: [{ title: 'Étudiants', href: '/students' }],
};
