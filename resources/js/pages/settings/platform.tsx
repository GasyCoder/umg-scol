import { Head, useForm, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import type { DragEvent, FormEvent, ReactNode } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import { BrandMark } from '@/components/brand-mark';
import { DdbLabel } from '@/components/scholarships/ddb-label';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';

type Texts = {
    institution_name: string;
    scol_name: string;
    bourse_name: string;
    support_email: string;
};
type FileKind = 'logo' | 'favicon';

const fileRules: Record<
    FileKind,
    {
        types: string[];
        max: number;
        accept: string;
        tooBig: string;
        badType: string;
    }
> = {
    logo: {
        types: ['image/png', 'image/jpeg', 'image/webp'],
        max: 1024 * 1024,
        accept: '.png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp',
        tooBig: 'Le logo dépasse 1 Mo.',
        badType: 'Le logo doit être une image PNG, JPG ou WebP.',
    },
    favicon: {
        types: ['image/png'],
        max: 256 * 1024,
        accept: '.png,image/png',
        tooBig: 'Le favicon dépasse 256 Ko.',
        badType: 'Le favicon doit être une image PNG.',
    },
};

type DdbSettings = {
    pattern: string;
    digits: number;
    defaultPattern: string;
    defaultDigits: number;
    tokens: Record<string, string>;
};

type LabelKey =
    | 'ddb_label_heading'
    | 'ddb_label_caption'
    | 'ddb_label_hint'
    | 'ddb_label_footer'
    | 'ddb_label_url';
type LabelSettings = {
    values: Record<LabelKey, string>;
    defaults: Record<LabelKey, string>;
    /** QR code of the saved link. */
    qr: string;
};

export default function PlatformSettings({
    values,
    defaults,
    ddb,
    labels,
    studentMails,
}: {
    values: Texts;
    defaults: Omit<Texts, 'support_email'>;
    ddb: DdbSettings;
    labels: LabelSettings;
    studentMails: StudentMailSettings;
}) {
    const { branding } = usePage().props;
    const form = useForm({
        ...values,
        logo: null as File | null,
        favicon: null as File | null,
        remove_logo: false,
        remove_favicon: false,
    });
    const [previews, setPreviews] = useState<Record<FileKind, string | null>>({
        logo: null,
        favicon: null,
    });
    const [fileErrors, setFileErrors] = useState<
        Record<FileKind, string | null>
    >({ logo: null, favicon: null });
    useEffect(
        () => () =>
            Object.values(previews).forEach(
                (url) => url && URL.revokeObjectURL(url),
            ),
        [previews],
    );

    const pick = (kind: FileKind, file: File | undefined) => {
        if (!file) return;
        const rule = fileRules[kind];
        const error = !rule.types.includes(file.type)
            ? rule.badType
            : file.size > rule.max
              ? rule.tooBig
              : null;
        setFileErrors((current) => ({ ...current, [kind]: error }));
        if (error) return;
        setPreviews((current) => ({
            ...current,
            [kind]: URL.createObjectURL(file),
        }));
        form.setData((data) => ({
            ...data,
            [kind]: file,
            [`remove_${kind}`]: false,
        }));
    };
    const remove = (kind: FileKind) => {
        setPreviews((current) => ({ ...current, [kind]: null }));
        setFileErrors((current) => ({ ...current, [kind]: null }));
        form.setData((data) => ({
            ...data,
            [kind]: null,
            [`remove_${kind}`]: Boolean(branding[`${kind}_url`]),
        }));
    };
    const shown = (kind: FileKind) =>
        previews[kind] ??
        (form.data[`remove_${kind}`] ? null : branding[`${kind}_url`]);

    const reset = () => {
        form.reset();
        form.clearErrors();
        setPreviews({ logo: null, favicon: null });
        setFileErrors({ logo: null, favicon: null });
    };
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.transform((data) => ({
            _method: 'put',
            institution_name: data.institution_name,
            scol_name: data.scol_name,
            bourse_name: data.bourse_name,
            support_email: data.support_email,
            remove_logo: data.remove_logo,
            remove_favicon: data.remove_favicon,
            ...(data.logo ? { logo: data.logo } : {}),
            ...(data.favicon ? { favicon: data.favicon } : {}),
        }));
        form.post('/settings/platform', {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                setPreviews({ logo: null, favicon: null });
                form.setDefaults({
                    ...form.data,
                    logo: null,
                    favicon: null,
                    remove_logo: false,
                    remove_favicon: false,
                });
                form.setData((data) => ({
                    ...data,
                    logo: null,
                    favicon: null,
                    remove_logo: false,
                    remove_favicon: false,
                }));
            },
        });
    };

    const scolName = form.data.scol_name.trim() || defaults.scol_name;
    const institution =
        form.data.institution_name.trim() || defaults.institution_name;
    const errors = form.errors as Record<string, string | undefined>;

    return (
        <>
            <Head title="Plateforme" />
            <form
                onSubmit={submit}
                noValidate
                className="rounded-xl border bg-card text-card-foreground"
            >
                <div className="border-b px-6 py-5">
                    <h1 className="text-lg font-semibold text-foreground">
                        Plateforme
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Nom et image de l’application, pour le portail du
                        personnel et le portail des étudiants.
                    </p>
                </div>

                <Row
                    label="Institution"
                    help="Affichée sous le nom de l’application."
                    htmlFor="institution_name"
                >
                    <TextInput
                        id="institution_name"
                        value={form.data.institution_name}
                        placeholder={defaults.institution_name}
                        maxLength={120}
                        error={errors.institution_name}
                        onChange={(value) =>
                            form.setData('institution_name', value)
                        }
                    />
                </Row>
                <Row
                    label="Portail du personnel"
                    help="Nom affiché dans le menu, à la connexion et dans l’onglet du navigateur."
                    htmlFor="scol_name"
                >
                    <TextInput
                        id="scol_name"
                        value={form.data.scol_name}
                        placeholder={defaults.scol_name}
                        maxLength={60}
                        error={errors.scol_name}
                        onChange={(value) => form.setData('scol_name', value)}
                    />
                </Row>
                <Row
                    label="Portail des étudiants"
                    help="Nom affiché en haut du portail étudiant."
                    htmlFor="bourse_name"
                >
                    <TextInput
                        id="bourse_name"
                        value={form.data.bourse_name}
                        placeholder={defaults.bourse_name}
                        maxLength={60}
                        error={errors.bourse_name}
                        onChange={(value) => form.setData('bourse_name', value)}
                    />
                </Row>
                <Row
                    label="Email de contact"
                    help="Facultatif. Indiqué aux étudiants en bas de page."
                    htmlFor="support_email"
                >
                    <TextInput
                        id="support_email"
                        type="email"
                        value={form.data.support_email}
                        placeholder="scolarite@exemple.mg"
                        maxLength={255}
                        error={errors.support_email}
                        onChange={(value) =>
                            form.setData('support_email', value)
                        }
                    />
                </Row>

                <Row
                    label="Logo"
                    help="PNG, JPG ou WebP, 1 Mo maximum. Sans logo, le sigle UMG est affiché."
                >
                    <FilePicker
                        kind="logo"
                        hasFile={Boolean(shown('logo'))}
                        fileName={form.data.logo?.name}
                        removing={form.data.remove_logo}
                        error={errors.logo ?? fileErrors.logo}
                        onPick={(file) => pick('logo', file)}
                        onRemove={() => remove('logo')}
                    >
                        <div className="flex w-full max-w-sm items-center gap-3 rounded-lg bg-sidebar px-4 py-3">
                            <BrandMark
                                logoUrl={shown('logo')}
                                className="size-10"
                            />
                            <div className="min-w-0 leading-tight">
                                <p className="truncate text-sm font-semibold text-white">
                                    {scolName}
                                </p>
                                <p className="truncate text-xs text-sidebar-muted">
                                    {institution}
                                </p>
                            </div>
                        </div>
                    </FilePicker>
                </Row>

                <Row
                    label="Favicon"
                    help="Icône de l’onglet du navigateur. Image PNG carrée, 512 px conseillé, 256 Ko maximum."
                >
                    <FilePicker
                        kind="favicon"
                        hasFile={Boolean(shown('favicon'))}
                        fileName={form.data.favicon?.name}
                        removing={form.data.remove_favicon}
                        error={errors.favicon ?? fileErrors.favicon}
                        onPick={(file) => pick('favicon', file)}
                        onRemove={() => remove('favicon')}
                    >
                        <div className="flex h-9 w-full max-w-sm items-center gap-2 rounded-t-md border border-b-0 bg-muted/50 px-3">
                            {shown('favicon') ? (
                                <img
                                    src={shown('favicon') ?? undefined}
                                    alt=""
                                    className="size-4 shrink-0"
                                />
                            ) : (
                                <AppLogoIcon
                                    className="size-4 shrink-0 fill-current text-primary"
                                    aria-hidden="true"
                                />
                            )}
                            <span className="truncate text-xs text-foreground">
                                {scolName}
                            </span>
                        </div>
                    </FilePicker>
                </Row>

                <div className="flex flex-col-reverse gap-3 rounded-b-xl border-t bg-muted/30 px-6 py-4 sm:flex-row sm:items-center sm:justify-end">
                    {form.recentlySuccessful && !form.isDirty && (
                        <p
                            role="status"
                            className="text-sm text-success-foreground sm:mr-auto"
                        >
                            Enregistré.
                        </p>
                    )}
                    <Button
                        type="button"
                        variant="outline"
                        onClick={reset}
                        disabled={!form.isDirty || form.processing}
                    >
                        Annuler
                    </Button>
                    <Button
                        type="submit"
                        disabled={!form.isDirty || form.processing}
                    >
                        {form.processing && <Spinner />}
                        Enregistrer
                    </Button>
                </div>
            </form>
            <DdbNumbering ddb={ddb} />
            <DdbLabelTexts
                labels={labels}
                institution={institution}
                pattern={ddb.pattern}
            />
            <StudentMailSwitches settings={studentMails} />
        </>
    );
}

