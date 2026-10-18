import { Head, useForm } from '@inertiajs/react';
import {
    CircleCheck,
    CircleDashed,
    Download,
    FileUp,
    Landmark,
    Lightbulb,
    ListChecks,
    ShieldCheck,
    PanelRightClose,
    PanelRightOpen,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import type { FormEvent } from 'react';
import { FileDropzone } from '@/components/forms/file-dropzone';
import {
    ErrorSummary,
    FormActions,
    FormField,
    FormSection,
} from '@/components/forms/form-kit';
import { UploadIllustration } from '@/components/illustrations';
import { PageBody, PageHeader } from '@/components/page-header';
import type { Options } from '@/components/students/types';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';
import { ImportStepper } from './import-ui';
import { kindTexts, templateHref } from './kinds';
import type { ImportColumn, ImportKind, ImportKindOption } from './types';

const HELP_KEY = 'imports.create.help';

export default function ImportCreate({
    options,
    maxKilobytes,
    maxRows,
    columns,
    kinds = [],
    initialKind = null,
}: {
    options: Options;
    maxKilobytes: number;
    maxRows: number;
    columns: ImportColumn[];
    /** Files the actor may import; more than one only for central offices. */
    kinds?: ImportKindOption[];
    /** Kind chosen from the history page (?kind=), when the actor may import it. */
    initialKind?: ImportKind | null;
}) {
    const form = useForm<{
        kind: ImportKind;
        file: File | null;
        establishment_id: string;
        academic_year_id: string;
    }>({
        kind:
            kinds.find((item) => item.value === initialKind)?.value ??
            kinds[0]?.value ??
            'establishment',
        file: null,
        establishment_id: String(options.fixedEstablishmentId ?? ''),
        academic_year_id: String(
            options.academicYears.find((y) => y.is_current && y.is_active)
                ?.id ?? '',
        ),
    });
    // Imports always go to the active current year: shown, not chosen.
    const year = options.academicYears.find(
        (item) => item.is_current && item.is_active,
    );
    // Help panel pinned on the right by default; the choice is remembered on this browser.
    const [pinned, setPinned] = useState(false);
    // Read after hydration (server rendering knows neither the screen nor the stored choice).
    useEffect(() => {
        try {
            setPinned(
                window.matchMedia('(min-width: 64rem)').matches &&
                    window.localStorage.getItem(HELP_KEY) !== 'hidden',
            );
        } catch {
            setPinned(window.matchMedia('(min-width: 64rem)').matches);
        }
    }, []);
    const togglePinned = () => {
        setPinned((value) => {
            try {
                window.localStorage.setItem(
                    HELP_KEY,
                    value ? 'hidden' : 'pinned',
                );
            } catch {
                // Storage unavailable (private window): the choice lasts for this page only.
            }
            return !value;
        });
    };
    const errors = form.errors as Record<string, string | undefined>;
    const submit = (event: FormEvent) => {
        event.preventDefault();
        // Central files name the establishment on each row: no establishment is sent.
        form.transform(({ establishment_id, ...data }) =>
            data.kind === 'establishment'
                ? { ...data, establishment_id }
                : data,
        );
        form.post('/imports', { forceFormData: true });
    };
    const kind = form.data.kind;
    const text = kindTexts[kind];
    const central = kind !== 'establishment';
    // A global actor may import a file covering several establishments (empty = read on each row).
    const global = options.fixedEstablishmentId === null;
    const ready =
        form.data.file !== null &&
        (central || global || form.data.establishment_id !== '') &&
        form.data.academic_year_id !== '';
    const expected =
        kinds.find((item) => item.value === kind)?.columns ?? columns;
    const required = expected.filter((column) => column.required);
    const optional = expected.filter((column) => !column.required);

    return (
        <PageBody
            className={cn(
                'pb-0 transition-[padding] duration-300 motion-reduce:transition-none md:pb-0 lg:pb-0',
                // The open panel covers the right edge: the page makes room for it on wide screens.
                pinned && 'lg:pr-[25.5rem]',
            )}
        >
            <Head title="Importer un fichier" />
            <PageHeader
                icon={FileUp}
                title="Importer des étudiants"
                description={text.intro}
                actions={
                    <div className="flex flex-wrap items-center gap-3">
                        <p className="text-sm text-muted-foreground">
                            Année universitaire{' '}
                            <span className="font-semibold text-foreground">
                                {year?.name ?? 'non définie'}
                            </span>
                        </p>
                        <Button variant="outline" asChild>
                            <a href={templateHref(kind)}>
                                <Download aria-hidden="true" />
                                Télécharger le modèle Excel
                            </a>
                        </Button>
                        <Button
                            type="button"
                            variant={pinned ? 'secondary' : 'outline'}
                            onClick={togglePinned}
                            aria-expanded={pinned}
                            aria-controls="import-help"
                        >
                            {pinned ? (
                                <PanelRightClose aria-hidden="true" />
                            ) : (
                                <PanelRightOpen aria-hidden="true" />
                            )}
                            {pinned ? 'Masquer l’aide' : 'Afficher l’aide'}
                        </Button>
                    </div>
                }
            />
            {!year && (
                <p
                    role="alert"
                    className="rounded-lg border border-destructive/30 px-4 py-3 text-sm text-destructive"
                >
                    Aucune année universitaire active n’est définie comme
                    courante : l’administrateur doit en activer une avant tout
                    import.
                </p>
            )}
            <ImportStepper current={0} />

            <form onSubmit={submit} noValidate className="space-y-6">
                <ErrorSummary
                    errors={errors}
                    labels={{
                        kind: 'Type de fichier',
                        establishment_id: 'Établissement',
                        academic_year_id: 'Année universitaire',
                        file: 'Fichier',
                    }}
                />
                <div className="min-w-0 space-y-6">
                    <FormSection
                        icon={Landmark}
                        title={
                            kinds.length > 1
                                ? 'Type de fichier et destination'
                                : 'Destination'
                        }
                        description={
                            kinds.length > 1
                                ? 'Choisissez le fichier que vous importez, puis l’établissement concerné.'
                                : global
                                  ? 'Les situations de l’année seront créées dans l’établissement choisi, ou dans celui indiqué sur chaque ligne du fichier.'
                                  : 'Les situations de l’année seront créées dans votre établissement.'
                        }
                        columns={1}
                    >
                        {kinds.length > 1 && (
                            <fieldset className="space-y-2">
                                <legend className="mb-2 text-sm font-medium text-foreground">
                                    Type de fichier
                                </legend>
                                <div
                                    className={cn(
                                        'grid divide-y overflow-hidden rounded-lg border md:divide-x md:divide-y-0',
                                        kinds.length > 2
                                            ? 'md:grid-cols-3'
                                            : 'md:grid-cols-2',
                                    )}
                                >
                                    {kinds.map((item) => {
                                        const checked = item.value === kind;
                                        return (
                                            <label
                                                key={item.value}
                                                className={cn(
                                                    'flex cursor-pointer items-start gap-3 p-4 transition-colors motion-reduce:transition-none',
                                                    checked
                                                        ? 'bg-accent/60'
                                                        : 'hover:bg-muted/40',
                                                )}
                                            >
                                                <input
                                                    type="radio"
                                                    name="kind"
                                                    value={item.value}
                                                    checked={checked}
                                                    onChange={() =>
                                                        form.setData(
                                                            'kind',
                                                            item.value,
                                                        )
                                                    }
                                                    className="mt-0.5 size-4 shrink-0 accent-primary"
                                                />
                                                <span className="min-w-0 space-y-1">
                                                    <span className="block text-sm font-semibold text-foreground">
                                                        {kindTexts[item.value]
                                                            ?.label ??
                                                            item.label}
                                                    </span>
                                                    <span className="block text-xs text-muted-foreground">
                                                        {
                                                            kindTexts[
                                                                item.value
                                                            ]?.sentence
                                                        }
                                                    </span>
                                                </span>
                                            </label>
                                        );
                                    })}
                                </div>
                            </fieldset>
                        )}
                        {errors.kind && (
                            <p className="text-sm text-destructive">
                                {errors.kind}
                            </p>
                        )}
                        {central ? (
                            <p className="text-sm text-muted-foreground">
                                <span className="font-medium text-foreground">
                                    Établissement :
                                </span>{' '}
                                indiqué sur chaque ligne du fichier (colonne «
                                Institution Code »), rien à choisir ici.
                            </p>
                        ) : (
                            <FormField
                                id="establishment_id"
                                label="Établissement"
                                required={!global}
                                hint={
                                    global && form.data.establishment_id === ''
                                        ? 'Chaque ligne indique son établissement (colonne « Institution Code » ou « École / Institut »).'
                                        : undefined
                                }
                                error={errors.establishment_id}
                            >
                                <Select
                                    value={
                                        form.data.establishment_id ||
                                        (global ? 'all' : undefined)
                                    }
                                    onValueChange={(value) =>
                                        form.setData(
                                            'establishment_id',
                                            value === 'all' ? '' : value,
                                        )
                                    }
                                    disabled={
                                        options.fixedEstablishmentId !== null
                                    }
                                >
                                    <SelectTrigger
                                        id="establishment_id"
                                        className="w-full"
                                    >
                                        <SelectValue placeholder="Choisir un établissement" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {global && (
                                            <SelectItem value="all">
                                                Tous les établissements (selon
                                                le fichier)
                                            </SelectItem>
                                        )}
                                        {options.establishments
                                            .filter((item) => item.is_active)
                                            .map((item) => (
                                                <SelectItem
                                                    key={item.id}
                                                    value={String(item.id)}
                                                >
                                                    {item.name}
                                                </SelectItem>
                                            ))}
                                    </SelectContent>
                                </Select>
                            </FormField>
                        )}
                    </FormSection>
                    <FormSection
                        icon={FileUp}
                        title="Fichier"
                        description={`Le modèle téléchargeable, ou une feuille avec ses en-têtes ; ${maxRows.toLocaleString('fr-FR')} lignes au plus.`}
                        columns={1}
                    >
                        <div className="relative overflow-hidden rounded-xl bg-accent/50 p-4 sm:p-5">
                            <div
                                className="bg-grid absolute inset-0 text-primary opacity-50"
                                aria-hidden="true"
                            />
                            <div className="relative flex flex-col gap-4 sm:flex-row sm:items-center">
                                <UploadIllustration className="mx-auto h-28 w-auto shrink-0 sm:mx-0 sm:h-32" />
                                <div className="min-w-0 flex-1 space-y-3">
                                    <div className="space-y-1">
                                        <p className="font-heading text-sm font-bold text-foreground">
                                            Déposez votre liste d’étudiants
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            Le fichier est d’abord lu puis
                                            vérifié ligne par ligne. Vous
                                            associerez ses colonnes à l’étape
                                            suivante.
                                        </p>
                                    </div>
                                    <FormField
                                        id="file"
                                        label="Fichier XLSX ou CSV"
                                        required
                                        error={errors.file}
                                    >
                                        <div className="rounded-lg bg-card">
                                            <FileDropzone
                                                id="file"
                                                file={form.data.file}
                                                onChange={(file) =>
                                                    form.setData('file', file)
                                                }
                                                accept={['xlsx', 'csv']}
                                                maxKilobytes={maxKilobytes}
                                                invalid={Boolean(errors.file)}
                                            />
                                        </div>
                                    </FormField>
                                </div>
                            </div>
                        </div>
                        {form.progress && (
                            <div className="rounded-lg border bg-muted/40 p-3">
                                <div className="flex justify-between text-xs font-medium text-muted-foreground">
                                    <span>Téléversement</span>
                                    <span className="tabular-nums">
                                        {form.progress.percentage} %
                                    </span>
                                </div>
                                <div
                                    className="mt-2 h-2 overflow-hidden rounded-full bg-muted"
                                    role="progressbar"
                                    aria-valuenow={form.progress.percentage}
                                    aria-valuemin={0}
                                    aria-valuemax={100}
                                    aria-label="Progression du téléversement"
                                >
                                    <div
                                        className="h-2 rounded-full bg-primary transition-all motion-reduce:transition-none"
                                        style={{
                                            width: `${form.progress.percentage ?? 0}%`,
                                        }}
                                    />
                                </div>
                            </div>
                        )}
                    </FormSection>
                </div>
                <FormActions
                    processing={form.processing}
                    submitLabel="Lire le fichier"
                    cancelHref="/imports"
                    note={
                        ready
                            ? 'Étape suivante : association des colonnes du fichier.'
                            : central
                              ? 'Choisissez le fichier pour continuer.'
                              : 'Choisissez la destination et le fichier pour continuer.'
                    }
                />
            </form>
            {/* Help panel on the right edge: shown or hidden with the header button, not a modal. */}
            <aside
                id="import-help"
                aria-label="Aide à l’import"
                inert={!pinned}
                className={cn(
                    'fixed top-16 right-0 bottom-0 z-30 flex w-full flex-col border-l bg-background shadow-elevated transition-transform duration-300 ease-out motion-reduce:transition-none sm:w-[24rem]',
                    pinned ? 'translate-x-0' : 'translate-x-full',
                )}
            >
                <div className="flex items-center justify-between gap-2 border-b px-5 py-3">
                    <p className="font-heading text-sm font-bold text-foreground">
                        Aide à l’import
                    </p>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        onClick={togglePinned}
                        aria-label="Masquer l’aide"
                        title="Masquer l’aide"
                    >
                        <PanelRightClose aria-hidden="true" />
                    </Button>
                </div>
                <div className="min-h-0 flex-1 space-y-4 overflow-y-auto p-4">
                    <section
                        className="surface p-5"
                        aria-labelledby="import-tips"
                    >
                        <div className="flex items-center gap-3">
                            <span
                                className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-warning-soft text-warning"
                                aria-hidden="true"
                            >
                                <Lightbulb className="size-[18px]" />
                            </span>
                            <h2
                                id="import-tips"
                                className="text-base font-bold text-foreground"
                            >
                                Avant d’importer
                            </h2>
                        </div>
                        <ul className="mt-4 space-y-2.5 text-sm">
                            {text.tips.map((tip) => (
                                <li key={tip} className="flex gap-2.5">
                                    <CircleCheck
                                        className="mt-0.5 size-4 shrink-0 text-success"
                                        aria-hidden="true"
                                    />
                                    <span className="text-muted-foreground">
                                        {tip}
                                    </span>
                                </li>
                            ))}
                        </ul>
                        <Button
                            variant="outline"
                            className="mt-5 w-full"
                            asChild
                        >
                            <a href={templateHref(kind)}>
                                <Download aria-hidden="true" />
                                Télécharger le modèle Excel
                            </a>
                        </Button>
                    </section>
                    <section
                        className="surface p-5"
                        aria-labelledby="import-columns"
                    >
                        <div className="flex items-center gap-3">
                            <span
                                className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-accent text-primary"
                                aria-hidden="true"
                            >
                                <ListChecks className="size-[18px]" />
                            </span>
                            <div className="min-w-0">
                                <h2
                                    id="import-columns"
                                    className="text-base font-bold text-foreground"
                                >
                                    Colonnes attendues
                                </h2>
                                <p className="text-xs text-muted-foreground">
                                    Seules les colonnes obligatoires doivent
                                    être remplies ; les autres peuvent rester
                                    vides.
                                </p>
                            </div>
                        </div>
                        {required.length > 0 && (
                            <div className="mt-4">
                                <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                    Obligatoires
                                </p>
                                <ul className="mt-2 space-y-1.5">
                                    {required.map((column) => (
                                        <li
                                            key={column.label}
                                            className="flex items-center gap-2 rounded-lg bg-accent/60 px-3 py-2 text-sm font-semibold text-foreground"
                                        >
                                            <CircleCheck
                                                className="size-4 shrink-0 text-primary"
                                                aria-hidden="true"
                                            />
                                            <span className="min-w-0 flex-1 truncate">
                                                {column.label}
                                            </span>
                                            <span className="text-xs font-medium text-primary">
                                                Obligatoire
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        )}
                        {optional.length > 0 && (
                            <div className="mt-4">
                                <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                    Facultatives
                                </p>
                                <ul className="mt-2 grid gap-x-3 gap-y-1.5 sm:grid-cols-2 lg:grid-cols-1 xl:grid-cols-2">
                                    {optional.map((column) => (
                                        <li
                                            key={column.label}
                                            className="flex min-w-0 items-center gap-2 text-sm text-muted-foreground"
                                        >
                                            <CircleDashed
                                                className="size-3.5 shrink-0"
                                                aria-hidden="true"
                                            />
                                            <span className="truncate">
                                                {column.label}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        )}
                    </section>
                    <p className="flex items-start gap-2 px-1 text-xs text-muted-foreground">
                        <ShieldCheck
                            className="mt-px size-4 shrink-0 text-success"
                            aria-hidden="true"
                        />
                        Seules les lignes valides sont importées ; les données
                        officielles contradictoires ne sont jamais écrasées.
                    </p>
                </div>
            </aside>
        </PageBody>
    );
}
ImportCreate.layout = {
    breadcrumbs: [
        { title: 'Imports', href: '/imports' },
        { title: 'Fichier', href: '/imports/create' },
    ],
};
