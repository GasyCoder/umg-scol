import { Head, useForm } from '@inertiajs/react';
import { BookOpenCheck, IdCard, Phone, UserRound } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { FormEvent, HTMLInputTypeAttribute } from 'react';
import {
    ErrorSummary,
    FormActions,
    FormField,
    FormSection,
} from '@/components/forms/form-kit';
import Heading from '@/components/heading';
import RecordFields from '@/components/students/record-fields';
import type { Options, Student } from '@/components/students/types';
import { Input } from '@/components/ui/input';

type FieldKey = Exclude<keyof Student, 'id' | 'uuid' | 'is_active'>;
type Field = {
    key: FieldKey;
    label: string;
    required?: boolean;
    type?: HTMLInputTypeAttribute;
    max?: number;
    placeholder?: string;
    hint?: string;
    autoComplete?: string;
    inputMode?: 'numeric' | 'tel' | 'email' | 'text';
    wide?: boolean;
};
type Section = {
    title: string;
    description: string;
    icon: LucideIcon;
    fields: Field[];
};

const currentYear = new Date().getFullYear();
const identity: Section = {
    title: 'Identité',
    description:
        'Telle qu’elle figure sur les pièces officielles de l’étudiant.',
    icon: UserRound,
    fields: [
        {
            key: 'last_name',
            label: 'Nom',
            required: true,
            max: 150,
            placeholder: 'RAKOTO',
            autoComplete: 'family-name',
        },
        {
            key: 'first_names',
            label: 'Prénoms',
            max: 150,
            placeholder: 'Jean Aina',
            autoComplete: 'given-name',
        },
        {
            key: 'student_number',
            label: 'Matricule',
            max: 100,
            placeholder: 'Attribué par l’établissement',
            hint: 'Identifiant institutionnel ; il doit être unique.',
        },
        {
            key: 'birth_date',
            label: 'Date de naissance',
            type: 'date',
        },
        {
            key: 'birth_place',
            label: 'Lieu de naissance',
            max: 255,
            placeholder: 'Mahajanga',
        },
    ],
};
const sections: Section[] = [
    {
        title: 'Baccalauréat',
        description: 'Informations du diplôme d’accès à l’université.',
        icon: BookOpenCheck,
        fields: [
            {
                key: 'bac_registration_number',
                label: 'Numéro d’inscription BAC',
                max: 100,
                placeholder: 'Numéro figurant sur le relevé',
            },
            {
                key: 'bac_series',
                label: 'Série',
                max: 50,
                placeholder: 'A, C, D…',
            },
            {
                key: 'bac_year',
                label: 'Année d’obtention',
                type: 'number',
                inputMode: 'numeric',
                placeholder: String(currentYear - 1),
            },
            {
                key: 'bac_mention',
                label: 'Mention',
                max: 100,
                placeholder: 'Passable, Assez bien…',
            },
        ],
    },
    {
        title: 'Pièce d’identité (CIN)',
        description:
            'Données sensibles : visibles uniquement par le personnel autorisé.',
        icon: IdCard,
        fields: [
            {
                key: 'cin',
                label: 'Numéro CIN',
                max: 100,
                inputMode: 'numeric',
                placeholder: '12 chiffres',
                hint: 'Les espaces sont retirés automatiquement.',
            },
            {
                key: 'cin_issued_at',
                label: 'Date de délivrance',
                type: 'date',
            },
            {
                key: 'cin_issued_place',
                label: 'Lieu de délivrance',
                max: 255,
                placeholder: 'Mahajanga',
            },
        ],
    },
    {
        title: 'Coordonnées',
        description: 'Pour joindre l’étudiant au sujet de son dossier.',
        icon: Phone,
        fields: [
            {
                key: 'phone',
                label: 'Téléphone',
                type: 'tel',
                max: 30,
                inputMode: 'tel',
                placeholder: '034 12 345 67',
                hint: 'Format malgache ; le préfixe +261 est accepté.',
                autoComplete: 'tel',
            },
            {
                key: 'email',
                label: 'Email de contact',
                type: 'email',
                max: 255,
                inputMode: 'email',
                placeholder: 'prenom.nom@exemple.mg',
                hint: 'Email officiel de contact, distinct du compte de connexion.',
                autoComplete: 'email',
            },
            {
                key: 'address',
                label: 'Adresse',
                max: 2000,
                placeholder: 'Lot, quartier, ville',
                autoComplete: 'street-address',
                wide: true,
            },
        ],
    },
];
const labels = Object.fromEntries(
    [identity, ...sections].flatMap((section) =>
        section.fields.map((field) => [field.key, field.label]),
    ),
);
labels.establishment_id = 'Établissement';
labels.academic_year_id = 'Année universitaire';
labels.level_id = 'Niveau';
labels.program_id = 'Parcours';