/** Same rule as the server (DdbNumber::format), for the preview only. */
function formatDdb(pattern: string, digits: number, sequence: number) {
    return pattern
        .replaceAll('{NUM}', String(sequence).padStart(digits, '0'))
        .replaceAll('{ANNEE}', '2026')
        .replaceAll('{AU}', '2026-2027')
        .replaceAll('{ETAB}', 'FMED');
}

/** Pattern of the DDB numbers generated at the reception counter. */
function DdbNumbering({ ddb }: { ddb: DdbSettings }) {
    const form = useForm({ ddb_pattern: ddb.pattern, ddb_digits: ddb.digits });
    const errors = form.errors as Record<string, string | undefined>;
    const pattern = form.data.ddb_pattern.toUpperCase();
    const digits = Math.min(10, Math.max(1, Number(form.data.ddb_digits) || 1));
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put('/settings/platform/ddb', { preserveScroll: true });
    };

    return (
        <form
            onSubmit={submit}
            noValidate
            className="mt-6 rounded-xl border bg-card text-card-foreground"
        >
            <div className="border-b px-6 py-5">
                <h2 className="text-lg font-semibold text-foreground">
                    Numérotation des dossiers (DDB)
                </h2>
                <p className="mt-1 text-sm text-muted-foreground">
                    Numéro généré à la réception d’un dossier papier et recopié
                    au stylo sur l’étiquette du dossier. Les numéros déjà donnés
                    ne changent pas.
                </p>
            </div>
            <Row
                label="Modèle"
                help="Lettres, chiffres, séparateurs et balises. {NUM} est obligatoire."
                htmlFor="ddb_pattern"
            >
                <div className="space-y-3">
                    <TextInput
                        id="ddb_pattern"
                        value={form.data.ddb_pattern}
                        onChange={(value) => form.setData('ddb_pattern', value)}
                        placeholder={ddb.defaultPattern}
                        maxLength={60}
                        error={errors.ddb_pattern}
                    />
                    <ul className="flex flex-wrap gap-2">
                        {Object.entries(ddb.tokens).map(([token, label]) => (
                            <li key={token}>
                                <button
                                    type="button"
                                    title={label}
                                    className="rounded-md border px-2 py-1 font-mono text-xs text-foreground hover:bg-accent"
                                    onClick={() =>
                                        form.setData(
                                            'ddb_pattern',
                                            form.data.ddb_pattern + token,
                                        )
                                    }
                                >
                                    {token}
                                </button>
                            </li>
                        ))}
                    </ul>
                    <dl className="grid gap-1 text-xs text-muted-foreground">
                        {Object.entries(ddb.tokens).map(([token, label]) => (
                            <div key={token} className="flex gap-2">
                                <dt className="w-24 shrink-0 font-mono">
                                    {token}
                                </dt>
                                <dd>{label}</dd>
                            </div>
                        ))}
                    </dl>
                </div>
            </Row>
            <Row
                label="Chiffres du numéro d’ordre"
                help="Longueur de {NUM}, complétée par des zéros."
                htmlFor="ddb_digits"
            >
                <TextInput
                    id="ddb_digits"
                    type="number"
                    value={String(form.data.ddb_digits)}
                    onChange={(value) =>
                        form.setData('ddb_digits', Number(value))
                    }
                    placeholder={String(ddb.defaultDigits)}
                    maxLength={2}
                    error={errors.ddb_digits}
                />
            </Row>
            <Row label="Aperçu" help="Exemple pour le 124ᵉ dossier reçu.">
                <p className="font-mono text-2xl font-bold tracking-wide text-foreground">
                    {pattern.includes('{NUM}')
                        ? formatDdb(pattern, digits, 124)
                        : '—'}
                </p>
            </Row>
            <div className="flex flex-col-reverse gap-2 px-6 py-4 sm:flex-row sm:justify-end">
                <Button
                    type="button"
                    variant="ghost"
                    disabled={form.processing}
                    onClick={() =>
                        form.setData({
                            ddb_pattern: ddb.defaultPattern,
                            ddb_digits: ddb.defaultDigits,
                        })
                    }
                >
                    Revenir au modèle par défaut
                </Button>
                <Button
                    type="submit"
                    disabled={form.processing || !form.isDirty}
                >
                    {form.processing && <Spinner />}
                    Enregistrer la numérotation
                </Button>
            </div>
        </form>
    );
}

