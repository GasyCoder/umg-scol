import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { CorrectionStatusBadge } from '@/components/corrections/status-badge';

type Row = {
    id: number;
    field_label: string;
    status: string;
    status_label: string;
    created_at: string;
    student: {
        last_name: string;
        first_names: string | null;
        student_number: string | null;
    };
    establishments: string[];
};
type Option = { value: string; label: string };

export default function CorrectionsIndex({
    corrections,
    filters,
    options,
}: {
    corrections: {
        data: Row[];
        total: number;
        links: { url: string | null; label: string; active: boolean }[];
    };
    filters: Record<string, string | undefined>;
    options: {
        statuses: Option[];
        fields: Option[];
        establishments: { id: number; name: string }[];
    };
}) {
    const form = useForm<Record<string, string>>({
        search: filters.search ?? '',
        status: filters.status ?? 'pending',
        field: filters.field ?? '',
        establishment_id: String(filters.establishment_id ?? ''),
    });
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.get('/corrections', { preserveState: true });
    };
    const selects = [
        {
            key: 'status',
            label: 'Statut',
            all: 'Tous les statuts',
            items: options.statuses,
        },
        {
            key: 'field',
            label: 'Information',
            all: 'Toutes les informations',
            items: options.fields,
        },
        ...(options.establishments.length > 0
            ? [
                  {
                      key: 'establishment_id',
                      label: 'Établissement',
                      all: 'Tous les établissements',
                      items: options.establishments.map((item) => ({
                          value: String(item.id),
                          label: item.name,
                      })),
                  },
              ]
            : []),
    ];
    return (
        <div className="space-y-6 p-4 md:p-6">
            <Head title="Demandes de correction" />
            <Heading
                title="Demandes de correction"
                description="Corrections d’informations proposées par les étudiants. La fiche officielle n’est modifiée qu’après approbation."
            />
            <form onSubmit={submit} className="space-y-3">
                <Input
                    aria-label="Recherche étudiant"
                    placeholder="Matricule, nom ou prénom"
                    maxLength={100}
                    value={form.data.search}
                    onChange={(event) =>
                        form.setData('search', event.target.value)
                    }
                />
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    {selects.map((select) => (
                        <Select
                            key={select.key}
                            value={form.data[select.key] || 'all'}
                            onValueChange={(value) =>
                                form.setData(
                                    select.key,
                                    value === 'all' && select.key !== 'status'
                                        ? ''
                                        : value,
                                )
                            }
                        >
                            <SelectTrigger aria-label={select.label}>
                                <SelectValue placeholder={select.label} />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">
                                    {select.all}
                                </SelectItem>
                                {select.items.map((item) => (
                                    <SelectItem
                                        key={item.value}
                                        value={item.value}
                                    >
                                        {item.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    ))}
                    <Button disabled={form.processing}>Filtrer</Button>
                </div>
            </form>
            <p className="text-sm text-muted-foreground">
                {corrections.total} demande(s)
            </p>
            {corrections.data.length === 0 ? (
                <p>
                    Aucune demande ne correspond à ces critères dans votre
                    périmètre.
                </p>
            ) : (
                <div className="divide-y rounded-md border">
                    {corrections.data.map((item) => (
                        <article
                            key={item.id}
                            className="flex flex-wrap items-center justify-between gap-3 p-4"
                        >
                            <div className="space-y-1">
                                <p className="font-medium">
                                    {item.student.last_name}{' '}
                                    {item.student.first_names} —{' '}
                                    {item.student.student_number ??
                                        'Sans matricule'}
                                </p>
                                <p className="text-sm">
                                    Information : {item.field_label}
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    Demandée le{' '}
                                    {new Date(
                                        item.created_at,
                                    ).toLocaleDateString('fr-FR')}
                                    {item.establishments.length > 0 &&
                                        ` · ${item.establishments.join(', ')}`}
                                </p>
                            </div>
                            <div className="flex items-center gap-3">
                                <CorrectionStatusBadge
                                    status={item.status}
                                    label={item.status_label}
                                />
                                <Button variant="outline" asChild>
                                    <Link href={`/corrections/${item.id}`}>
                                        Examiner
                                    </Link>
                                </Button>
                            </div>
                        </article>
                    ))}
                </div>
            )}
            <nav aria-label="Pagination" className="flex flex-wrap gap-2">
                {corrections.links.map((link, i) =>
                    link.url ? (
                        <Button
                            key={i}
                            variant={link.active ? 'default' : 'outline'}
                            asChild
                        >
                            <Link href={link.url} preserveState>
                                {i === 0
                                    ? 'Précédent'
                                    : i === corrections.links.length - 1
                                      ? 'Suivant'
                                      : link.label}
                            </Link>
                        </Button>
                    ) : null,
                )}
            </nav>
        </div>
    );
}
CorrectionsIndex.layout = {
    breadcrumbs: [{ title: 'Corrections', href: '/corrections' }],
};
