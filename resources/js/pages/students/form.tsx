import { Head, Link, useForm, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowLeft,
    ArrowRight,
    BookOpenCheck,
    Check,
    CircleAlert,
    ClipboardCheck,
    GraduationCap,
    History,
    IdCard,
    Pencil,
    Phone,
    RotateCcw,
    Save,
    UserPlus,
    UserRound,
    UserRoundPen,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { FormEvent, HTMLInputTypeAttribute } from 'react';
import { ErrorSummary, FormField } from '@/components/forms/form-kit';
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
import { ToneBadge } from '@/components/tone-badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';

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
/** Optional identity fields counted as « vides » on the review (PNES identifiers excluded: holders only). */
const REVIEW_OPTIONAL = [
    'first_names',
    'mother_name',
    'cin',
    'cin_issued_at',
    'cin_issued_place',
    'phone',
    'email',
    'address',
    'bac_year',
    'bac_series',
];
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
    description: 'CIN, code bourse',
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
        {
            key: 'scholarship_code',
            label: 'Code bourse',
            max: 30,
            hint: 'Code de la base PNES, pour un étudiant boursier.',
        },
    ],
};
/** PNES identifiers entered by the central office only (never shown to establishments). */
const pnesFields: Field[] = [
    {
        key: 'uin',
        label: 'UIN',
        max: 30,
        hint: 'Identifiant PNES. Visible par la Scolarité Centrale uniquement.',
    },
    {
        key: 'bank_account',
        label: 'Numéro de compte',
        max: 34,
        hint: 'Compte bancaire du boursier. Visible par la Scolarité Centrale uniquement.',
    },
];
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

/** Identity steps; the central office also enters the PNES identifiers. */
const identityStepsFor = (pnes: boolean): FormStep[] =>
    [
        identityStep,
        pnes
            ? { ...cinStep, fields: [...(cinStep.fields ?? []), ...pnesFields] }
            : cinStep,
        contactStep,
        bacStep,
    ].map((step) => ({
        ...step,
        keys: (step.fields ?? []).map((field) => field.key),
    }));

