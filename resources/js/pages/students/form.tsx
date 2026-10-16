import { Head, Link, useForm } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowLeft,
    ArrowRight,
    BookOpenCheck,
    ClipboardCheck,
    GraduationCap,
    IdCard,
    Info,
    Pencil,
    Phone,
    Save,
    UserPlus,
    UserRound,
    UserRoundPen,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { FormEvent, HTMLInputTypeAttribute } from 'react';
import {
    ErrorSummary,
    FormField,
    FormSection,
} from '@/components/forms/form-kit';
import { Stepper } from '@/components/forms/stepper';
import type { Step, StepState } from '@/components/forms/stepper';
import { PageBody, PageHeader } from '@/components/page-header';
import { formatDay } from '@/components/references/reference-meta';
import RecordFields from '@/components/students/record-fields';
import {
    BAC_SERIES_CHOICES,
    NATIONALITY_CHOICES,
    REPEAT_CODE_CHOICES,
    SEX_CHOICES,
} from '@/components/students/types';
import type {
    Option,
    Options,
    SelectChoice,
    Student,
} from '@/components/students/types';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';

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
type FormStep = Step & {
    /** Server/client error keys shown on this step. */
    keys: string[];
    fields?: Field[];
};
type Data = Record<string, string>;
type Errors = Record<string, string>;

const today = () => new Date().toISOString().slice(0, 10);
const currentYear = new Date().getFullYear();
const RECORD_KEYS = [
    'establishment_id',
    'academic_year_id',
    'level_id',
    'program_id',
    'repeat_code',
    'scholarship_requested_at',
    'scholarship_request_reference',
];

