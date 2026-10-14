import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { Options } from '@/components/students/types';
export default function ImportCreate({
    options,
    maxKilobytes,
    maxRows,
}: {
    options: Options;
    maxKilobytes: number;
    maxRows: number;
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
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post('/imports', { forceFormData: true });
    };
    return (
        <div className="max-w-3xl space-y-6 p-4 md:p-6">
            <Head title="Importer un fichier" />
            <Heading
                title="1. Fichier"
                description="Fichier → Colonnes → Vérification → Confirmation → Résultat"
            />
            <p>
                Ce fichier déclare des étudiants déjà inscrits hors plateforme.
                Aucune inscription universitaire n’est effectuée.
            </p>
            <a className="underline" href="/imports/template">
                Télécharger le modèle CSV
            </a>
            <form onSubmit={submit} className="space-y-5">
                {(
                    [
                        {
                            key: 'establishment_id',
                            label: 'Établissement',
                            items: options.establishments,
                        },
                        {
                            key: 'academic_year_id',
                            label: 'Année universitaire',
                            items: options.academicYears,
                        },
                    ] as const
                ).map((field) => (
                    <div key={field.key} className="space-y-2">
                        <Label htmlFor={field.key}>{field.label}</Label>
                        <Select
                            value={form.data[field.key]}
                            onValueChange={(value) =>
                                form.setData(field.key, value)
                            }
                            disabled={
                                field.key === 'establishment_id' &&
                                options.fixedEstablishmentId !== null
                            }
                        >
                            <SelectTrigger id={field.key}>
                                <SelectValue placeholder="Sélectionner" />
                            </SelectTrigger>
                            <SelectContent>
                                {field.items
                                    .filter((i) => i.is_active)
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
                        <InputError message={form.errors[field.key]} />
                    </div>
                ))}
                <div className="space-y-2">
                    <Label htmlFor="file">Fichier XLSX ou CSV</Label>
                    <Input
                        id="file"
                        type="file"
                        accept=".xlsx,.csv"
                        onChange={(event) =>
                            form.setData(
                                'file',
                                event.target.files?.[0] ?? null,
                            )
                        }
                    />
                    <p className="text-sm text-muted-foreground">
                        Une seule feuille, en-têtes en première ligne. Maximum{' '}
                        {maxKilobytes / 1024} Mo et {maxRows} lignes. Pas de
                        formules. Les identifiants doivent être enregistrés
                        comme texte pour conserver les zéros initiaux.
                    </p>
                    <InputError message={form.errors.file} />
                </div>
                {form.progress && (
                    <p>Téléversement : {form.progress.percentage} %</p>
                )}
                <div className="flex gap-3">
                    <Button disabled={form.processing || !form.data.file}>
                        {form.processing ? 'Lecture…' : 'Lire le fichier'}
                    </Button>
                    <Button variant="outline" asChild>
                        <Link href="/imports">Annuler</Link>
                    </Button>
                </div>
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