export default function StudentForm({
    student,
    options,
}: {
    student: Student | null;
    options: Options;
}) {
    const initial: Record<string, string> = {};
    for (const section of [identity, ...sections])
        for (const field of section.fields) {
            const value = student?.[field.key];
            initial[field.key] =
                value === null || value === undefined ? '' : String(value);
            if (field.type === 'date')
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
    const errors = form.errors as Record<string, string | undefined>;
    const submit = (event: FormEvent) => {
        event.preventDefault();
        if (student) form.put(`/students/${student.uuid}`);
        else form.post('/students');
    };
    const renderSection = (section: Section) => (
        <FormSection
            key={section.title}
            icon={section.icon}
            title={section.title}
            description={section.description}
        >
            {section.fields.map((field) => (
                <FormField
                    key={field.key}
                    id={field.key}
                    label={field.label}
                    required={field.required}
                    hint={field.hint}
                    error={errors[field.key]}
                    wide={field.wide}
                >
                    <Input
                        id={field.key}
                        type={field.type ?? 'text'}
                        value={form.data[field.key]}
                        onChange={(event) =>
                            form.setData(field.key, event.target.value)
                        }
                        required={field.required}
                        maxLength={field.max}
                        min={
                            field.key === 'bac_year'
                                ? 1950
                                : field.type === 'date'
                                  ? '1900-01-01'
                                  : undefined
                        }
                        max={
                            field.key === 'bac_year'
                                ? currentYear
                                : field.key === 'birth_date'
                                  ? new Date().toISOString().slice(0, 10)
                                  : undefined
                        }
                        placeholder={field.placeholder}
                        autoComplete={field.autoComplete ?? 'off'}
                        inputMode={field.inputMode}
                        aria-invalid={errors[field.key] ? true : undefined}
                        aria-describedby={
                            errors[field.key]
                                ? `${field.key}-error`
                                : field.hint
                                  ? `${field.key}-hint`
                                  : undefined
                        }
                    />
                </FormField>
            ))}
        </FormSection>
    );

    return (
        <div className="space-y-6 p-4 pb-0 md:p-6 md:pb-0">
            <Head
                title={student ? 'Modifier l’identité' : 'Ajouter un étudiant'}
            />
            <Heading
                title={
                    student
                        ? `Modifier l’identité — ${student.last_name} ${student.first_names ?? ''}`.trim()
                        : 'Ajouter un étudiant'
                }
                description={
                    student
                        ? 'Les modifications sont tracées dans l’audit. La déclaration annuelle se modifie depuis la fiche.'
                        : 'Déclarez un étudiant déjà régulièrement inscrit dans votre établissement : identité, puis situation de l’année.'
                }
            />
            <form onSubmit={submit} className="space-y-6" noValidate>
                <ErrorSummary errors={errors} labels={labels} />
                {renderSection(identity)}
                {!student && (
                    <RecordFields
                        options={options}
                        data={form.data}
                        errors={form.errors}
                        setData={(key, value) => form.setData(key, value)}
                    />
                )}
                {sections.map(renderSection)}
                <FormActions
                    processing={form.processing}
                    submitLabel={
                        student
                            ? 'Enregistrer les modifications'
                            : 'Ajouter l’étudiant'
                    }
                    cancelHref={
                        student ? `/students/${student.uuid}` : '/students'
                    }
                />
            </form>
        </div>
    );
}
StudentForm.layout = {
    breadcrumbs: [{ title: 'Étudiants', href: '/students' }],
};