const identityStep: FormStep = {
    id: 'identity',
    title: 'Identité',
    description: 'Nom, naissance, nationalité',
    icon: UserRound,
    keys: [],
    fields: [
        {
            key: 'last_name',
            label: 'Nom',
            required: true,
            max: 150,
            placeholder: 'Ex. : RAKOTO',
            autoComplete: 'family-name',
        },
        {
            key: 'first_names',
            label: 'Prénoms',
            max: 150,
            placeholder: 'Ex. : Jean Aina',
            autoComplete: 'given-name',
            hint: 'Laisser vide si l’étudiant n’a pas de prénom.',
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
            placeholder: 'Ex. : RASOA Marie',
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
const cinStep: FormStep = {
    id: 'cin',
    title: 'Pièce d’identité',
    description: 'CIN et délivrance',
    icon: IdCard,
    keys: [],
    fields: [
        {
            key: 'cin',
            label: 'Numéro CIN',
            max: 100,
            inputMode: 'numeric',
            placeholder: 'Ex. : 413 012 049 888 (12 chiffres)',
            hint: 'Les espaces sont retirés. Un autre format (récépissé…) est accepté mais signalé « non standard ».',
        },
        { key: 'cin_issued_at', label: 'Date de délivrance', type: 'date' },
        {
            key: 'cin_issued_place',
            label: 'Lieu de délivrance',
            max: 255,
            placeholder: 'Ex. : Mahajanga',
        },
    ],
};
const contactStep: FormStep = {
    id: 'contact',
    title: 'Coordonnées',
    description: 'Téléphone, email, adresse',
    icon: Phone,
    keys: [],
    fields: [
        {
            key: 'phone',
            label: 'Téléphone',
            type: 'tel',
            max: 30,
            inputMode: 'tel',
            placeholder: 'Ex. : 034 12 345 67',
            hint: 'Format malgache ; +261 accepté. Un numéro n’appartient qu’à un seul étudiant.',
            autoComplete: 'tel',
        },
        {
            key: 'email',
            label: 'Adresse mail',
            type: 'email',
            max: 255,
            inputMode: 'email',
            placeholder: 'Ex. : prenom.nom@exemple.mg',
            hint: 'Email de contact, distinct du compte de connexion ; unique par étudiant.',
            autoComplete: 'email',
        },
        {
            key: 'address',
            label: 'Adresse exacte',
            max: 2000,
            placeholder: 'Ex. : Lot, quartier, ville',
            autoComplete: 'street-address',
            wide: true,
        },
    ],
};
const bacStep: FormStep = {
    id: 'bac',
    title: 'Baccalauréat',
    description: 'Année et série',
    icon: BookOpenCheck,
    keys: [],
    fields: [
        {
            key: 'bac_year',
            label: 'Année d’obtention',
            type: 'number',
            inputMode: 'numeric',
            placeholder: `Ex. : ${currentYear - 1}`,
            hint: 'Sert à la règle indicative de bourse (année limite de l’année universitaire).',
        },
        {
            key: 'bac_series',
            label: 'Série',
            choices: BAC_SERIES_CHOICES,
            placeholder: 'Choisir la série',
        },
    ],
};
const recordStep: FormStep = {
    id: 'record',
    title: 'Situation de l’année',
    description: 'Année, niveau, parcours',
    icon: GraduationCap,
    keys: RECORD_KEYS,
};
const reviewStep: FormStep = {
    id: 'review',
    title: 'Récapitulatif',
    description: 'Vérifier et enregistrer',
    icon: ClipboardCheck,
    keys: [],
};

const identitySteps = [identityStep, cinStep, contactStep, bacStep];
for (const step of identitySteps)
    step.keys = (step.fields ?? []).map((field) => field.key);

const labels: Record<string, string> = {
    ...Object.fromEntries(
        identitySteps.flatMap((step) =>
            (step.fields ?? []).map((field) => [field.key, field.label]),
        ),
    ),
    establishment_id: 'Établissement',
    academic_year_id: 'Année universitaire',
    level_id: 'Niveau',
    program_id: 'Parcours',
    repeat_code: 'Code de redoublement',
    scholarship_requested_at: 'Date de dépôt de la demande de bourse',
    scholarship_request_reference: 'Référence demande de bourse',
};

/** Same checks as the server for what can be known in the browser; the server stays the authority. */
function validateStep(step: FormStep, data: Data): Errors {
    const errors: Errors = {};
    const required = (key: string) => {
        if (!data[key]?.trim())
            errors[key] =
                `Le champ ${labels[key].toLowerCase()} est obligatoire.`;
    };
    if (step.id === 'identity') {
        ['last_name', 'sex', 'birth_date', 'nationality'].forEach(required);
        if (data.birth_date && data.birth_date > today())
            errors.birth_date = 'La date ne peut pas être future.';
        else if (data.birth_date && data.birth_date < '1900-01-01')
            errors.birth_date =
                'La date est antérieure à 1900 : vérifiez l’année saisie.';
    }
    if (step.id === 'cin') {
        if (data.cin_issued_at && data.cin_issued_at > today())
            errors.cin_issued_at = 'La date ne peut pas être future.';
        else if (
            data.cin_issued_at &&
            data.birth_date &&
            data.cin_issued_at < data.birth_date
        )
            errors.cin_issued_at =
                'La délivrance de la CIN ne peut pas précéder la naissance.';
    }
    if (step.id === 'contact') {
        if (data.phone && !/^\+?[0-9\s().-]+$/.test(data.phone))
            errors.phone =
                'Le téléphone doit contenir des chiffres et éventuellement un + initial.';
        if (data.email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(data.email))
            errors.email = 'L’adresse email est invalide.';
    }
    if (step.id === 'bac' && data.bac_year) {
        const year = Number(data.bac_year);
        if (!Number.isInteger(year) || year < 1900 || year > currentYear)
            errors.bac_year = `L’année doit être comprise entre 1900 et ${currentYear}.`;
    }
    if (step.id === 'record') {
        [
            'establishment_id',
            'academic_year_id',
            'level_id',
            'repeat_code',
        ].forEach(required);
        if (
            data.scholarship_requested_at &&
            !data.scholarship_request_reference.trim()
        )
            errors.scholarship_request_reference =
                'La référence de la demande est obligatoire lorsque la date de dépôt est renseignée.';
        if (
            data.scholarship_requested_at &&
            data.scholarship_requested_at > today()
        )
            errors.scholarship_requested_at =
                'La date ne peut pas être future.';
    }
    return errors;
}

/** Keeps a legacy value outside the template list selectable, so editing never silently drops it. */
const withCurrent = (choices: SelectChoice[], current: string) =>
    current === '' || choices.some((choice) => choice.value === current)
        ? choices
        : [...choices, { value: current, label: `${current} (hors liste)` }];

const choiceLabel = (choices: SelectChoice[], value: string) =>
    choices.find((choice) => choice.value === value)?.label ?? value;
const optionName = (options: Option[], id: string) => {
    const option = options.find((item) => String(item.id) === id);
    return option
        ? `${option.code ? `${option.code} — ` : ''}${option.name}`
        : '';
};

export default function StudentForm({
    student,
    options,
}: {
    student: Student | null;
    options: Options;
}) {
    const editing = student !== null;
    const steps = editing
        ? [...identitySteps, reviewStep]
        : [...identitySteps, recordStep, reviewStep];

    const [initial] = useState<Data>(() => {
        const values: Data = {};
        for (const step of identitySteps)
            for (const field of step.fields ?? []) {
                const value = student?.[field.key];
                values[field.key] =
                    value === null || value === undefined ? '' : String(value);
                if (field.type === 'date')
                    values[field.key] = values[field.key].slice(0, 10);
            }
        if (!student)
            Object.assign(values, {
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
        return values;
    });
    const form = useForm<Data>(initial);
    const errors = form.errors as Errors;
    const [current, setCurrent] = useState(0);
    // Furthest step reached: creating, later steps open only once the previous ones are valid.
    const [reached, setReached] = useState(editing ? steps.length - 1 : 0);
    const panel = useRef<HTMLDivElement>(null);
    const firstRender = useRef(true);

    useEffect(() => {
        if (firstRender.current) {
            firstRender.current = false;
            return;
        }
        panel.current?.focus({ preventScroll: true });
        panel.current?.scrollIntoView({ block: 'start', behavior: 'smooth' });
    }, [current]);

    const stepWithError = (keys: string[]) =>
        steps.findIndex((step) => step.keys.some((key) => keys.includes(key)));
    const changedKeys = Object.keys(form.data).filter(
        (key) => form.data[key] !== initial[key],
    );
    const stepChanged = (index: number) =>
        steps[index].keys.some((key) => changedKeys.includes(key));
    const stateOf = (index: number): StepState => {
        if (steps[index].keys.some((key) => errors[key])) return 'error';
        if (index === current) return 'current';
        // Editing, every step is already filled: only the modified ones are highlighted.
        if (editing) return stepChanged(index) ? 'done' : 'upcoming';
        return index <= reached && index < steps.length - 1
            ? 'done'
            : 'upcoming';
    };

    const check = (index: number) => {
        const found = validateStep(steps[index], form.data);
        form.clearErrors(...steps[index].keys);
        if (Object.keys(found).length > 0) form.setError(found);
        return Object.keys(found).length === 0;
    };
    const open = (index: number) => {
        // Leaving forward checks the step being left; going back never blocks.
        if (index > current && !check(current)) return;
        setCurrent(index);
        setReached((value) => Math.max(value, index));
    };
    const save = () => {
        for (let index = 0; index < steps.length - 1; index++)
            if (!check(index)) {
                setCurrent(index);
                return;
            }
        const visit = {
            preserveScroll: true,
            onError: (serverErrors: Errors) => {
                const index = stepWithError(Object.keys(serverErrors));
                if (index >= 0) setCurrent(index);
            },
        };
        if (student) form.put(`/students/${student.uuid}`, visit);
        else form.post('/students', visit);
    };
    /** Enter key: creating, goes to the next step; editing, saves from any step. */
    const submit = (event: FormEvent) => {
        event.preventDefault();
        if (editing || current === steps.length - 1) save();
        else open(current + 1);
    };

    const step = steps[current];
    const last = current === steps.length - 1;
    const otherStepsWithErrors = steps
        .map((_, index) => index)
        .filter(
            (index) =>
                index !== current &&
                steps[index].keys.some((key) => errors[key]),
        );
    const fullName = student
        ? `${student.last_name} ${student.first_names ?? ''}`.trim()
        : '';
    const cinIsNonStandard =
        form.data.cin.trim() !== '' &&
        !/^\d{12}$/.test(form.data.cin.replace(/[\s.]+/g, ''));

    const renderField = (field: Field) => (
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
                    onValueChange={(value) => {
                        form.setData(field.key, value === 'none' ? '' : value);
                        form.clearErrors(field.key);
                    }}
                >
                    <SelectTrigger
                        id={field.key}
                        className="w-full"
                        aria-invalid={errors[field.key] ? true : undefined}
                        aria-describedby={
                            errors[field.key] ? `${field.key}-error` : undefined
                        }
                    >
                        <SelectValue placeholder={field.placeholder} />
                    </SelectTrigger>
                    <SelectContent>
                        {!field.required && (
                            <SelectItem value="none">Non renseignée</SelectItem>
                        )}
                        {withCurrent(field.choices, form.data[field.key]).map(
                            (choice) => (
                                <SelectItem
                                    key={choice.value}
                                    value={choice.value}
                                >
                                    {choice.label}
                                </SelectItem>
                            ),
                        )}
                    </SelectContent>
                </Select>
            ) : (
                <Input
                    id={field.key}
                    type={field.type ?? 'text'}
                    value={form.data[field.key]}
                    onChange={(event) => {
                        form.setData(field.key, event.target.value);
                        if (errors[field.key]) form.clearErrors(field.key);
                    }}
                    required={field.required}
                    maxLength={field.max}
                    min={
                        field.key === 'bac_year'
                            ? 1900
                            : field.type === 'date'
                              ? '1900-01-01'
                              : undefined
                    }
                    max={
                        field.key === 'bac_year'
                            ? currentYear
                            : field.type === 'date'
                              ? today()
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
    );

    /** Displayed value of a field on the review step. */
    const display = (key: string): string => {
        const value = form.data[key] ?? '';
        if (value === '') return '';
        switch (key) {
            case 'sex':
                return choiceLabel(SEX_CHOICES, value);
            case 'repeat_code':
                return choiceLabel(REPEAT_CODE_CHOICES, value);
            case 'birth_date':
            case 'cin_issued_at':
            case 'scholarship_requested_at':
                return formatDay(value) ?? value;
            case 'establishment_id':
                return optionName(options.establishments, value);
            case 'academic_year_id':
                return optionName(options.academicYears, value);
            case 'level_id':
                return optionName(options.levels, value);
            case 'program_id': {
                const program = options.programs.find(
                    (item) => String(item.id) === value,
                );
                return program
                    ? `${program.mention ? `${program.mention.name} › ` : ''}${program.name}`
                    : '';
            }
            default:
                return value;
        }
    };

    const reviewSection = (item: FormStep, index: number) => {
        const keys =
            item.id === 'record'
                ? RECORD_KEYS
                : (item.fields ?? []).map((field) => field.key);
        return (
            <section
                key={item.id}
                className="rounded-xl border bg-card"
                aria-labelledby={`review-${item.id}`}
            >
                <header className="flex items-center justify-between gap-3 border-b px-4 py-3">
                    <h3
                        id={`review-${item.id}`}
                        className="flex items-center gap-2 text-sm font-bold text-foreground"
                    >
                        <item.icon
                            className="size-4 text-primary"
                            aria-hidden="true"
                        />
                        {item.title}
                    </h3>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() => setCurrent(index)}
                    >
                        <Pencil aria-hidden="true" />
                        Modifier
                        <span className="sr-only"> : {item.title}</span>
                    </Button>
                </header>
                <dl className="grid gap-x-6 gap-y-3 p-4 sm:grid-cols-2">
                    {keys.map((key) => {
                        const value = display(key);
                        const changed =
                            editing && form.data[key] !== initial[key];
                        return (
                            <div key={key} className="min-w-0">
                                <dt className="flex items-center gap-2 text-xs font-medium text-muted-foreground">
                                    {labels[key]}
                                    {changed && (
                                        <Badge
                                            variant="outline"
                                            className="border-info/40 bg-info-soft px-1.5 py-0 text-[10px] text-info-foreground"
                                        >
                                            Modifié
                                        </Badge>
                                    )}
                                </dt>
                                <dd
                                    className={
                                        value
                                            ? 'mt-0.5 text-sm font-medium break-words text-foreground'
                                            : 'mt-0.5 text-sm text-muted-foreground italic'
                                    }
                                >
                                    {value || 'Non renseigné'}
                                    {key === 'cin' && cinIsNonStandard && (
                                        <Badge
                                            variant="outline"
                                            className="ml-2 border-warning/40 bg-warning-soft text-warning-foreground"
                                        >
                                            CIN non standard
                                        </Badge>
                                    )}
                                </dd>
                            </div>
                        );
                    })}
                </dl>
            </section>
        );
    };

    return (
        <>
            <Head
                title={editing ? 'Modifier l’identité' : 'Ajouter un étudiant'}
            />
            <PageBody className="pb-0 md:pb-0 lg:pb-0">
                <PageHeader
                    icon={editing ? UserRoundPen : UserPlus}
                    title={
                        editing
                            ? `Modifier l’identité — ${fullName}`
                            : 'Ajouter un étudiant'
                    }
                    description={
                        editing
                            ? 'Les modifications sont tracées dans l’audit. La situation de l’année se modifie depuis la fiche.'
                            : 'Déclarez, étape par étape, un étudiant déjà régulièrement inscrit dans votre établissement.'
                    }
                />
                <Stepper
                    steps={steps}
                    current={current}
                    stateOf={stateOf}
                    subtitleOf={(index) =>
                        editing && index !== current && stepChanged(index)
                            ? 'Modifiée'
                            : undefined
                    }
                    canOpen={(index) => index <= reached || index < current}
                    onOpen={open}
                />
                <form onSubmit={submit} className="space-y-6" noValidate>
                    <ErrorSummary
                        errors={Object.fromEntries(
                            step.keys.map((key) => [key, errors[key]]),
                        )}
                        labels={labels}
                    />
                    {otherStepsWithErrors.length > 0 && (
                        <div
                            role="alert"
                            className="flex flex-wrap items-center gap-2 rounded-xl border border-danger/30 bg-danger-soft p-3 text-sm text-danger-foreground"
                        >
                            <AlertTriangle
                                className="size-4 shrink-0"
                                aria-hidden="true"
                            />
                            <span>D’autres étapes sont à corriger :</span>
                            {otherStepsWithErrors.map((index) => (
                                <Button
                                    key={steps[index].id}
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    onClick={() => setCurrent(index)}
                                >
                                    {steps[index].title}
                                </Button>
                            ))}
                        </div>
                    )}
                    <div
                        ref={panel}
                        tabIndex={-1}
                        aria-label={`Étape ${current + 1} sur ${steps.length} : ${step.title}`}
                        className="scroll-mt-24 space-y-4 outline-none"
                    >
                        {step.fields && (
                            <FormSection
                                icon={step.icon}
                                title={step.title}
                                description={stepDescriptions[step.id]}
                            >
                                {step.fields.map(renderField)}
                            </FormSection>
                        )}
                        {step.id === 'cin' && cinIsNonStandard && (
                            <p className="flex items-start gap-2 rounded-xl border border-warning/30 bg-warning-soft p-3 text-sm text-warning-foreground">
                                <AlertTriangle
                                    className="mt-0.5 size-4 shrink-0"
                                    aria-hidden="true"
                                />
                                Ce numéro ne comporte pas 12 chiffres : il sera
                                accepté mais signalé « CIN non standard » et ne
                                servira pas à identifier l’étudiant.
                            </p>
                        )}
                        {step.id === 'record' && (
                            <RecordFields
                                options={options}
                                data={form.data}
                                errors={form.errors}
                                setData={(key, value) => {
                                    form.setData(key, value);
                                    form.clearErrors(key);
                                }}
                            />
                        )}
                        {step.id === 'review' && (
                            <FormSection
                                icon={ClipboardCheck}
                                title="Récapitulatif"
                                description={
                                    editing
                                        ? 'Vérifiez les informations ; les champs modifiés sont signalés.'
                                        : 'Vérifiez les informations avant d’ajouter l’étudiant.'
                                }
                                columns={1}
                            >
                                {steps
                                    .slice(0, -1)
                                    .map((item, index) =>
                                        reviewSection(item, index),
                                    )}
                                {!editing && (
                                    <p className="flex items-start gap-2 rounded-xl border border-info/30 bg-info-soft p-3 text-sm text-info-foreground">
                                        <Info
                                            className="mt-0.5 size-4 shrink-0"
                                            aria-hidden="true"
                                        />
                                        L’inscription se fait dans
                                        l’établissement, hors plateforme : cette
                                        fiche déclare un étudiant déjà
                                        régulièrement inscrit.
                                    </p>
                                )}
                            </FormSection>
                        )}
                    </div>

                    <div className="sticky bottom-0 z-20 pt-2 pb-3 md:pb-4">
                        <div className="flex flex-col gap-3 rounded-xl border bg-card/85 px-4 py-3 shadow-elevated backdrop-blur-md supports-[backdrop-filter]:bg-card/75 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                            <div className="flex items-center gap-2">
                                <Button variant="ghost" asChild>
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
                                <p className="hidden text-xs text-muted-foreground sm:block">
                                    {editing
                                        ? changedKeys.length === 0
                                            ? 'Aucune modification pour l’instant.'
                                            : `${changedKeys.length} champ${changedKeys.length > 1 ? 's' : ''} modifié${changedKeys.length > 1 ? 's' : ''} ; enregistrez depuis n’importe quelle étape.`
                                        : 'Les champs marqués * sont obligatoires.'}
                                </p>
                            </div>
                            <div className="flex gap-2 [&>*]:flex-1 sm:[&>*]:flex-none">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setCurrent(current - 1)}
                                    disabled={current === 0}
                                >
                                    <ArrowLeft aria-hidden="true" />
                                    Précédent
                                </Button>
                                {!last && (
                                    <Button
                                        type={editing ? 'button' : 'submit'}
                                        variant={
                                            editing ? 'outline' : 'default'
                                        }
                                        onClick={
                                            editing
                                                ? () => open(current + 1)
                                                : undefined
                                        }
                                    >
                                        {current === steps.length - 2
                                            ? 'Vérifier'
                                            : 'Suivant'}
                                        <ArrowRight aria-hidden="true" />
                                    </Button>
                                )}
                                {(editing || last) && (
                                    <Button
                                        type="submit"
                                        className={
                                            editing
                                                ? 'bg-success text-primary-foreground hover:bg-success/90 focus-visible:ring-success/40'
                                                : undefined
                                        }
                                        disabled={
                                            form.processing ||
                                            (editing &&
                                                changedKeys.length === 0)
                                        }
                                    >
                                        {form.processing ? (
                                            <Spinner />
                                        ) : editing ? (
                                            <Save aria-hidden="true" />
                                        ) : (
                                            <UserPlus aria-hidden="true" />
                                        )}
                                        {form.processing
                                            ? 'Enregistrement…'
                                            : editing
                                              ? 'Mettre à jour'
                                              : 'Ajouter l’étudiant'}
                                    </Button>
                                )}
                            </div>
                        </div>
                    </div>
                </form>
            </PageBody>
        </>
    );
}

const stepDescriptions: Record<string, string> = {
    identity: 'Telle qu’elle figure sur les pièces officielles de l’étudiant.',
    cin: 'Données sensibles : visibles uniquement par le personnel autorisé.',
    contact: 'Pour joindre l’étudiant au sujet de son dossier.',
    bac: 'Informations du diplôme d’accès à l’université.',
};

StudentForm.layout = {
    breadcrumbs: [{ title: 'Étudiants', href: '/students' }],
};
