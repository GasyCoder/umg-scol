import { Head, Link, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    AtSign,
    Building2,
    Check,
    Contact,
    FlaskConical,
    GraduationCap,
    ImagePlus,
    Landmark,
    MapPin,
    Phone,
    Power,
    School,
    Shapes,
    Trash2,
    Upload,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { DragEvent, FormEvent } from 'react';
import {
    ErrorSummary,
    FormActions,
    FormField,
    FormSection,
} from '@/components/forms/form-kit';
import { PageBody, PageHeader } from '@/components/page-header';
import { sectorOptions } from '@/components/references/definitions';
import type { ReferenceRecord } from '@/components/references/definitions';
import { LogoTile } from '@/components/references/logo-tile';
import { ToneBadge } from '@/components/tone-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';

const OTHER = 'Autre';
const MAX_BYTES = 1024 * 1024;
const ACCEPTED = ['image/png', 'image/jpeg', 'image/webp'];
const typeIcons: Record<string, LucideIcon> = {
    Faculté: GraduationCap,
    Institut: FlaskConical,
    École: School,
    [OTHER]: Shapes,
};
const typeHints: Record<string, string> = {
    Faculté: 'Unité de formation et de recherche',
    Institut: 'Institut universitaire ou de recherche',
    École: 'École supérieure ou normale',
    [OTHER]: 'Précisez librement le type',
};
const labels = {
    code: 'Sigle',
    name: 'Nom complet',
    type: 'Type',
    type_other: 'Type',
    sector: 'Institution',
    logo: 'Logo',
    address: 'Adresse',
    phone: 'Téléphone',
    email: 'Email',
};
const big = 'h-12 text-base md:text-base';

type Record_ = ReferenceRecord & { logo_url?: string | null };

export default function EstablishmentForm({
    record,
    types,
}: {
    record: Record_ | null;
    types: string[];
}) {
    const known = record?.type && types.includes(record.type);
    const form = useForm({
        code: record?.code ?? '',
        name: record?.name ?? '',
        type_choice: known ? record.type! : record?.type ? OTHER : '',
        type_other: !known && record?.type ? record.type : '',
        sector: record?.sector ?? '',
        address: record?.address ?? '',
        phone: record?.phone ?? '',
        email: record?.email ?? '',
        is_active: record?.is_active ?? true,
        logo: null as File | null,
        remove_logo: false,
    });
    const [preview, setPreview] = useState<string | null>(null);
    const [logoError, setLogoError] = useState<string | null>(null);
    const [dragging, setDragging] = useState(false);
    const fileInput = useRef<HTMLInputElement>(null);

    useEffect(
        () => () => {
            if (preview) URL.revokeObjectURL(preview);
        },
        [preview],
    );

    const pickLogo = (file: File | undefined) => {
        if (!file) return;
        if (!ACCEPTED.includes(file.type)) {
            setLogoError('Choisissez une image PNG, JPG ou WebP.');
            return;
        }
        if (file.size > MAX_BYTES) {
            setLogoError(
                'L’image dépasse 1 Mo : réduisez-la avant de l’envoyer.',
            );
            return;
        }
        setLogoError(null);
        setPreview(URL.createObjectURL(file));
        form.setData((data) => ({ ...data, logo: file, remove_logo: false }));
    };
    const removeLogo = () => {
        setPreview(null);
        setLogoError(null);
        if (fileInput.current) fileInput.current.value = '';
        form.setData((data) => ({
            ...data,
            logo: null,
            remove_logo: Boolean(record?.logo_url),
        }));
    };
    const onDrop = (event: DragEvent<HTMLDivElement>) => {
        event.preventDefault();
        setDragging(false);
        pickLogo(event.dataTransfer.files[0]);
    };

    const shownLogo =
        preview ?? (form.data.remove_logo ? null : (record?.logo_url ?? null));
    const type =
        form.data.type_choice === OTHER
            ? form.data.type_other.trim()
            : form.data.type_choice;

    const submit = (event: FormEvent) => {
        event.preventDefault();
        if (form.data.type_choice === OTHER && !type) {
            form.setError(
                'type_other',
                'Précisez le type, ou choisissez Faculté, Institut ou École.',
            );
            document.getElementById('type_other')?.focus();
            return;
        }
        form.clearErrors('type_other');
        form.transform((data) => ({
            ...(record ? { _method: 'put' } : {}),
            code: data.code,
            name: data.name,
            type: type || null,
            sector: data.sector || null,
            address: data.address,
            phone: data.phone,
            email: data.email,
            is_active: data.is_active,
            remove_logo: data.remove_logo,
            ...(data.logo ? { logo: data.logo } : {}),
        }));
        form.post(
            record
                ? `/references/establishments/${record.uuid}`
                : '/references/establishments',
            { forceFormData: true, preserveScroll: true },
        );
    };
    const errors = form.errors as Record<string, string | undefined>;

    return (
        <>
            <Head
                title={
                    record
                        ? `${record.name} · Établissement`
                        : 'Nouvel établissement'
                }
            />
            <PageBody>
                <PageHeader
                    icon={Building2}
                    title={record ? record.name : 'Créer un établissement'}
                    description="Faculté, institut ou école de l’Université de Mahajanga : identité, logo et coordonnées."
                    meta={
                        record && (
                            <ToneBadge
                                tone={record.is_active ? 'success' : 'neutral'}
                            >
                                {record.is_active ? 'Actif' : 'Inactif'}
                            </ToneBadge>
                        )
                    }
                    actions={
                        <Button variant="outline" asChild>
                            <Link href="/references/establishments">
                                <ArrowLeft aria-hidden="true" />
                                Établissements
                            </Link>
                        </Button>
                    }
                />

                <form
                    onSubmit={submit}
                    noValidate
                    className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]"
                >
                    <div className="min-w-0 space-y-6">
                        <ErrorSummary
                            errors={{
                                ...errors,
                                logo: errors.logo ?? logoError ?? undefined,
                            }}
                            labels={labels}
                        />

                        <FormSection
                            icon={Landmark}
                            title="Identité"
                            description="Le sigle sert de repère court partout dans la plateforme."
                            columns={1}
                        >
                            <div className="grid gap-x-6 gap-y-5 md:grid-cols-[14rem_minmax(0,1fr)]">
                                <FormField
                                    id="code"
                                    label={labels.code}
                                    required
                                    hint="Majuscules, chiffres et . _ - (30 au plus). Ex. : FST, ENS."
                                    error={errors.code}
                                >
                                    <Input
                                        id="code"
                                        value={form.data.code}
                                        onChange={(e) =>
                                            form.setData(
                                                'code',
                                                e.target.value.toUpperCase(),
                                            )
                                        }
                                        maxLength={30}
                                        autoComplete="off"
                                        placeholder="FST"
                                        aria-invalid={Boolean(errors.code)}
                                        className={cn(
                                            big,
                                            'font-mono text-lg font-bold tracking-wider md:text-lg',
                                        )}
                                    />
                                </FormField>
                                <FormField
                                    id="name"
                                    label={labels.name}
                                    required
                                    hint="Tel qu’il apparaît dans les documents officiels."
                                    error={errors.name}
                                >
                                    <Input
                                        id="name"
                                        value={form.data.name}
                                        onChange={(e) =>
                                            form.setData('name', e.target.value)
                                        }
                                        maxLength={255}
                                        autoComplete="off"
                                        placeholder="Faculté des Sciences"
                                        aria-invalid={Boolean(errors.name)}
                                        className={big}
                                    />
                                </FormField>
                                <FormField
                                    id="sector"
                                    label={labels.sector}
                                    hint="Facultatif : publique ou privée."
                                    error={errors.sector}
                                >
                                    <Select
                                        value={form.data.sector || 'none'}
                                        onValueChange={(value) =>
                                            form.setData(
                                                'sector',
                                                value === 'none' ? '' : value,
                                            )
                                        }
                                    >
                                        <SelectTrigger
                                            id="sector"
                                            className="h-12 w-full text-base"
                                            aria-invalid={Boolean(
                                                errors.sector,
                                            )}
                                        >
                                            <SelectValue placeholder="Non précisée" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="none">
                                                Non précisée
                                            </SelectItem>
                                            {sectorOptions.map((option) => (
                                                <SelectItem
                                                    key={option.value}
                                                    value={option.value}
                                                >
                                                    {option.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </FormField>
                            </div>
                        </FormSection>

                        <FormSection
                            id="type"
                            icon={Shapes}
                            title="Type d’établissement"
                            description="Choisissez le type ; « Autre » permet de le saisir librement."
                            columns={1}
                        >
                            <div
                                role="radiogroup"
                                aria-label="Type d’établissement"
                                className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4"
                            >
                                {[...types, OTHER].map((option) => {
                                    const Icon = typeIcons[option] ?? Shapes;
                                    const selected =
                                        form.data.type_choice === option;
                                    return (
                                        <button
                                            key={option}
                                            type="button"
                                            role="radio"
                                            aria-checked={selected}
                                            onClick={() =>
                                                form.setData(
                                                    'type_choice',
                                                    selected ? '' : option,
                                                )
                                            }
                                            className={cn(
                                                'relative flex flex-col items-start gap-3 rounded-xl border bg-card p-4 text-left transition-all focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                                selected
                                                    ? 'border-primary bg-accent/50 ring-2 ring-primary/20'
                                                    : 'hover:border-primary/40 hover:shadow-card',
                                            )}
                                        >
                                            <span
                                                className={cn(
                                                    'flex size-11 items-center justify-center rounded-xl transition-colors',
                                                    selected
                                                        ? 'bg-primary text-primary-foreground'
                                                        : 'bg-accent text-primary',
                                                )}
                                                aria-hidden="true"
                                            >
                                                <Icon className="size-5" />
                                            </span>
                                            <span>
                                                <span className="block text-base font-semibold text-foreground">
                                                    {option}
                                                </span>
                                                <span className="block text-xs text-muted-foreground">
                                                    {typeHints[option] ?? ''}
                                                </span>
                                            </span>
                                            <span
                                                className={cn(
                                                    'absolute top-3 right-3 flex size-5 items-center justify-center rounded-full border',
                                                    selected
                                                        ? 'border-primary bg-primary text-primary-foreground'
                                                        : 'bg-card',
                                                )}
                                                aria-hidden="true"
                                            >
                                                {selected && (
                                                    <Check className="size-3" />
                                                )}
                                            </span>
                                        </button>
                                    );
                                })}
                            </div>
                            {form.data.type_choice === OTHER && (
                                <FormField
                                    id="type_other"
                                    label="Précisez le type"
                                    required
                                    hint="Ex. : Centre de recherche, Annexe universitaire…"
                                    error={errors.type_other ?? errors.type}
                                >
                                    <Input
                                        id="type_other"
                                        autoFocus
                                        value={form.data.type_other}
                                        onChange={(e) =>
                                            form.setData(
                                                'type_other',
                                                e.target.value,
                                            )
                                        }
                                        maxLength={100}
                                        placeholder="Type de l’établissement"
                                        aria-invalid={Boolean(
                                            errors.type_other ?? errors.type,
                                        )}
                                        className={big}
                                    />
                                </FormField>
                            )}
                            {form.data.type_choice !== OTHER && errors.type && (
                                <p className="text-sm font-medium text-danger-foreground">
                                    {errors.type}
                                </p>
                            )}
                        </FormSection>

                        <FormSection
                            id="logo"
                            icon={ImagePlus}
                            title="Logo"
                            description="Facultatif. PNG, JPG ou WebP, 1 Mo au plus ; un format carré rend le mieux."
                            columns={1}
                        >
                            <div
                                onDragOver={(e) => {
                                    e.preventDefault();
                                    setDragging(true);
                                }}
                                onDragLeave={() => setDragging(false)}
                                onDrop={onDrop}
                                className={cn(
                                    'flex flex-col items-center gap-5 rounded-2xl border-2 border-dashed p-5 transition-colors sm:flex-row sm:p-6',
                                    dragging
                                        ? 'border-primary bg-accent/60'
                                        : errors.logo || logoError
                                          ? 'border-danger/50 bg-danger-soft/40'
                                          : 'border-border bg-muted/40',
                                )}
                            >
                                <LogoTile
                                    src={shownLogo}
                                    code={form.data.code}
                                    className="size-32 rounded-2xl text-3xl"
                                />
                                <div className="flex min-w-0 flex-1 flex-col items-center gap-3 text-center sm:items-start sm:text-left">
                                    <div>
                                        <p className="text-base font-semibold text-foreground">
                                            {dragging
                                                ? 'Déposez l’image ici'
                                                : shownLogo
                                                  ? 'Logo de l’établissement'
                                                  : 'Glissez-déposez le logo ici'}
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            {form.data.logo
                                                ? `${form.data.logo.name} · ${Math.ceil(form.data.logo.size / 1024)} Ko (enregistré avec le formulaire)`
                                                : form.data.remove_logo
                                                  ? 'Le logo sera retiré à l’enregistrement.'
                                                  : 'ou choisissez une image sur votre appareil.'}
                                        </p>
                                    </div>
                                    <div className="flex flex-wrap justify-center gap-2">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            className="h-10"
                                            onClick={() =>
                                                fileInput.current?.click()
                                            }
                                        >
                                            <Upload aria-hidden="true" />
                                            {shownLogo
                                                ? 'Remplacer'
                                                : 'Choisir une image'}
                                        </Button>
                                        {shownLogo && (
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                className="h-10 text-danger-foreground hover:bg-danger-soft hover:text-danger-foreground"
                                                onClick={removeLogo}
                                            >
                                                <Trash2 aria-hidden="true" />
                                                Retirer
                                            </Button>
                                        )}
                                    </div>
                                    <input
                                        ref={fileInput}
                                        id="logo-input"
                                        type="file"
                                        accept=".png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp"
                                        className="sr-only"
                                        aria-label="Choisir le logo"
                                        onChange={(e) =>
                                            pickLogo(e.target.files?.[0])
                                        }
                                    />
                                    {(errors.logo || logoError) && (
                                        <p
                                            role="alert"
                                            className="text-sm font-medium text-danger-foreground"
                                        >
                                            {errors.logo ?? logoError}
                                        </p>
                                    )}
                                </div>
                            </div>
                        </FormSection>

                        <FormSection
                            icon={Contact}
                            title="Coordonnées"
                            description="Pour contacter le service scolarité de l’établissement."
                        >
                            <FormField
                                id="address"
                                label={labels.address}
                                wide
                                error={errors.address}
                            >
                                <div className="relative">
                                    <MapPin
                                        className="pointer-events-none absolute top-3.5 left-3.5 size-5 text-muted-foreground"
                                        aria-hidden="true"
                                    />
                                    <textarea
                                        id="address"
                                        rows={3}
                                        value={form.data.address}
                                        onChange={(e) =>
                                            form.setData(
                                                'address',
                                                e.target.value,
                                            )
                                        }
                                        maxLength={2000}
                                        placeholder="Campus Ambondrona, BP 652, Mahajanga 401"
                                        aria-invalid={Boolean(errors.address)}
                                        className="flex w-full rounded-md border border-input bg-transparent py-3 pr-3 pl-11 text-base shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive dark:bg-input/30"
                                    />
                                </div>
                            </FormField>
                            <FormField
                                id="phone"
                                label={labels.phone}
                                error={errors.phone}
                            >
                                <IconInput icon={Phone}>
                                    <Input
                                        id="phone"
                                        type="tel"
                                        inputMode="tel"
                                        value={form.data.phone}
                                        onChange={(e) =>
                                            form.setData(
                                                'phone',
                                                e.target.value,
                                            )
                                        }
                                        maxLength={30}
                                        placeholder="034 12 345 67"
                                        aria-invalid={Boolean(errors.phone)}
                                        className={cn(big, 'pl-11')}
                                    />
                                </IconInput>
                            </FormField>
                            <FormField
                                id="email"
                                label={labels.email}
                                error={errors.email}
                            >
                                <IconInput icon={AtSign}>
                                    <Input
                                        id="email"
                                        type="email"
                                        inputMode="email"
                                        value={form.data.email}
                                        onChange={(e) =>
                                            form.setData(
                                                'email',
                                                e.target.value,
                                            )
                                        }
                                        maxLength={255}
                                        placeholder="scolarite@etablissement.mg"
                                        aria-invalid={Boolean(errors.email)}
                                        className={cn(big, 'pl-11')}
                                    />
                                </IconInput>
                            </FormField>
                        </FormSection>

                        <FormSection
                            icon={Power}
                            title="Statut"
                            description="Un établissement inactif n’est plus proposé pour les nouvelles situations annuelles."
                            columns={1}
                        >
                            <button
                                type="button"
                                role="switch"
                                aria-checked={form.data.is_active}
                                onClick={() =>
                                    form.setData(
                                        'is_active',
                                        !form.data.is_active,
                                    )
                                }
                                className={cn(
                                    'flex w-full items-center justify-between gap-4 rounded-xl border p-4 text-left transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                    form.data.is_active
                                        ? 'border-success/40 bg-success-soft/50'
                                        : 'bg-muted/40',
                                )}
                            >
                                <span>
                                    <span className="block text-base font-semibold text-foreground">
                                        {form.data.is_active
                                            ? 'Établissement actif'
                                            : 'Établissement inactif'}
                                    </span>
                                    <span className="block text-sm text-muted-foreground">
                                        {form.data.is_active
                                            ? 'Disponible pour les situations annuelles, imports et comptes.'
                                            : 'Conservé pour l’historique, masqué des nouveaux choix.'}
                                    </span>
                                </span>
                                <span
                                    className={cn(
                                        'relative inline-flex h-7 w-12 shrink-0 items-center rounded-full transition-colors',
                                        form.data.is_active
                                            ? 'bg-success'
                                            : 'bg-input',
                                    )}
                                    aria-hidden="true"
                                >
                                    <span
                                        className={cn(
                                            'block size-6 rounded-full bg-white shadow-sm transition-transform',
                                            form.data.is_active
                                                ? 'translate-x-[22px]'
                                                : 'translate-x-0.5',
                                        )}
                                    />
                                </span>
                            </button>
                        </FormSection>

                        <FormActions
                            processing={form.processing}
                            submitLabel={
                                record ? 'Enregistrer' : 'Créer l’établissement'
                            }
                            cancelHref="/references/establishments"
                        />
                    </div>

                    <aside className="hidden xl:block">
                        <div className="sticky top-24 space-y-3">
                            <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                Aperçu
                            </p>
                            <div className="surface overflow-hidden">
                                <div className="relative h-20 bg-sidebar">
                                    <div
                                        className="bg-grid absolute inset-0 text-white opacity-30"
                                        aria-hidden="true"
                                    />
                                </div>
                                <div className="relative -mt-10 space-y-3 px-5 pb-5">
                                    <LogoTile
                                        src={shownLogo}
                                        code={form.data.code}
                                        className="size-20 rounded-2xl text-xl ring-4 ring-card"
                                    />
                                    <div className="space-y-1.5">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <span className="rounded-md border bg-muted/60 px-2 py-0.5 font-mono text-xs font-bold">
                                                {form.data.code || 'SIGLE'}
                                            </span>
                                            {type && (
                                                <ToneBadge tone="primary">
                                                    {type}
                                                </ToneBadge>
                                            )}
                                        </div>
                                        <p className="font-heading text-lg leading-snug font-bold text-foreground">
                                            {form.data.name ||
                                                'Nom de l’établissement'}
                                        </p>
                                    </div>
                                    {(form.data.phone ||
                                        form.data.email ||
                                        form.data.address) && (
                                        <ul className="space-y-1.5 border-t pt-3 text-sm text-muted-foreground">
                                            {form.data.address && (
                                                <li className="flex gap-2">
                                                    <MapPin
                                                        className="mt-0.5 size-4 shrink-0"
                                                        aria-hidden="true"
                                                    />
                                                    <span className="line-clamp-2">
                                                        {form.data.address}
                                                    </span>
                                                </li>
                                            )}
                                            {form.data.phone && (
                                                <li className="flex gap-2">
                                                    <Phone
                                                        className="mt-0.5 size-4 shrink-0"
                                                        aria-hidden="true"
                                                    />
                                                    {form.data.phone}
                                                </li>
                                            )}
                                            {form.data.email && (
                                                <li className="flex gap-2">
                                                    <AtSign
                                                        className="mt-0.5 size-4 shrink-0"
                                                        aria-hidden="true"
                                                    />
                                                    <span className="truncate">
                                                        {form.data.email}
                                                    </span>
                                                </li>
                                            )}
                                        </ul>
                                    )}
                                    <ToneBadge
                                        tone={
                                            form.data.is_active
                                                ? 'success'
                                                : 'neutral'
                                        }
                                    >
                                        {form.data.is_active
                                            ? 'Actif'
                                            : 'Inactif'}
                                    </ToneBadge>
                                </div>
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Le logo et le sigle s’affichent dans les listes
                                et les fiches.
                            </p>
                        </div>
                    </aside>
                </form>
            </PageBody>
        </>
    );
}

function IconInput({
    icon: Icon,
    children,
}: {
    icon: LucideIcon;
    children: React.ReactNode;
}) {
    return (
        <div className="relative">
            <Icon
                className="pointer-events-none absolute top-1/2 left-3.5 z-10 size-5 -translate-y-1/2 text-muted-foreground"
                aria-hidden="true"
            />
            {children}
        </div>
    );
}

EstablishmentForm.layout = {
    breadcrumbs: [
        { title: 'Référentiels', href: '/references/establishments' },
    ],
};
