import { Head, Link, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    CalendarRange,
    Contact,
    GraduationCap,
    Network,
    Power,
    Star,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { FormEvent } from 'react';
import {
    ErrorSummary,
    FormActions,
    FormField,
    FormSection,
} from '@/components/forms/form-kit';
import { PageBody, PageHeader } from '@/components/page-header';
import { ToneBadge } from '@/components/tone-badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';
import { article, baseUrl, definitions } from './definitions';
import type {
    DomainOption,
    EstablishmentOption,
    Field,
    MentionOption,
    ReferenceKind,
    ReferenceRecord,
} from './definitions';
import { recordDescriptions, referenceIcons } from './reference-meta';

export type ReferenceFormProps = {
    record: ReferenceRecord | null;
    establishments?: EstablishmentOption[];
    mentions?: MentionOption[];
    domains?: DomainOption[];
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
    scholarship_max_bac_year: {
        placeholder: 'Ex. : 2024',
        hint: 'Règle bourse du modèle établissement : BAC obtenu au plus tard cette année. Vide = éligibilité indéterminée.',
    },
    sector: { hint: 'Facultatif : publique ou privée.' },
    training_type: { hint: 'Facultatif : académique ou professionnalisant.' },
};

type SectionKey =
    | 'attachment'
    | 'identity'
    | 'period'
    | 'scholarship'
    | 'contact';
/** Which form section each field belongs to; unknown fields fall back to "identity". */
const sectionOf: Record<string, SectionKey> = {
    establishment_id: 'attachment',
    mention_id: 'attachment',
    domain_id: 'attachment',
    starts_at: 'period',
    ends_at: 'period',
    scholarship_max_bac_year: 'scholarship',
    address: 'contact',
    phone: 'contact',
    email: 'contact',
};
const sectionOrder: SectionKey[] = [
    'attachment',
    'identity',
    'period',
    'scholarship',
    'contact',
];
/** Wide fields span the whole section row. */
const wideFields = ['address'];

export default function ReferenceForm({
    kind,
    record,
    establishments = [],
    mentions = [],
    domains = [],
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
    const sections: Record<
        SectionKey,
        { icon: LucideIcon; title: string; description: string }
    > = {
        attachment: {
            icon: Network,
            title: 'Rattachement',
            description:
                kind === 'programs'
                    ? 'Établissement du parcours et, si possible, sa mention.'
                    : 'Établissement auquel appartient cette mention et son domaine LMD.',
        },
        identity: {
            icon: referenceIcons[kind],
            title: 'Informations',
            description: `Données de ${articleText} ${definition.singular}.`,
        },
        period: {
            icon: CalendarRange,
            title: 'Période',
            description: 'Dates indicatives de l’année universitaire.',
        },
        scholarship: {
            icon: GraduationCap,
            title: 'Bourse',
            description:
                'Règle d’éligibilité appliquée aux dossiers de cette année.',
        },
        contact: {
            icon: Contact,
            title: 'Coordonnées',
            description: 'Adresse et moyens de contact de l’établissement.',
        },
    };
    const grouped = sectionOrder
        .map((key) => ({
            key,
            fields: definition.fields.filter(
                (field) => (sectionOf[field.key] ?? 'identity') === key,
            ),
        }))
        .filter((group) => group.fields.length > 0);
    const active = Boolean(form.data.is_active);

    const control = (field: Field) => {
        const fieldHelp = help[field.key] ?? {};
        if (field.type === 'select')
            return (
                <Select
                    value={String(form.data[field.key]) || 'none'}
                    onValueChange={(value) =>
                        form.setData(field.key, value === 'none' ? '' : value)
                    }
                >
                    <SelectTrigger
                        id={field.key}
                        className="w-full"
                        aria-invalid={errors[field.key] ? true : undefined}
                    >
                        <SelectValue placeholder="Non précisé" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="none">Non précisé</SelectItem>
                        {(field.options ?? []).map((option) => (
                            <SelectItem key={option.value} value={option.value}>
                                {option.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            );
        if (field.type === 'domain')
            return (
                <Select
                    value={String(form.data[field.key]) || 'none'}
                    onValueChange={(value) =>
                        form.setData(field.key, value === 'none' ? '' : value)
                    }
                >
                    <SelectTrigger
                        id={field.key}
                        className="w-full"
                        aria-invalid={errors[field.key] ? true : undefined}
                    >
                        <SelectValue placeholder="Sans domaine" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="none">Sans domaine</SelectItem>
                        {domains.map((option) => (
                            <SelectItem
                                key={option.id}
                                value={String(option.id)}
                            >
                                {option.code} — {option.name}
                                {!option.is_active ? ' (inactif)' : ''}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            );
        if (field.type === 'mention')
            return (
                <Select
                    value={String(form.data[field.key]) || 'none'}
                    onValueChange={(value) =>
                        form.setData(field.key, value === 'none' ? '' : value)
                    }
                    disabled={!form.data.establishment_id}
                >
                    <SelectTrigger id={field.key} className="w-full">
                        <SelectValue placeholder="Sans mention" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="none">Sans mention</SelectItem>
                        {availableMentions.map((option) => (
                            <SelectItem
                                key={option.id}
                                value={String(option.id)}
                            >
                                {option.code ? `${option.code} — ` : ''}
                                {option.name}
                                {!option.is_active ? ' (inactive)' : ''}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            );
        if (field.type === 'establishment')
            return (
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
                        aria-invalid={errors[field.key] ? true : undefined}
                    >
                        <SelectValue placeholder="Choisir un établissement" />
                    </SelectTrigger>
                    <SelectContent>
                        {establishments.map((option) => (
                            <SelectItem
                                key={option.id}
                                value={String(option.id)}
                            >
                                {option.code} — {option.name}
                                {!option.is_active ? ' (inactif)' : ''}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            );
        return (
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
                min={field.type === 'number' ? (field.min ?? 0) : undefined}
                max={field.max}
                placeholder={fieldHelp.placeholder}
                autoComplete="off"
                aria-invalid={errors[field.key] ? true : undefined}
                className={field.key === 'code' ? 'font-mono' : undefined}
            />
        );
    };
    const hintOf = (field: Field) =>
        field.type === 'mention'
            ? !form.data.establishment_id
                ? 'Choisissez d’abord l’établissement.'
                : availableMentions.length === 0
                  ? 'Aucune mention pour cet établissement : créez-la d’abord dans Mentions.'
                  : 'Facultatif : le parcours peut rester sans mention.'
            : field.type === 'domain'
              ? domains.length === 0
                  ? 'Aucun domaine : créez-le d’abord dans Domaines.'
                  : 'Facultatif : domaine LMD (ALSH, STS…) de la mention.'
              : help[field.key]?.hint;

    return (
        <PageBody className="pb-0 md:pb-0 lg:pb-0">
            <Head title={definition.title} />
            <PageHeader
                icon={referenceIcons[kind]}
                title={
                    record
                        ? `${definition.title} / ${record.name}`
                        : `Créer ${articleText} ${definition.singular}`
                }
                description={recordDescriptions[kind]}
                meta={
                    record && (
                        <div className="flex flex-wrap items-center gap-2">
                            <ToneBadge
                                tone={record.is_active ? 'success' : 'neutral'}
                            >
                                {record.is_active ? 'Actif' : 'Inactif'}
                            </ToneBadge>
                            {record.is_current && (
                                <ToneBadge tone="primary">
                                    Année courante
                                </ToneBadge>
                            )}
                        </div>
                    )
                }
                actions={
                    <Button variant="outline" asChild>
                        <Link href={baseUrl(kind)}>
                            <ArrowLeft aria-hidden="true" />
                            {definition.title}
                        </Link>
                    </Button>
                }
            />
            <form onSubmit={submit} className="space-y-6" noValidate>
                <ErrorSummary errors={errors} labels={labels} />
                {grouped.map((group) => (
                    <FormSection
                        key={group.key}
                        icon={sections[group.key].icon}
                        title={sections[group.key].title}
                        description={sections[group.key].description}
                    >
                        {group.fields.map((field) => (
                            <FormField
                                key={field.key}
                                id={field.key}
                                label={field.label}
                                required={field.required}
                                error={errors[field.key]}
                                hint={hintOf(field)}
                                wide={wideFields.includes(field.key)}
                            >
                                {control(field)}
                            </FormField>
                        ))}
                    </FormSection>
                ))}
                <FormSection
                    icon={Power}
                    title="Statut"
                    description="Un référentiel inactif reste dans l’historique mais n’est plus proposé dans les formulaires."
                    columns={1}
                >
                    <label
                        htmlFor="is_active"
                        className={cn(
                            'flex cursor-pointer items-start gap-3 rounded-xl border p-4 transition-colors',
                            active
                                ? 'border-success/40 bg-success-soft/50'
                                : 'hover:bg-muted/40',
                        )}
                    >
                        <Checkbox
                            id="is_active"
                            checked={active}
                            onCheckedChange={(checked) =>
                                form.setData('is_active', checked === true)
                            }
                            className="mt-0.5"
                        />
                        <span className="min-w-0 flex-1">
                            <span className="block text-sm font-bold text-foreground">
                                Actif
                            </span>
                            <span className="mt-1 flex items-start gap-1.5 text-sm text-muted-foreground">
                                {record?.is_current && (
                                    <Star
                                        className="mt-0.5 size-3.5 shrink-0 text-warning"
                                        aria-hidden="true"
                                    />
                                )}
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
        </PageBody>
    );
}
