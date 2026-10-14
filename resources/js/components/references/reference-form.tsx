import { Head, useForm } from '@inertiajs/react';
import {
    BookMarked,
    Building2,
    CalendarDays,
    Layers,
    Power,
    Route,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { FormEvent } from 'react';
import {
    ErrorSummary,
    FormActions,
    FormField,
    FormSection,
} from '@/components/forms/form-kit';
import Heading from '@/components/heading';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { article, baseUrl, definitions } from './definitions';
import type {
    EstablishmentOption,
    MentionOption,
    ReferenceKind,
    ReferenceRecord,
} from './definitions';

export type ReferenceFormProps = {
    record: ReferenceRecord | null;
    establishments?: EstablishmentOption[];
    mentions?: MentionOption[];
};

const icons: Record<ReferenceKind, LucideIcon> = {
    establishments: Building2,
    'academic-years': CalendarDays,
    levels: Layers,
    mentions: BookMarked,
    programs: Route,
};
const descriptions: Record<ReferenceKind, string> = {
    establishments: 'Faculté, école ou institut de l’Université de Mahajanga.',
    'academic-years':
        'Période universitaire ; l’année courante se définit depuis la liste.',
    levels: 'Niveau d’études (L1, L2, M1…), commun à tous les établissements.',
    mentions:
        'Mention d’un établissement (LMD) ; elle regroupe plusieurs parcours.',
    programs:
        'Parcours d’un établissement, rattaché si possible à une mention du même établissement.',
};
const CODE_HINT =
    'Majuscules, chiffres et . _ - uniquement (30 caractères au plus).';
const help: Record<string, { placeholder?: string; hint?: string }> = {
    code: { placeholder: 'Ex. : FS, INFO, L1', hint: CODE_HINT },
    name: { placeholder: 'Nom complet affiché dans les listes' },
    type: { placeholder: 'Faculté, École, Institut…' },
    address: { placeholder: 'Adresse postale' },
    phone: { placeholder: '034 12 345 67' },
    email: { placeholder: 'contact@etablissement.mg' },
    starts_at: { hint: 'Facultatif : date indicative.' },
    ends_at: { hint: 'Facultatif : date indicative.' },
    sort_order: {
        placeholder: '1',
        hint: 'Position dans les listes (du plus petit au plus grand).',
    },
};

export default function ReferenceForm({
    kind,
    record,
    establishments = [],
    mentions = [],
}: ReferenceFormProps & { kind: ReferenceKind }) {
    const definition = definitions[kind];
    const articleText = article(kind);
    const initial: Record<string, string | boolean> = {
        is_active: record?.is_active ?? true,
    };
    for (const field of definition.fields) {
        const value = record?.[field.key as keyof ReferenceRecord];
        initial[field.key] =
            field.type === 'date' && typeof value === 'string'
                ? value.slice(0, 10)
                : typeof value === 'number' || typeof value === 'string'
                  ? String(value)
                  : field.key === 'sort_order'
                    ? '0'
                    : '';
    }
    const form = useForm(initial);
    const errors = form.errors as Record<string, string | undefined>;
    const submit = (event: FormEvent) => {
        event.preventDefault();
        if (record) form.put(`${baseUrl(kind)}/${record.uuid}`);
        else form.post(baseUrl(kind));
    };
    const availableMentions = mentions.filter(
        (option) =>
            String(option.establishment_id) ===
            String(form.data.establishment_id),
    );
    const labels = Object.fromEntries(
        definition.fields.map((field) => [field.key, field.label]),
    );

    return (
        <div className="space-y-6 p-4 pb-0 md:p-6 md:pb-0">
            <Head title={definition.title} />
            <Heading
                title={
                    record
                        ? `${definition.title} / ${record.name}`
                        : `Créer ${articleText} ${definition.singular}`
                }
                description={descriptions[kind]}
            />
            <form onSubmit={submit} className="space-y-6" noValidate>
                <ErrorSummary errors={errors} labels={labels} />
                <FormSection
                    icon={icons[kind]}
                    title="Informations"
                    description={`Données de ${articleText} ${definition.singular}.`}
                >
                    {definition.fields.map((field) => {
                        const fieldHelp = help[field.key] ?? {};
                        return (
                            <FormField
                                key={field.key}
                                id={field.key}
                                label={field.label}
                                required={field.required}
                                error={errors[field.key]}
                                hint={
                                    field.type === 'mention'
                                        ? !form.data.establishment_id
                                            ? 'Choisissez d’abord l’établissement.'
                                            : availableMentions.length === 0
                                              ? 'Aucune mention pour cet établissement : créez-la d’abord dans Mentions.'
                                              : 'Facultatif : le parcours peut rester sans mention.'
                                        : fieldHelp.hint
                                }
                            >
                                {field.type === 'mention' ? (
                                    <Select
                                        value={
                                            String(form.data[field.key]) ||
                                            'none'
                                        }
                                        onValueChange={(value) =>
                                            form.setData(
                                                field.key,
                                                value === 'none' ? '' : value,
                                            )
                                        }
                                        disabled={!form.data.establishment_id}
                                    >
                                        <SelectTrigger
                                            id={field.key}
                                            className="w-full"
                                        >
                                            <SelectValue placeholder="Sans mention" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="none">
                                                Sans mention
                                            </SelectItem>
                                            {availableMentions.map((option) => (
                                                <SelectItem
                                                    key={option.id}
                                                    value={String(option.id)}
                                                >
                                                    {option.code
                                                        ? `${option.code} — `
                                                        : ''}
                                                    {option.name}
                                                    {!option.is_active
                                                        ? ' (inactive)'
                                                        : ''}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                ) : field.type === 'establishment' ? (
                                    <Select
                                        value={String(form.data[field.key])}
                                        onValueChange={(value) =>
                                            form.setData((data) => ({
                                                ...data,
                                                [field.key]: value,
                                                ...('mention_id' in data &&
                                                value !== data[field.key]
                                                    ? { mention_id: '' }
                                                    : {}),
                                            }))
                                        }
                                    >
                                        <SelectTrigger
                                            id={field.key}
                                            className="w-full"
                                            aria-invalid={
                                                errors[field.key]
                                                    ? true
                                                    : undefined
                                            }
                                        >
                                            <SelectValue placeholder="Choisir un établissement" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {establishments.map((option) => (
                                                <SelectItem
                                                    key={option.id}
                                                    value={String(option.id)}
                                                >
                                                    {option.code} —{' '}
                                                    {option.name}
                                                    {!option.is_active
                                                        ? ' (inactif)'
                                                        : ''}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                ) : (
                                    <Input
                                        id={field.key}
                                        type={field.type ?? 'text'}
                                        value={String(form.data[field.key])}
                                        onChange={(event) =>
                                            form.setData(
                                                field.key,
                                                field.key === 'code'
                                                    ? event.target.value.toUpperCase()
                                                    : event.target.value,
                                            )
                                        }
                                        required={field.required}
                                        maxLength={field.maxLength}
                                        min={
                                            field.type === 'number'
                                                ? 0
                                                : undefined
                                        }
                                        placeholder={fieldHelp.placeholder}
                                        autoComplete="off"
                                        aria-invalid={
                                            errors[field.key] ? true : undefined
                                        }
                                    />
                                )}
                            </FormField>
                        );
                    })}
                </FormSection>
                <FormSection
                    icon={Power}
                    title="Statut"
                    description="Un référentiel inactif reste dans l’historique mais n’est plus proposé dans les formulaires."
                    columns={1}
                >
                    <label
                        htmlFor="is_active"
                        className="flex cursor-pointer items-start gap-3 rounded-lg border p-4 hover:bg-muted/40"
                    >
                        <Checkbox
                            id="is_active"
                            checked={Boolean(form.data.is_active)}
                            onCheckedChange={(checked) =>
                                form.setData('is_active', checked === true)
                            }
                            className="mt-0.5"
                        />
                        <span>
                            <span className="block text-sm font-bold text-foreground">
                                Actif
                            </span>
                            <span className="block text-sm text-muted-foreground">
                                {record?.is_current
                                    ? 'Année courante : la désactivation lui retirera aussi ce statut.'
                                    : 'Proposé dans les formulaires et les imports.'}
                            </span>
                        </span>
                    </label>
                </FormSection>
                <FormActions
                    processing={form.processing}
                    submitLabel={
                        record
                            ? 'Enregistrer les modifications'
                            : `Créer ${articleText} ${definition.singular}`
                    }
                    cancelHref={baseUrl(kind)}
                />
            </form>
        </div>
    );
}
