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
} from 'lucide-react';
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
import { ImportStepper } from './import-ui';

const tips = [
    'Partez du modèle : les colonnes sont reconnues automatiquement.',
    'Enregistrez matricules, CIN et numéros BAC comme du texte pour garder les zéros initiaux.',
    'Pas de formules ni de cellules fusionnées.',
    'Le fichier déclare des étudiants déjà inscrits : aucune inscription n’est effectuée.',
];

export default function ImportCreate({
    options,
    maxKilobytes,
    maxRows,
    columns,
}: {
    options: Options;
    maxKilobytes: number;
    maxRows: number;
    columns: { label: string; required: boolean }[];
}) {
    const form = useForm<{
        file: File | null;
        establishment_id: string;
        academic_year_id: string;
    }>({
        file: null,
        establishment_id: String(options.fixedEstablishmentId ?? ''),
        academic_year_id: String(
            options.academicYears.find((y) => y.is_current && y.is_active)
                ?.id ?? '',
        ),
    });
    const errors = form.errors as Record<string, string | undefined>;
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post('/imports', { forceFormData: true });
    };
    const ready =
        form.data.file !== null &&
        form.data.establishment_id !== '' &&
        form.data.academic_year_id !== '';
    const required = columns.filter((column) => column.required);
    const optional = columns.filter((column) => !column.required);

    return (
        <PageBody className="pb-0 md:pb-0 lg:pb-0">
            <Head title="Importer un fichier" />
            <PageHeader
                icon={FileUp}
                title="Importer des étudiants"
                description="Déclarez en une fois des étudiants déjà régulièrement inscrits. Rien n’est enregistré avant votre confirmation."
                actions={
                    <Button variant="outline" asChild>
                        <a href="/imports/template">
                            <Download aria-hidden="true" />
                            Télécharger le modèle
                        </a>
                    </Button>
                }
            />
            <ImportStepper current={0} />

            <form onSubmit={submit} noValidate className="space-y-6">
                <ErrorSummary
                    errors={errors}
                    labels={{
                        establishment_id: 'Établissement',
                        academic_year_id: 'Année universitaire',
                        file: 'Fichier',
                    }}
                />
                <div className="grid gap-6 xl:grid-cols-3">
                    <div className="min-w-0 space-y-6 xl:col-span-2">
                        <FormSection
                            icon={Landmark}
                            title="Destination"
                            description="Les déclarations seront créées pour cet établissement et cette année."
                        >
                            <FormField
                                id="establishment_id"
                                label="Établissement"
                                required
                                error={errors.establishment_id}
                            >
                                <Select
                                    value={
                                        form.data.establishment_id || undefined
                                    }
                                    onValueChange={(value) =>
                                        form.setData('establishment_id', value)
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
                            <FormField
                                id="academic_year_id"
                                label="Année universitaire"
                                required
                                error={errors.academic_year_id}
                                hint="L’année courante est proposée par défaut."
                            >
                                <Select
                                    value={
                                        form.data.academic_year_id || undefined
                                    }
                                    onValueChange={(value) =>
                                        form.setData('academic_year_id', value)
                                    }
                                >
                                    <SelectTrigger
                                        id="academic_year_id"
                                        className="w-full"
                                    >
                                        <SelectValue placeholder="Choisir une année" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {options.academicYears
                                            .filter((item) => item.is_active)
                                            .map((item) => (
                                                <SelectItem
                                                    key={item.id}
                                                    value={String(item.id)}
                                                >
                                                    {item.name}
                                                    {item.is_current
                                                        ? ' (courante)'
                                                        : ''}
                                                </SelectItem>
                                            ))}
                                    </SelectContent>
                                </Select>
                            </FormField>
                        </FormSection>
                        <FormSection
                            icon={FileUp}
                            title="Fichier"
                            description={`Une seule feuille, en-têtes en première ligne, ${maxRows.toLocaleString('fr-FR')} lignes au plus.`}
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
                                                associerez ses colonnes à
                                                l’étape suivante.
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
                                                        form.setData(
                                                            'file',
                                                            file,
                                                        )
                                                    }
                                                    accept={['xlsx', 'csv']}
                                                    maxKilobytes={maxKilobytes}
                                                    invalid={Boolean(
                                                        errors.file,
                                                    )}
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
                    <aside className="min-w-0 space-y-4">
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
                                {tips.map((tip) => (
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
                                <a href="/imports/template">
                                    <Download aria-hidden="true" />
                                    Télécharger le modèle
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
                                        être remplies ; les autres peuvent
                                        rester vides.
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
                                    <ul className="mt-2 grid gap-x-3 gap-y-1.5 sm:grid-cols-2 xl:grid-cols-1 2xl:grid-cols-2">
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
                            Seules les lignes valides sont importées ; les
                            données officielles contradictoires ne sont jamais
                            écrasées.
                        </p>
                    </aside>
                </div>
                <FormActions
                    processing={form.processing}
                    submitLabel="Lire le fichier"
                    cancelHref="/imports"
                    note={
                        ready
                            ? 'Étape suivante : association des colonnes du fichier.'
                            : 'Choisissez la destination et le fichier pour continuer.'
                    }
                />
            </form>
        </PageBody>
    );
}
ImportCreate.layout = {
    breadcrumbs: [
        { title: 'Imports', href: '/imports' },
        { title: 'Fichier', href: '/imports/create' },
    ],
};
