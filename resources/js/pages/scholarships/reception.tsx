import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { formatDate } from '@/components/scholarships/shared';
import { Badge } from '@/components/ui/badge';
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

type Result = {
    id: number;
    student_number: string | null;
    last_name: string;
    first_names: string | null;
    birth_date: string | null;
    is_active: boolean;
    records: {
        establishment: string;
        level: string;
        program: string | null;
        status: string;
    }[];
    application: {
        id: number;
        uuid: string;
        application_number: string;
    } | null;
};

export default function Reception({
    campaigns,
    campaignId,
    search,
    results,
}: {
    campaigns: { id: number; name: string; academic_year: string }[];
    campaignId: number | null;
    search: string;
    results: Result[];
}) {
    const [query, setQuery] = useState(search);
    const [selected, setSelected] = useState<Result | null>(null);
    const form = useForm({
        campaign_id: String(campaignId ?? ''),
        student_id: '',
        ddb_number: '',
        internal_notes: '',
    });
    const errors = form.errors as Record<string, string | undefined>;
    const campaign = campaigns.find((item) => item.id === campaignId);
    const find = (event: FormEvent) => {
        event.preventDefault();
        setSelected(null);
        router.get(
            '/scholarships/reception',
            { campaign_id: campaignId ?? '', search: query },
            { preserveState: true, replace: true },
        );
    };
    const choose = (result: Result) => {
        setSelected(result);
        form.clearErrors();
        form.setData({
            campaign_id: String(campaignId ?? ''),
            student_id: String(result.id),
            ddb_number: '',
            internal_notes: '',
        });
    };
    const receive = (event: FormEvent) => {
        event.preventDefault();
        form.post('/scholarships/applications');
    };
    const activeRecord = (result: Result) =>
        result.records.find((record) => record.status === 'active');
    return (
        <div className="space-y-6 p-4 md:p-6">
            <Head title="Réception des dossiers" />
            <Heading
                title="Réception des dossiers papier"
                description="Recherchez l’étudiant qui dépose son dossier, vérifiez qu’il s’agit de la bonne personne, puis enregistrez la réception. Le dossier est rattaché à la déclaration annuelle de l’année de la campagne."
            />
            {campaigns.length === 0 ? (
                <p role="status" className="rounded-md border p-3">
                    Aucune campagne ouverte : la réception est impossible.
                </p>
            ) : (
                <form onSubmit={find} className="space-y-3">
                    <div className="grid gap-3 md:grid-cols-[1fr_2fr_auto]">
                        <Select
                            value={campaignId ? String(campaignId) : ''}
                            onValueChange={(value) => {
                                setSelected(null);
                                router.get(
                                    '/scholarships/reception',
                                    { campaign_id: value, search: query },
                                    { preserveState: true, replace: true },
                                );
                            }}
                        >
                            <SelectTrigger aria-label="Campagne">
                                <SelectValue placeholder="Campagne ouverte" />
                            </SelectTrigger>
                            <SelectContent>
                                {campaigns.map((item) => (
                                    <SelectItem
                                        key={item.id}
                                        value={String(item.id)}
                                    >
                                        {item.name} ({item.academic_year})
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Input
                            aria-label="Recherche étudiant"
                            placeholder="Matricule, nom, prénom, CIN, numéro BAC, téléphone ou email"
                            maxLength={100}
                            value={query}
                            onChange={(e) => setQuery(e.target.value)}
                            disabled={!campaignId}
                        />
                        <Button
                            disabled={!campaignId || query.trim().length < 2}
                        >
                            Rechercher
                        </Button>
                    </div>
                    {campaign && (
                        <p className="text-sm text-muted-foreground">
                            Campagne : {campaign.name} — année{' '}
                            {campaign.academic_year}. Saisissez au moins deux
                            caractères ; 20 résultats au plus sont affichés.
                        </p>
                    )}
                </form>
            )}
            {search.length >= 2 && campaignId && results.length === 0 && (
                <p>Aucun étudiant ne correspond à cette recherche.</p>
            )}
            {results.length > 0 && (
                <div className="divide-y rounded-md border">
                    {results.map((result) => {
                        const record = activeRecord(result);
                        const blocked =
                            !result.is_active ||
                            !record ||
                            result.application !== null;
                        return (
                            <article
                                key={result.id}
                                className="flex flex-wrap items-center justify-between gap-3 p-4"
                            >
                                <div className="space-y-1">
                                    <p className="font-medium">
                                        {result.last_name} {result.first_names}{' '}
                                        —{' '}
                                        {result.student_number ??
                                            'Sans matricule'}
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        Né(e) le {formatDate(result.birth_date)}
                                        {record &&
                                            ` · ${record.establishment} · ${record.level}${record.program ? ` · ${record.program}` : ''}`}
                                    </p>
                                    {!result.is_active && (
                                        <Badge variant="destructive">
                                            Fiche inactive
                                        </Badge>
                                    )}
                                    {result.is_active && !record && (
                                        <p className="text-sm text-destructive">
                                            {result.records.length === 0
                                                ? 'Aucune déclaration pour l’année de la campagne.'
                                                : 'Déclaration inactive pour l’année de la campagne.'}
                                        </p>
                                    )}
                                </div>
                                {result.application ? (
                                    <Button variant="outline" size="sm" asChild>
                                        <Link
                                            href={`/scholarships/applications/${result.application.uuid}`}
                                        >
                                            Dossier existant n°{' '}
                                            {
                                                result.application
                                                    .application_number
                                            }
                                        </Link>
                                    </Button>
                                ) : (
                                    <Button
                                        size="sm"
                                        variant={
                                            selected?.id === result.id
                                                ? 'default'
                                                : 'outline'
                                        }
                                        disabled={blocked}
                                        onClick={() => choose(result)}
                                    >
                                        Sélectionner
                                    </Button>
                                )}
                            </article>
                        );
                    })}
                </div>
            )}
            {selected && campaign && (
                <form
                    onSubmit={receive}
                    className="space-y-4 rounded-md border p-5"
                >
                    <h2 className="text-lg font-semibold">
                        Confirmer la réception
                    </h2>
                    <dl className="grid gap-2 sm:grid-cols-2">
                        {[
                            [
                                'Étudiant',
                                `${selected.last_name} ${selected.first_names ?? ''}`,
                            ],
                            ['Matricule', selected.student_number ?? '—'],
                            [
                                'Date de naissance',
                                formatDate(selected.birth_date),
                            ],
                            ['Année universitaire', campaign.academic_year],
                            [
                                'Établissement',
                                activeRecord(selected)?.establishment ?? '—',
                            ],
                            ['Niveau', activeRecord(selected)?.level ?? '—'],
                            [
                                'Parcours',
                                activeRecord(selected)?.program ??
                                    'Non renseigné',
                            ],
                        ].map(([label, value]) => (
                            <div key={label}>
                                <dt className="text-sm text-muted-foreground">
                                    {label}
                                </dt>
                                <dd>{value}</dd>
                            </div>
                        ))}
                    </dl>
                    <div className="space-y-2">
                        <Label htmlFor="ddb">
                            Numéro DDB (formulaire papier)
                        </Label>
                        <Input
                            id="ddb"
                            maxLength={100}
                            value={form.data.ddb_number}
                            onChange={(e) =>
                                form.setData('ddb_number', e.target.value)
                            }
                        />
                        <InputError message={errors.ddb_number} />
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="notes">Observations internes</Label>
                        <textarea
                            id="notes"
                            className="min-h-20 w-full rounded-md border bg-transparent p-2 text-sm"
                            maxLength={2000}
                            value={form.data.internal_notes}
                            onChange={(e) =>
                                form.setData('internal_notes', e.target.value)
                            }
                        />
                    </div>
                    <InputError
                        message={errors.student_id ?? errors.campaign_id}
                    />
                    <div className="flex gap-3">
                        <Button disabled={form.processing}>
                            {form.processing
                                ? 'Enregistrement…'
                                : 'Enregistrer la réception'}
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            disabled={form.processing}
                            onClick={() => setSelected(null)}
                        >
                            Annuler
                        </Button>
                    </div>
                </form>
            )}
        </div>
    );
}
Reception.layout = {
    breadcrumbs: [
        { title: 'Réception des dossiers', href: '/scholarships/reception' },
    ],
};