const labelFields: {
    key: LabelKey;
    label: string;
    help: string;
    maxLength: number;
}[] = [
    {
        key: 'ddb_label_heading',
        label: 'Ligne sous l’institution',
        help: 'Suivie de l’année universitaire sélectionnée.',
        maxLength: 40,
    },
    {
        key: 'ddb_label_caption',
        label: 'Titre du numéro',
        help: 'Au-dessus du préfixe et de la ligne à remplir.',
        maxLength: 30,
    },
    {
        key: 'ddb_label_hint',
        label: 'Mention sous le numéro',
        help: 'Courte indication pour l’étudiant.',
        maxLength: 50,
    },
    {
        key: 'ddb_label_footer',
        label: 'Pied de l’étiquette',
        help: 'Service émetteur, centré en bas.',
        maxLength: 60,
    },
    {
        key: 'ddb_label_url',
        label: 'Lien du QR code',
        help: 'Adresse complète ouverte au scan, aussi imprimée sous le code.',
        maxLength: 100,
    },
];

/** Texts printed on the blank DDB labels; an empty field restores its default. */
function DdbLabelTexts({
    labels,
    institution,
    pattern,
}: {
    labels: LabelSettings;
    institution: string;
    pattern: string;
}) {
    const form = useForm(labels.values);
    const errors = form.errors as Record<string, string | undefined>;
    const text = (key: LabelKey) =>
        form.data[key].trim() || labels.defaults[key];
    const [before, after = ''] = pattern.split('{NUM}');
    const resolve = (part: string) =>
        formatDdb(part, 1, 0).replaceAll('FMED', '____');
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put('/settings/platform/labels', {
            preserveScroll: true,
            onSuccess: () => form.setDefaults(),
        });
    };

    return (
        <form
            onSubmit={submit}
            noValidate
            className="mt-6 rounded-xl border bg-card text-card-foreground"
        >
            <div className="border-b px-6 py-5">
                <h2 className="text-lg font-semibold text-foreground">
                    Étiquettes DDB
                </h2>
                <p className="mt-1 text-sm text-muted-foreground">
                    Textes imprimés sur la fiche d’étiquettes vierges de la
                    réception. Un champ vide reprend le texte par défaut.
                </p>
            </div>
            {labelFields.map((field) => (
                <Row
                    key={field.key}
                    label={field.label}
                    help={field.help}
                    htmlFor={field.key}
                >
                    <TextInput
                        id={field.key}
                        type={field.key === 'ddb_label_url' ? 'url' : 'text'}
                        value={form.data[field.key]}
                        placeholder={labels.defaults[field.key]}
                        maxLength={field.maxLength}
                        error={errors[field.key]}
                        onChange={(value) => form.setData(field.key, value)}
                    />
                </Row>
            ))}
            <Row
                label="Aperçu"
                help="Taille réelle. Le QR code suit le lien enregistré."
            >
                <div className="overflow-x-auto">
                    <DdbLabel
                        texts={{
                            institution,
                            heading: text('ddb_label_heading'),
                            caption: text('ddb_label_caption'),
                            hint: text('ddb_label_hint'),
                            footer: text('ddb_label_footer'),
                            urlLabel: text('ddb_label_url')
                                .replace(/^https?:\/\//, '')
                                .replace(/\/+$/, ''),
                        }}
                        academicYear="2026-2027"
                        prefix={resolve(before)}
                        suffix={resolve(after)}
                        qr={labels.qr}
                    />
                </div>
            </Row>
            <div className="flex flex-col-reverse gap-2 px-6 py-4 sm:flex-row sm:justify-end">
                <Button
                    type="button"
                    variant="ghost"
                    disabled={form.processing}
                    onClick={() =>
                        form.setData({
                            ddb_label_heading: '',
                            ddb_label_caption: '',
                            ddb_label_hint: '',
                            ddb_label_footer: '',
                            ddb_label_url: '',
                        })
                    }
                >
                    Revenir aux textes par défaut
                </Button>
                <Button
                    type="submit"
                    disabled={form.processing || !form.isDirty}
                >
                    {form.processing && <Spinner />}
                    Enregistrer les étiquettes
                </Button>
            </div>
        </form>
    );
}