const labels: Record<string, string> = {
    ...Object.fromEntries(
        identityStepsFor(true).flatMap((step) =>
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
        // Spaces and dots are removed by the server, as for the CIN.
        const compact = (key: string) =>
            (data[key] ?? '').replace(/[\s.]+/g, '');
        if (
            compact('scholarship_code') &&
            !/^[0-9A-Za-z-]+$/.test(compact('scholarship_code'))
        )
            errors.scholarship_code =
                'Le code bourse ne doit contenir que des chiffres, des lettres et des tirets.';
        if (compact('uin') && !/^[0-9A-Za-z]+$/.test(compact('uin')))
            errors.uin =
                'L’UIN ne doit contenir que des chiffres et des lettres.';
        if (
            compact('bank_account') &&
            !/^[0-9A-Za-z]+$/.test(compact('bank_account'))
        )
            errors.bank_account =
                'Le numéro de compte ne doit contenir que des chiffres et des lettres.';
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

/**
 * Draft of a creation, kept in this browser tab only (sessionStorage, per account): a reload
 * loses nothing, closing the tab, saving or cancelling forgets it. Nothing reaches the server
 * before « Enregistrer ». Storage may be unavailable (private mode): the form still works.
 */
type Draft = {
    data: Data;
    current: number;
    reached: number;
    savedAt: string;
    /** Reception search the creation started from (null for a plain creation). */
    origin: string | null;
};
const readDraft = (key: string | null): Draft | null => {
    if (!key) return null;
    try {
        const raw = window.sessionStorage.getItem(key);
        return raw ? (JSON.parse(raw) as Draft) : null;
    } catch {
        return null;
    }
};
const writeDraft = (key: string, draft: Draft | null) => {
    try {
        if (draft) window.sessionStorage.setItem(key, JSON.stringify(draft));
        else window.sessionStorage.removeItem(key);
    } catch {
        // Storage full or blocked: the draft is simply not kept.
    }
};

/** « ! » button of the header: the draft was restored after a reload; opens the notice and « Recommencer ». */
function RestoredNotice({
    savedAt,
    onRestart,
}: {
    savedAt: string;
    onRestart: () => void;
}) {
    const time = new Date(savedAt).toLocaleTimeString('fr-FR', {
        hour: '2-digit',
        minute: '2-digit',
    });
    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    className="relative rounded-full text-info"
                    aria-label={`Saisie restaurée (${time}) : afficher le détail`}
                >
                    <CircleAlert aria-hidden="true" />
                    <span
                        className="absolute -top-0.5 -right-0.5 size-2.5 rounded-full bg-info ring-2 ring-card"
                        aria-hidden="true"
                    />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-80 p-0">
                <div className="space-y-1.5 px-4 py-3 text-sm">
                    <p className="flex items-center gap-2 font-semibold text-foreground">
                        <History
                            className="size-4 text-info"
                            aria-hidden="true"
                        />
                        Saisie restaurée
                    </p>
                    <p className="text-muted-foreground">
                        Les informations entrées avant l’actualisation ({time})
                        sont conservées. Rien n’est enregistré avant «
                        Enregistrer ».
                    </p>
                </div>
                <DropdownMenuSeparator className="my-0" />
                <DropdownMenuItem
                    onSelect={onRestart}
                    className="cursor-pointer rounded-none px-4 py-2.5 font-medium text-danger-foreground focus:text-danger-foreground"
                >
                    <RotateCcw aria-hidden="true" />
                    Recommencer
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

/** Creation opened from a reception search without result. */
type ReceptionContext = {
    search: string;
    back_url: string;
};

export default function StudentForm({
    student,
    options,
    prefill = {},
    reception = null,
}: {
    student: Student | null;
    options: Options;
    /** Field guessed from the reception search (name, email, phone or CIN). */
    prefill?: Record<string, string>;
    reception?: ReceptionContext | null;
}) {
    const editing = student !== null;
    // UIN and bank account: central office only; editing, the server sends them only to it.
    const pnes = student
        ? student.uin !== undefined
        : options.fixedEstablishmentId === null;
    const [identitySteps] = useState(() => identityStepsFor(pnes));
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
        if (!student)
            for (const [key, value] of Object.entries(prefill))
                if (key in values) values[key] = value;
        return values;
    });
    const { auth } = usePage().props;
    const draftKey = editing
        ? null
        : `umg:student-create:${auth.user?.id ?? 'guest'}`;
    // A creation in progress before a reload comes back as it was (fields, step).
    const [restored, setRestored] = useState<Draft | null>(() => {
        const draft = readDraft(draftKey);
        // Started for another reception search: that other student is not mixed in.
        return draft &&
            typeof draft.data === 'object' &&
            (draft.origin ?? null) === (reception?.search ?? null)
            ? draft
            : null;
    });
    const form = useForm<Data>(
        restored
            ? Object.fromEntries(
                  Object.entries(initial).map(([key, value]) => [
                      key,
                      typeof restored.data[key] === 'string'
                          ? restored.data[key]
                          : value,
                  ]),
              )
            : initial,
    );
    const errors = form.errors as Errors;
    const lastStep = steps.length - 1;
    const [current, setCurrent] = useState(() => {
        // Editing from a block of the student page (?step=contact…): open that step.
        if (editing && typeof window !== 'undefined') {
            const asked = new URLSearchParams(window.location.search).get(
                'step',
            );
            const index = steps.findIndex((item) => item.id === asked);
            if (index >= 0) return index;
        }
        return Math.min(restored?.current ?? 0, lastStep);
    });
    // Furthest step reached: creating, later steps open only once the previous ones are valid.
    const [reached, setReached] = useState(() =>
        editing ? lastStep : Math.min(restored?.reached ?? 0, lastStep),
    );
    // Saved or cancelled: the draft is forgotten and never written again.
    const finished = useRef(false);
    const forgetDraft = () => {
        finished.current = true;
        if (draftKey) writeDraft(draftKey, null);
    };
    useEffect(() => {
        if (!draftKey || finished.current) return;
        const timer = window.setTimeout(() => {
            if (finished.current) return;
            const touched = Object.keys(form.data).some(
                (key) => form.data[key] !== initial[key],
            );
            writeDraft(
                draftKey,
                touched
                    ? {
                          data: form.data,
                          current,
                          reached,
                          savedAt: new Date().toISOString(),
                          origin: reception?.search ?? null,
                      }
                    : null,
            );
        }, 300);
        return () => window.clearTimeout(timer);
    }, [draftKey, form.data, current, reached, initial]);
    const restart = () => {
        if (draftKey) writeDraft(draftKey, null);
        form.setData(initial);
        form.clearErrors();
        setCurrent(0);
        setReached(0);
        setRestored(null);
    };
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
            onSuccess: forgetDraft,
            onError: (serverErrors: Errors) => {
                const index = stepWithError(Object.keys(serverErrors));
                if (index >= 0) setCurrent(index);
            },
        };
        if (student) form.put(`/students/${student.uuid}`, visit);
        else {
            // From the reception, the server comes back to the counter on the new student's file.
            if (reception)
                form.transform((data) => ({
                    ...data,
                    from_reception: true,
                    reception_search: reception.search,
                }));
            form.post('/students', visit);
        }
    };
    /** Enter key: creating, goes to the next step; editing, saves from any step. */
    const submit = (event: FormEvent) => {
        event.preventDefault();
        if (editing || current === steps.length - 1) save();
        else open(current + 1);
    };

    const step = steps[current];
    const last = current === steps.length - 1;
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

    /** What a step already holds, shown under its title in the step list. */
    const summaryOf = (item: FormStep): string => {
        const join = (...values: string[]) =>
            values.filter((value) => value !== '').join(' · ');
        switch (item.id) {
            case 'identity':
                return join(
                    `${form.data.last_name} ${form.data.first_names}`.trim(),
                    display('sex'),
                    display('birth_date'),
                );
            case 'cin':
                return join(display('cin'), display('scholarship_code'));
            case 'contact':
                return join(display('phone'), display('email'));
            case 'bac':
                return join(display('bac_series'), display('bac_year'));
            case 'record':
                return join(
                    form.data.level_id
                        ? (options.levels.find(
                              (level) =>
                                  String(level.id) === form.data.level_id,
                          )?.code ?? '')
                        : '',
                    form.data.establishment_id
                        ? (options.establishments.find(
                              (item) =>
                                  String(item.id) ===
                                  form.data.establishment_id,
                          )?.code ?? '')
                        : '',
                );
            default:
                return '';
        }
    };

    /** One block of the review: label / value rows, « Modifier » opens the step. */
    const reviewSection = (item: FormStep, index: number) => {
        const keys =
            item.id === 'record'
                ? RECORD_KEYS
                : (item.fields ?? []).map((field) => field.key);
        return (
            <section
                key={item.id}
                aria-labelledby={`review-${item.id}`}
                className="overflow-hidden rounded-lg border bg-card"
            >
                <div className="flex items-center justify-between gap-3 border-b bg-muted/40 py-1.5 pr-1.5 pl-4">
                    <h3
                        id={`review-${item.id}`}
                        className="text-sm font-semibold text-foreground"
                    >
                        {item.title}
                    </h3>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        className="text-primary hover:text-primary"
                        onClick={() => setCurrent(index)}
                    >
                        <Pencil aria-hidden="true" />
                        Modifier
                        <span className="sr-only"> : {item.title}</span>
                    </Button>
                </div>
                <dl className="divide-y">
                    {keys.map((key) => {
                        const value = display(key);
                        const changed =
                            editing && form.data[key] !== initial[key];
                        return (
                            <div
                                key={key}
                                className="grid grid-cols-[minmax(0,9rem)_minmax(0,1fr)] gap-3 px-4 py-2 text-sm sm:grid-cols-[minmax(0,11rem)_minmax(0,1fr)]"
                            >
                                <dt className="text-muted-foreground">
                                    {labels[key]}
                                </dt>
                                <dd
                                    className={cn(
                                        'flex flex-wrap items-center gap-2 break-words',
                                        value
                                            ? 'font-medium text-foreground'
                                            : 'text-muted-foreground',
                                    )}
                                >
                                    {value || 'Non renseigné'}
                                    {key === 'cin' && cinIsNonStandard && (
                                        <ToneBadge tone="warning">
                                            Non standard
                                        </ToneBadge>
                                    )}
                                    {changed && (
                                        <ToneBadge tone="info">
                                            Modifié
                                        </ToneBadge>
                                    )}
                                </dd>
                            </div>
                        );
                    })}
                </dl>
            </section>
        );
    };

    /** Top of the review: who is being added, and what is still empty. */
    const reviewOverview = () => {
        const optionalEmpty = REVIEW_OPTIONAL.filter(
            (key) => key in form.data && !form.data[key],
        );
        const born = form.data.birth_date
            ? `${form.data.sex === 'F' ? 'née' : form.data.sex === 'M' ? 'né' : 'né(e)'} le ${display('birth_date')}`
            : '';
        const identity = [display('sex'), born, display('nationality')].filter(
            Boolean,
        );
        const situation = editing
            ? []
            : [
                  options.levels.find(
                      (level) => String(level.id) === form.data.level_id,
                  )?.name ?? '',
                  options.establishments.find(
                      (item) => String(item.id) === form.data.establishment_id,
                  )?.name ?? '',
                  options.academicYears.find(
                      (year) => String(year.id) === form.data.academic_year_id,
                  )?.name ?? '',
              ].filter(Boolean);
        return (
            <div className="flex flex-col gap-3 rounded-lg border bg-muted/30 p-4 sm:flex-row sm:items-start sm:justify-between">
                <div className="min-w-0 space-y-1">
                    <p className="text-lg leading-tight font-semibold text-foreground">
                        {`${form.data.last_name} ${form.data.first_names}`.trim() ||
                            '—'}
                    </p>
                    {identity.length > 0 && (
                        <p className="text-sm text-muted-foreground">
                            {identity.join(' · ')}
                        </p>
                    )}
                    {situation.length > 0 && (
                        <p className="text-sm text-foreground">
                            {situation.join(' · ')}
                        </p>
                    )}
                </div>
                {optionalEmpty.length > 0 ? (
                    <span
                        className="shrink-0"
                        title={optionalEmpty
                            .map((key) => labels[key])
                            .join(', ')}
                    >
                        <ToneBadge tone="neutral">
                            {optionalEmpty.length} information
                            {optionalEmpty.length > 1 && 's'} facultative
                            {optionalEmpty.length > 1 && 's'} vide
                            {optionalEmpty.length > 1 && 's'}
                        </ToneBadge>
                    </span>
                ) : (
                    <ToneBadge tone="success" className="shrink-0">
                        Fiche complète
                    </ToneBadge>
                )}
            </div>
        );
    };

    const cancelHref = student
        ? `/students/${student.uuid}`
        : (reception?.back_url ?? '/students');
    const progress = Math.round(((current + 1) / steps.length) * 100);

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
                            ? `Modifier l’identité de ${fullName}`
                            : 'Ajouter un étudiant'
                    }
                    description={
                        editing
                            ? 'Les modifications sont tracées dans l’audit. La situation de l’année se modifie depuis la fiche.'
                            : options.fixedEstablishmentId === null
                              ? 'Un étudiant déjà inscrit dans son établissement : l’inscription se fait hors plateforme.'
                              : 'Un étudiant déjà inscrit dans votre établissement : l’inscription se fait hors plateforme.'
                    }
                    meta={
                        reception && (
                            <ToneBadge tone="info">
                                Depuis la réception
                                {reception.search &&
                                    ` · « ${reception.search} »`}
                            </ToneBadge>
                        )
                    }
                    actions={
                        <>
                            {restored && (
                                <RestoredNotice
                                    savedAt={restored.savedAt}
                                    onRestart={restart}
                                />
                            )}
                            {reception && (
                                <Button variant="outline" asChild>
                                    <Link
                                        href={reception.back_url}
                                        onClick={forgetDraft}
                                    >
                                        <ArrowLeft aria-hidden="true" />
                                        Retour à la recherche
                                    </Link>
                                </Button>
                            )}
                        </>
                    }
                />

                <div className="grid items-start gap-6 lg:grid-cols-[16rem_minmax(0,1fr)] xl:grid-cols-[18rem_minmax(0,1fr)]">
                    {/* Steps: vertical list on large screens, with what each step already holds. */}
                    <nav
                        aria-label="Étapes"
                        className="hidden lg:sticky lg:top-6 lg:block"
                    >
                        <ol className="space-y-1">
                            {steps.map((item, index) => {
                                const state = stateOf(index);
                                const openable =
                                    editing ||
                                    index <= reached ||
                                    index < current;
                                const summary = summaryOf(item);
                                return (
                                    <li key={item.id}>
                                        <button
                                            type="button"
                                            disabled={!openable}
                                            aria-current={
                                                state === 'current'
                                                    ? 'step'
                                                    : undefined
                                            }
                                            onClick={() => open(index)}
                                            className={cn(
                                                'flex w-full items-start gap-3 rounded-lg px-3 py-2.5 text-left transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed',
                                                state === 'current'
                                                    ? 'bg-card shadow-card ring-1 ring-border'
                                                    : 'hover:bg-card/70 disabled:hover:bg-transparent',
                                            )}
                                        >
                                            <span
                                                className={cn(
                                                    'mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full border text-xs font-semibold tabular-nums',
                                                    state === 'current' &&
                                                        'border-primary bg-primary text-primary-foreground',
                                                    state === 'done' &&
                                                        'border-success/40 bg-success-soft text-success-foreground',
                                                    state === 'error' &&
                                                        'border-danger/40 bg-danger-soft text-danger-foreground',
                                                    state === 'upcoming' &&
                                                        'bg-card text-muted-foreground',
                                                )}
                                                aria-hidden="true"
                                            >
                                                {state === 'done' ? (
                                                    <Check className="size-3.5" />
                                                ) : state === 'error' ? (
                                                    '!'
                                                ) : (
                                                    index + 1
                                                )}
                                            </span>
                                            <span className="min-w-0 flex-1">
                                                <span
                                                    className={cn(
                                                        'block text-sm',
                                                        state === 'current'
                                                            ? 'font-semibold text-foreground'
                                                            : state === 'error'
                                                              ? 'font-medium text-danger-foreground'
                                                              : openable
                                                                ? 'font-medium text-foreground'
                                                                : 'text-muted-foreground',
                                                    )}
                                                >
                                                    {item.title}
                                                </span>
                                                <span className="block truncate text-xs text-muted-foreground">
                                                    {state === 'error'
                                                        ? 'À corriger'
                                                        : summary ||
                                                          item.description}
                                                </span>
                                            </span>
                                        </button>
                                    </li>
                                );
                            })}
                        </ol>
                    </nav>

                    <form
                        onSubmit={submit}
                        noValidate
                        className="surface min-w-0 overflow-clip"
                    >
                        {/* Small screens: where the agent stands, instead of the list. */}
                        <div className="border-b px-5 pt-4 pb-3 lg:hidden">
                            <p className="text-xs text-muted-foreground">
                                Étape {current + 1} sur {steps.length}
                            </p>
                            <div
                                className="mt-2 h-1 overflow-hidden rounded-full bg-muted"
                                aria-hidden="true"
                            >
                                <div
                                    className="h-full rounded-full bg-primary transition-[width]"
                                    style={{ width: `${progress}%` }}
                                />
                            </div>
                        </div>

                        <div
                            ref={panel}
                            tabIndex={-1}
                            aria-label={`Étape ${current + 1} sur ${steps.length} : ${step.title}`}
                            className="scroll-mt-24 space-y-6 p-5 outline-none sm:p-6"
                        >
                            <header>
                                <h2 className="text-lg font-semibold text-foreground">
                                    {step.title}
                                </h2>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    {step.id === 'review' && editing
                                        ? 'Vérifiez les informations ; les champs modifiés sont signalés.'
                                        : stepDescriptions[step.id]}
                                </p>
                            </header>

                            <ErrorSummary
                                errors={Object.fromEntries(
                                    step.keys.map((key) => [key, errors[key]]),
                                )}
                                labels={labels}
                            />

                            {step.fields && (
                                <div className="grid gap-x-6 gap-y-5 sm:grid-cols-2">
                                    {step.fields.map(renderField)}
                                </div>
                            )}
                            {step.id === 'cin' && cinIsNonStandard && (
                                <p className="-mt-2 flex items-start gap-2 text-sm text-warning-foreground">
                                    <AlertTriangle
                                        className="mt-0.5 size-4 shrink-0"
                                        aria-hidden="true"
                                    />
                                    Ce numéro n’a pas 12 chiffres : il sera
                                    accepté mais signalé « CIN non standard » et
                                    ne servira pas à identifier l’étudiant.
                                </p>
                            )}
                            {step.id === 'record' && (
                                <div className="grid gap-x-6 gap-y-5 sm:grid-cols-2">
                                    <RecordFields
                                        bare
                                        options={options}
                                        data={form.data}
                                        errors={form.errors}
                                        setData={(key, value) => {
                                            form.setData(key, value);
                                            form.clearErrors(key);
                                        }}
                                    />
                                </div>
                            )}
                            {step.id === 'review' && (
                                <div className="space-y-4">
                                    {reviewOverview()}
                                    <div className="grid items-start gap-4 xl:grid-cols-2">
                                        {steps
                                            .slice(0, -1)
                                            .map((item, index) =>
                                                reviewSection(item, index),
                                            )}
                                    </div>
                                </div>
                            )}
                        </div>

                        {/* Actions stay in reach at the bottom of the screen. */}
                        <div className="sticky bottom-0 z-20 flex flex-col-reverse gap-3 border-t bg-card/95 px-5 py-3 backdrop-blur-sm sm:flex-row sm:items-center sm:justify-between sm:px-6">
                            <div className="flex items-center gap-3">
                                <Button variant="ghost" asChild>
                                    <Link
                                        href={cancelHref}
                                        onClick={forgetDraft}
                                    >
                                        Annuler
                                    </Link>
                                </Button>
                                <p className="hidden text-xs text-muted-foreground sm:block">
                                    {editing
                                        ? changedKeys.length === 0
                                            ? 'Aucune modification pour l’instant.'
                                            : `${changedKeys.length} champ${changedKeys.length > 1 ? 's' : ''} modifié${changedKeys.length > 1 ? 's' : ''}.`
                                        : '* champ obligatoire'}
                                </p>
                            </div>
                            <div className="flex gap-2 [&>*]:flex-1 sm:[&>*]:flex-none">
                                {current > 0 && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => setCurrent(current - 1)}
                                    >
                                        <ArrowLeft aria-hidden="true" />
                                        Précédent
                                    </Button>
                                )}
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
                                              ? 'Enregistrer les modifications'
                                              : 'Ajouter l’étudiant'}
                                    </Button>
                                )}
                            </div>
                        </div>
                    </form>
                </div>
            </PageBody>
        </>
    );
}

const stepDescriptions: Record<string, string> = {
    identity: 'Telle qu’elle figure sur les pièces officielles de l’étudiant.',
    cin: 'Données sensibles, visibles seulement par le personnel autorisé.',
    contact: 'Pour joindre l’étudiant au sujet de son dossier.',
    bac: 'Le diplôme qui lui a ouvert l’accès à l’université.',
    record: 'Où et à quel niveau l’étudiant est inscrit cette année. Il reste « en attente » tant que la liste de l’établissement ne l’a pas confirmé.',
    review: 'Relisez avant d’ajouter l’étudiant ; « Modifier » rouvre l’étape.',
};

StudentForm.layout = {
    breadcrumbs: [{ title: 'Étudiants', href: '/students' }],
};
