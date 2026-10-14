import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { AnnualRecord, Options } from '@/components/students/types';
export default function StudentsIndex({
    records,
    filters,
    options,
    canCreate,
}: {
    records: {
        data: AnnualRecord[];
        total: number;
        links: { url: string | null; label: string; active: boolean }[];
    };
    filters: Record<string, string>;
    options: Options;
    canCreate: boolean;
}) {
    const form = useForm<Record<string, string>>({
        search: filters.search ?? '',
        academic_year_id: String(filters.academic_year_id ?? ''),
        establishment_id: String(
            options.fixedEstablishmentId ?? filters.establishment_id ?? '',
        ),
        level_id: String(filters.level_id ?? ''),
        program_id: String(filters.program_id ?? ''),
        status: filters.status ?? '',
    });
    const fields = [
        {
            key: 'academic_year_id',
            label: 'Année',
            items: options.academicYears,
        },
        {
            key: 'establishment_id',
            label: 'Établissement',
            items: options.establishments,
        },
        { key: 'level_id', label: 'Niveau', items: options.levels },
        {
            key: 'program_id',
            label: 'Parcours',
            items: options.programs.filter(
                (item) =>
                    !form.data.establishment_id ||
                    String(item.establishment_id) ===
                        form.data.establishment_id,
            ),
        },
    ];
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.get('/students', { preserveState: true });
    };
    return (
        <div className="space-y-6 p-4 md:p-6">
            <Head title="Étudiants déclarés" />
            <div className="flex flex-wrap justify-between gap-3">
                <Heading
                    title="Étudiants déclarés"
                    description="Identités et déclarations annuelles des étudiants déjà régulièrement inscrits."
                />
                {canCreate && (
                    <Button asChild>
                        <Link href="/students/create">Ajouter un étudiant</Link>
                    </Button>
                )}
            </div>
            <form onSubmit={submit} className="space-y-3">
                <Input
                    aria-label="Recherche étudiant"
                    placeholder="Matricule, nom, prénom, CIN, numéro BAC, téléphone ou email"
                    value={form.data.search}
                    onChange={(event) =>
                        form.setData('search', event.target.value)
                    }
                    maxLength={100}
                />
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                    {fields.map((field) => (
                        <Select
                            key={field.key}
                            value={form.data[field.key] || 'all'}
                            onValueChange={(value) => {
                                form.setData(
                                    field.key,
                                    value === 'all' ? '' : value,
                                );
                                if (field.key === 'establishment_id')
                                    form.setData('program_id', '');
                            }}
                            disabled={
                                field.key === 'establishment_id' &&
                                options.fixedEstablishmentId !== null
                            }
                        >
                            <SelectTrigger aria-label={field.label}>
                                <SelectValue placeholder={field.label} />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">
                                    {field.label} : tous
                                </SelectItem>
                                {field.items.map((item) => (
                                    <SelectItem
                                        key={item.id}
                                        value={String(item.id)}
                                    >
                                        {item.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    ))}
                    <Select
                        value={form.data.status || 'all'}
                        onValueChange={(value) =>
                            form.setData('status', value === 'all' ? '' : value)
                        }
                    >
                        <SelectTrigger aria-label="Statut déclaration">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">
                                Tous les statuts de déclaration
                            </SelectItem>
                            <SelectItem value="active">Active</SelectItem>
                            <SelectItem value="inactive">Inactive</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <InputError message={Object.values(form.errors)[0]} />
                <div className="flex gap-2">
                    <Button variant="outline" disabled={form.processing}>
                        Rechercher
                    </Button>
                    <Button variant="ghost" asChild>
                        <Link href="/students">Réinitialiser</Link>
                    </Button>
                </div>
            </form>
            <div className="overflow-x-auto rounded-lg border">
                <table className="w-full text-left text-sm">
                    <caption className="sr-only">
                        Étudiants déclarés par année universitaire
                    </caption>
                    <thead className="bg-muted">
                        <tr>
                            {[
                                'Matricule',
                                'Nom et prénoms',
                                'Établissement',
                                'Niveau',
                                'Parcours',
                                'Année',
                                'Statut',
                                'Actions',
                            ].map((label) => (
                                <th
                                    key={label}
                                    className="px-4 py-3"
                                    scope="col"
                                >
                                    {label}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {records.data.length === 0 ? (
                            <tr>
                                <td
                                    colSpan={8}
                                    className="p-8 text-center text-muted-foreground"
                                >
                                    Aucun étudiant déclaré ne correspond à cette
                                    recherche.
                                </td>
                            </tr>
                        ) : (
                            records.data.map((record) => (
                                <tr key={record.id} className="border-t">
                                    <td className="px-4 py-3">
                                        {record.student?.student_number ?? '—'}
                                    </td>
                                    <td className="px-4 py-3">
                                        {record.student?.last_name}{' '}
                                        {record.student?.first_names}
                                    </td>
                                    <td className="px-4 py-3">
                                        {record.establishment?.code}
                                    </td>
                                    <td className="px-4 py-3">
                                        {record.level?.code}
                                    </td>
                                    <td className="px-4 py-3">
                                        {record.program?.name ?? '—'}
                                    </td>
                                    <td className="px-4 py-3">
                                        {record.academic_year?.name}
                                    </td>
                                    <td className="px-4 py-3">
                                        <Badge
                                            variant={
                                                record.status === 'active'
                                                    ? 'secondary'
                                                    : 'outline'
                                            }
                                        >
                                            {record.status === 'active'
                                                ? 'Déclaration active'
                                                : 'Déclaration inactive'}
                                        </Badge>
                                        {record.student?.is_active ===
                                            false && (
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                Identité inactive
                                            </p>
                                        )}
                                    </td>
                                    <td className="px-4 py-3">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            asChild
                                        >
                                            <Link
                                                href={`/students/${record.student_id}`}
                                            >
                                                Consulter
                                            </Link>
                                        </Button>
                                    </td>
                                </tr>
                            ))
                        )}
                    </tbody>
                </table>
            </div>
            <div className="flex flex-wrap justify-between gap-3">
                <p className="text-sm text-muted-foreground">
                    {records.total} déclaration(s)
                </p>
                <nav className="flex flex-wrap gap-1" aria-label="Pagination">
                    {records.links.map((link, index) => {
                        const label =
                            index === 0
                                ? 'Précédent'
                                : index === records.links.length - 1
                                  ? 'Suivant'
                                  : link.label;
                        return link.url ? (
                            <Button
                                key={index}
                                size="sm"
                                variant={link.active ? 'default' : 'outline'}
                                asChild
                            >
                                <Link
                                    href={link.url}
                                    aria-current={
                                        link.active ? 'page' : undefined
                                    }
                                >
                                    {label}
                                </Link>
                            </Button>
                        ) : (
                            <Button
                                key={index}
                                size="sm"
                                variant="outline"
                                disabled
                            >
                                {label}
                            </Button>
                        );
                    })}
                </nav>
            </div>
        </div>
    );
}
StudentsIndex.layout = {
    breadcrumbs: [{ title: 'Étudiants', href: '/students' }],
};