type StudentMailSettings = {
    values: Record<string, boolean>;
    labels: Record<string, string>;
};

/** When each email is sent; the student must have an email on the file. */
const studentMailHelp: Record<string, string> = {
    mail_student_registered:
        'À la création d’une fiche avec le formulaire (jamais lors d’un import de liste).',
    mail_scholarship_received:
        'À l’enregistrement de la réception du dossier papier, avec le N° DDB.',
};

/** Emails sent to the official address of a student file, switched on or off one by one. */
function StudentMailSwitches({ settings }: { settings: StudentMailSettings }) {
    const form = useForm<Record<string, boolean>>(settings.values);
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put('/settings/platform/student-mails', {
            preserveScroll: true,
            onSuccess: () => form.setDefaults(),
        });
    };

    return (
        <form
            onSubmit={submit}
            noValidate
            className="mt-6 rounded-xl border bg-card text-card-foreground"
        >
            <div className="border-b px-6 py-5">
                <h2 className="text-lg font-semibold text-foreground">
                    Emails aux étudiants
                </h2>
                <p className="mt-1 text-sm text-muted-foreground">
                    Envoyés à l’adresse email de la fiche, quand elle est
                    renseignée, avec le lien du portail étudiant.
                </p>
            </div>
            {Object.entries(settings.labels).map(([key, label]) => (
                <Row
                    key={key}
                    label={label}
                    help={studentMailHelp[key] ?? ''}
                    htmlFor={key}
                >
                    <label
                        htmlFor={key}
                        className="flex cursor-pointer items-center gap-3 text-sm text-foreground"
                    >
                        <Checkbox
                            id={key}
                            checked={form.data[key]}
                            onCheckedChange={(checked) =>
                                form.setData(key, checked === true)
                            }
                        />
                        {form.data[key] ? 'Envoyé' : 'Désactivé'}
                    </label>
                </Row>
            ))}
            <div className="flex justify-end px-6 py-4">
                <Button
                    type="submit"
                    disabled={form.processing || !form.isDirty}
                >
                    {form.processing && <Spinner />}
                    Enregistrer les emails
                </Button>
            </div>
        </form>
    );
}

