import { Head, useForm } from '@inertiajs/react';
import {
    BookOpenCheck,
    GraduationCap,
    IdCard,
    Info,
    ListChecks,
    Phone,
    Save,
    UserPlus,
    UserRound,
    UserRoundPen,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { FormEvent, HTMLInputTypeAttribute } from 'react';
import {
    ErrorSummary,
    FormActions,
    FormField,
    FormSection,
} from '@/components/forms/form-kit';
import { PageBody, PageHeader } from '@/components/page-header';
import RecordFields from '@/components/students/record-fields';
import {
    BAC_SERIES_CHOICES,
    NATIONALITY_CHOICES,
    SEX_CHOICES,
} from '@/components/students/types';
import type {
    Options,
    SelectChoice,
    Student,
} from '@/components/students/types';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

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
    /** Closed list of the official template: rendered as a select. */
    choices?: SelectChoice[];
};
type Section = {
    id: string;
    title: string;
    description: string;
    icon: LucideIcon;
    fields: Field[];
};

const currentYear = new Date().getFullYear();
const identity: Section = {
    id: 'section-identity',
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
            key: 'sex',
            label: 'Sexe',
            required: true,
            choices: SEX_CHOICES,
            placeholder: 'Choisir le sexe',
        },
        {
            key: 'birth_date',
            label: 'Date de naissance',
            required: true,
            type: 'date',
        },
        {
            key: 'mother_name',
            label: 'Nom et prénoms de la mère',
            max: 255,
            placeholder: 'RASOA Marie',
        },
        {
            key: 'nationality',
            label: 'Nationalité',
            required: true,
            choices: NATIONALITY_CHOICES,
            placeholder: 'Choisir la nationalité',
        },
    ],
};
const sections: Section[] = [
    {
        id: 'section-cin',
        title: 'Pièce d’identité (CIN)',
        description:
            'Données sensibles : visibles uniquement par le personnel autorisé.',
        icon: IdCard,
        fields: [
            {
                key: 'cin',
                label: 'Numéro CIN',
                max: 100,
                placeholder: '12 chiffres',
                hint: 'Les espaces sont retirés ; un numéro autre que 12 chiffres est accepté mais signalé « non standard ».',
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
        id: 'section-contact',
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
                hint: 'Format malgache ; le préfixe +261 est accepté. Un même numéro ne peut appartenir qu’à un seul étudiant.',
                autoComplete: 'tel',
            },
            {
                key: 'email',
                label: 'Email de contact',
                type: 'email',
                max: 255,
                inputMode: 'email',
                placeholder: 'prenom.nom@exemple.mg',
                hint: 'Email officiel de contact, distinct du compte de connexion ; unique par étudiant.',
                autoComplete: 'email',
            },
            {
                key: 'address',
                label: 'Adresse exacte',
                max: 2000,
                placeholder: 'Lot, quartier, ville',
                autoComplete: 'street-address',
                wide: true,
            },
        ],
    },
    {
        id: 'section-bac',
        title: 'Baccalauréat',
        description: 'Informations du diplôme d’accès à l’université.',
        icon: BookOpenCheck,
        fields: [
            {
                key: 'bac_year',
                label: 'Année d’obtention',
                type: 'number',
                inputMode: 'numeric',
                placeholder: String(currentYear - 1),
            },
            {
                key: 'bac_series',
                label: 'Série',
                choices: BAC_SERIES_CHOICES,
                placeholder: 'Choisir la série',
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
labels.repeat_code = 'Code de redoublement';
labels.scholarship_requested_at = 'Date de dépôt de la demande de bourse';
labels.scholarship_request_reference = 'Référence demande de bourse';

/** Keeps a legacy value outside the template list selectable, so editing never silently drops it. */
const withCurrent = (choices: SelectChoice[], current: string) =>
    current === '' || choices.some((choice) => choice.value === current)
        ? choices
        : [...choices, { value: current, label: `${current} (hors liste)` }];

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
            repeat_code: '',
            scholarship_requested_at: '',
            scholarship_request_reference: '',
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
            key={section.id}
            id={section.id}
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
                    {field.choices ? (
                        <Select
                            value={
                                form.data[field.key] ||
                                (field.required ? undefined : 'none')
                            }
                            onValueChange={(value) =>
                                form.setData(
                                    field.key,
                                    value === 'none' ? '' : value,
                                )
                            }
                        >
                            <SelectTrigger
                                id={field.key}
                                className="w-full"
                                aria-invalid={
                                    errors[field.key] ? true : undefined
                                }
                                aria-describedby={
                                    errors[field.key]
                                        ? `${field.key}-error`
                                        : undefined
                                }
                            >
                                <SelectValue placeholder={field.placeholder} />
                            </SelectTrigger>
                            <SelectContent>
                                {!field.required && (
                                    <SelectItem value="none">
                                        Non renseignée
                                    </SelectItem>
                                )}
                                {withCurrent(
                                    field.choices,
                                    form.data[field.key],
                                ).map((choice) => (
                                    <SelectItem
                                        key={choice.value}
                                        value={choice.value}
                                    >
                                        {choice.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    ) : (
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
                    )}
                </FormField>
            ))}
        </FormSection>
    );

    const summary: { id: string; title: string; icon: LucideIcon }[] = [
        identity,
        ...(student
            ? []
            : [
                  {
                      id: 'section-record',
                      title: 'Déclaration annuelle',
                      icon: GraduationCap,
                  },
              ]),
        ...sections,
    ];
    const fullName = student
        ? `${student.last_name} ${student.first_names ?? ''}`.trim()
        : '';

    return (
        <>
            <Head
                title={student ? 'Modifier l’identité' : 'Ajouter un étudiant'}
            />
            <PageBody className="pb-0 md:pb-0 lg:pb-0">
                <PageHeader
                    icon={student ? UserRoundPen : UserPlus}
                    title={
                        student
                            ? `Modifier l’identité — ${fullName}`
                            : 'Ajouter un étudiant'
                    }
                    description={
                        student
                            ? 'Les modifications sont tracées dans l’audit. La déclaration annuelle se modifie depuis la fiche.'
                            : 'Déclarez un étudiant déjà régulièrement inscrit dans votre établissement : identité, puis situation de l’année.'
                    }
                />
                <div className="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_16rem]">
                    <form
                        onSubmit={submit}
                        className="min-w-0 space-y-6"
                        noValidate
                    >
                        <ErrorSummary errors={errors} labels={labels} />
                        {renderSection(identity)}
                        {!student && (
                            <div id="section-record" className="scroll-mt-24">
                                <RecordFields
                                    options={options}
                                    data={form.data}
                                    errors={form.errors}
                                    setData={(key, value) =>
                                        form.setData(key, value)
                                    }
                                />
                            </div>
                        )}
                        {sections.map(renderSection)}
                        <FormActions
                            processing={form.processing}
                            submitIcon={student ? Save : UserPlus}
                            submitLabel={
                                student
                                    ? 'Enregistrer les modifications'
                                    : 'Ajouter l’étudiant'
                            }
                            cancelHref={
                                student
                                    ? `/students/${student.uuid}`
                                    : '/students'
                            }
                        />
                    </form>
                    <aside className="hidden space-y-4 xl:sticky xl:top-20 xl:block">
                        <nav
                            aria-label="Sections du formulaire"
                            className="surface p-4"
                        >
                            <p className="mb-2 flex items-center gap-2 text-xs font-bold tracking-wide text-muted-foreground uppercase">
                                <ListChecks
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                Sections
                            </p>
                            <ol className="space-y-0.5">
                                {summary.map((item) => (
                                    <li key={item.id}>
                                        <a
                                            href={`#${item.id}`}
                                            className="flex items-center gap-2.5 rounded-lg px-2 py-1.5 text-sm font-medium text-foreground transition-colors hover:bg-accent hover:text-primary focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                        >
                                            <item.icon
                                                className="size-4 text-muted-foreground"
                                                aria-hidden="true"
                                            />
                                            {item.title}
                                        </a>
                                    </li>
                                ))}
                            </ol>
                        </nav>
                        <div className="flex gap-3 rounded-xl border border-info/30 bg-info-soft p-4 text-xs text-info-foreground">
                            <Info
                                className="size-4 shrink-0"
                                aria-hidden="true"
                            />
                            <p>
                                L’inscription se fait dans l’établissement, hors
                                plateforme : cette fiche déclare un étudiant
                                déjà régulièrement inscrit.
                            </p>
                        </div>
                    </aside>
                </div>
            </PageBody>
        </>
    );
}
StudentForm.layout = {
    breadcrumbs: [{ title: 'Étudiants', href: '/students' }],
};
