import { Head, useForm } from '@inertiajs/react';
import { Check, Download, FileUp, Landmark } from 'lucide-react';
import type { FormEvent } from 'react';
import { FileDropzone } from '@/components/forms/file-dropzone';
import {
    ErrorSummary,
    FormActions,
    FormField,
    FormSection,
} from '@/components/forms/form-kit';
import Heading from '@/components/heading';
import type { Options } from '@/components/students/types';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

const steps = [
    'Fichier',
    'Colonnes',
    'Vérification',
    'Confirmation',
    'Résultat',
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

    return (
        <div className="space-y-6 p-4 pb-0 md:p-6 md:pb-0">
            <Head title="Importer un fichier" />
            <Heading
                title="Importer des étudiants"
                description="Déclarez en une fois des étudiants déjà régulièrement inscrits. Rien n’est enregistré avant votre confirmation."
            />
            <ol
                aria-label="Étapes de l’import"
                className="flex flex-wrap items-center gap-2 rounded-lg border bg-card px-4 py-3 text-sm"
            >
                {steps.map((step, index) => (
                    <li key={step} className="flex items-center gap-2">
                        <span
                            className={`flex size-6 items-center justify-center rounded-full text-xs font-bold ${index === 0 ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground'}`}
                            aria-hidden="true"
                        >
                            {index + 1}
                        </span>
                        <span
                            className={
                                index === 0
                                    ? 'font-bold text-foreground'
                                    : 'text-muted-foreground'
                            }
                            aria-current={index === 0 ? 'step' : undefined}
                        >
                            {step}
                        </span>
                        {index < steps.length - 1 && (
                            <span
                                className="text-muted-foreground"
                                aria-hidden="true"
                            >
                                ›
                            </span>
                        )}
                    </li>
                ))}
            </ol>

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
                    <div className="space-y-6 xl:col-span-2">
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
                            <FormField
                                id="file"
                                label="Fichier XLSX ou CSV"
                                required
                                error={errors.file}
                            >
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
                            </FormField>
                            {form.progress && (
                                <div>
                                    <div className="flex justify-between text-xs text-muted-foreground">
                                        <span>Téléversement</span>
                                        <span>
                                            {form.progress.percentage} %
                                        </span>
                                    </div>
                                    <div
                                        className="mt-1 h-2 rounded-full bg-muted"
                                        role="progressbar"
                                        aria-valuenow={form.progress.percentage}
                                        aria-valuemin={0}
                                        aria-valuemax={100}
                                        aria-label="Progression du téléversement"
                                    >
                                        <div
                                            className="h-2 rounded-full bg-primary transition-all"
                                            style={{
                                                width: `${form.progress.percentage ?? 0}%`,
                                            }}
                                        />
                                    </div>
                                </div>
                            )}
                        </FormSection>
                    </div>
                    <aside className="space-y-4">
                        <section className="rounded-lg border bg-card p-5">
                            <h2 className="text-base font-bold text-foreground">
                                Avant d’importer
                            </h2>
                            <ul className="mt-3 space-y-2 text-sm">
                                {[
                                    'Partez du modèle : les colonnes sont reconnues automatiquement.',
                                    'Enregistrez matricules, CIN et numéros BAC comme du texte pour garder les zéros initiaux.',
                                    'Pas de formules ni de cellules fusionnées.',
                                    'Le fichier déclare des étudiants déjà inscrits : aucune inscription n’est effectuée.',
                                ].map((tip) => (
                                    <li key={tip} className="flex gap-2">
                                        <Check
                                            className="mt-0.5 size-4 shrink-0 text-primary"
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
                                className="mt-4 w-full"
                                asChild
                            >
                                <a href="/imports/template">
                                    <Download aria-hidden="true" />
                                    Télécharger le modèle
                                </a>
                            </Button>
                        </section>
                        <section className="rounded-lg border bg-card p-5">
                            <h2 className="text-base font-bold text-foreground">
                                Colonnes attendues
                            </h2>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Seules les colonnes marquées sont obligatoires ;
                                les autres peuvent rester vides.
                            </p>
                            <ul className="mt-3 flex flex-wrap gap-1.5">
                                {columns.map((column) => (
                                    <li key={column.label}>
                                        <Badge
                                            variant={
                                                column.required
                                                    ? 'default'
                                                    : 'outline'
                                            }
                                        >
                                            {column.label}
                                            {column.required ? ' *' : ''}
                                        </Badge>
                                    </li>
                                ))}
                            </ul>
                        </section>
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
        </div>
    );
}
ImportCreate.layout = {
    breadcrumbs: [
        { title: 'Imports', href: '/imports' },
        { title: 'Fichier', href: '/imports/create' },
    ],
};