/** One setting: label and short help on the left, the control on the right (stacked on mobile). */
function Row({
    label,
    help,
    htmlFor,
    children,
}: {
    label: string;
    help: string;
    htmlFor?: string;
    children: ReactNode;
}) {
    const Title = htmlFor ? 'label' : 'p';
    return (
        <div className="grid gap-3 border-b px-6 py-5 lg:grid-cols-[minmax(14rem,20rem)_minmax(0,1fr)] lg:gap-10">
            <div>
                <Title
                    {...(htmlFor ? { htmlFor } : {})}
                    className="text-sm font-medium text-foreground"
                >
                    {label}
                </Title>
                <p className="mt-1 text-sm text-muted-foreground">{help}</p>
            </div>
            <div className="min-w-0">{children}</div>
        </div>
    );
}

function TextInput({
    id,
    value,
    onChange,
    placeholder,
    maxLength,
    error,
    type = 'text',
}: {
    id: string;
    value: string;
    onChange: (value: string) => void;
    placeholder: string;
    maxLength: number;
    error?: string;
    type?: string;
}) {
    return (
        <div className="max-w-xl space-y-1.5">
            <Input
                id={id}
                type={type}
                value={value}
                onChange={(e) => onChange(e.target.value)}
                placeholder={placeholder}
                maxLength={maxLength}
                autoComplete="off"
                aria-invalid={Boolean(error)}
                aria-describedby={error ? `${id}-error` : undefined}
                className="h-11 text-base md:text-base"
            />
            {error && (
                <p
                    id={`${id}-error`}
                    className="text-sm text-danger-foreground"
                >
                    {error}
                </p>
            )}
        </div>
    );
}

/** Preview, then plain buttons; the preview also accepts a dropped file. */
function FilePicker({
    kind,
    hasFile,
    fileName,
    removing,
    error,
    onPick,
    onRemove,
    children,
}: {
    kind: FileKind;
    hasFile: boolean;
    fileName?: string;
    removing: boolean;
    error: string | null | undefined;
    onPick: (file: File | undefined) => void;
    onRemove: () => void;
    children: ReactNode;
}) {
    const input = useRef<HTMLInputElement>(null);
    const [dragging, setDragging] = useState(false);
    const onDrop = (event: DragEvent<HTMLDivElement>) => {
        event.preventDefault();
        setDragging(false);
        onPick(event.dataTransfer.files[0]);
    };
    return (
        <div className="space-y-3">
            <div
                onDragOver={(e) => {
                    e.preventDefault();
                    setDragging(true);
                }}
                onDragLeave={() => setDragging(false)}
                onDrop={onDrop}
                className={cn(
                    'rounded-lg transition-shadow',
                    dragging &&
                        'ring-2 ring-primary ring-offset-2 ring-offset-card',
                )}
            >
                {children}
            </div>
            <div className="flex flex-wrap items-center gap-2">
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={() => input.current?.click()}
                >
                    {hasFile ? 'Changer…' : 'Choisir un fichier…'}
                </Button>
                {hasFile && (
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={onRemove}
                        className="text-danger-foreground hover:bg-danger-soft hover:text-danger-foreground"
                    >
                        Retirer
                    </Button>
                )}
                <span className="text-sm text-muted-foreground">
                    {fileName
                        ? `${fileName} (non enregistré)`
                        : removing
                          ? 'Sera retiré à l’enregistrement'
                          : 'ou glissez une image sur l’aperçu'}
                </span>
            </div>
            <input
                ref={input}
                id={`${kind}-input`}
                type="file"
                accept={fileRules[kind].accept}
                className="sr-only"
                aria-label={
                    kind === 'logo' ? 'Choisir le logo' : 'Choisir le favicon'
                }
                onChange={(e) => {
                    onPick(e.target.files?.[0]);
                    e.target.value = '';
                }}
            />
            {error && (
                <p role="alert" className="text-sm text-danger-foreground">
                    {error}
                </p>
            )}
        </div>
    );
}

PlatformSettings.layout = {
    breadcrumbs: [{ title: 'Plateforme', href: '/settings/platform' }],
};
