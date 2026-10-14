import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import {
    formatDate,
    Pagination,
    StatusBadge,
} from '@/components/scholarships/shared';
import type { Option, PageLink } from '@/components/scholarships/shared';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type Row = {
    id: number;
    application_number: string;
    ddb_number: string | null;
    campaign: string;
    student: {
        student_number: string | null;
        last_name: string;
        first_names: string | null;
    };
    establishment: string;
    level: string;
    status: string;
    status_label: string;
    received_at: string;
};
type Named = { id: number; name: string };

export default function ApplicationsIndex({
    applications,
    filters,
    options,
    canExport,
    canReceive,
}: {
    applications: { data: Row[]; total: number; links: PageLink[] };
    filters: Record<string, string | number | undefined>;
    options: {
        campaigns: Named[];
        statuses: Option[];
        establishments: Named[];
        levels: Named[];
    };
    canExport: boolean;
    canReceive: boolean;
}) {
    const value = (key: string) =>
        filters[key] === undefined || filters[key] === null
            ? ''
            : String(filters[key]);
    const form = useForm<Record<string, string>>({
        search: value('search'),
        campaign_id: value('campaign_id'),
        status: value('status'),
        establishment_id: value('establishment_id'),
        level_id: value('level_id'),
        received_from: value('received_from'),
        received_to: value('received_to'),
    });
    const errors = form.errors as Record<string, string | undefined>;
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.get('/scholarships/applications', { preserveState: true });
    };
    const named = (items: Named[]) =>
        items.map((item) => ({ value: String(item.id), label: item.name }));
    const selects: { key: string; label: string; items: Option[] }[] = [
        {
            key: 'campaign_id',
            label: 'Campagne',
            items: named(options.campaigns),
        },
        { key: 'status', label: 'Statut', items: options.statuses },
        {
            key: 'establishment_id',
            label: 'Établissement',
            items: named(options.establishments),
        },
        { key: 'level_id', label: 'Niveau', items: named(options.levels) },
    ];
    // Export the applied filters (what the list shows), not unsubmitted form edits.
    const exportUrl = `/scholarships/applications/export?${new URLSearchParams(
        Object.keys(form.data)
            .map((key): [string, string] => [key, value(key)])
            .filter(([, item]) => item !== ''),
    ).toString()}`;
    return (
        <div className="space-y-6 p-4 md:p-6">
            <Head title="Dossiers de bourse" />
            <div className="flex flex-wrap items-start justify-between gap-3">
                <Heading
                    title="Dossiers de bourse"
                    description="Dossiers papier reçus par la Scolarité Centrale et leur traitement."
                />
                <div className="flex gap-2">
                    {canExport && (
                        <Button variant="outline" asChild>
                            <a href={exportUrl}>Exporter (CSV)</a>
                        </Button>
                    )}
                    {canReceive && (
                        <Button asChild>
                            <Link href="/scholarships/reception">
                                Réceptionner un dossier
                            </Link>
                        </Button>
                    )}
                </div>
            </div>
            <form onSubmit={submit} className="space-y-3">
                <Input
                    aria-label="Recherche"
                    placeholder="N° dossier, N° DDB, matricule, nom ou prénom"
                    maxLength={100}
                    value={form.data.search}
                    onChange={(e) => form.setData('search', e.target.value)}
                />
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    {selects.map((select) => (
                        <Select
                            key={select.key}
                            value={form.data[select.key] || 'all'}
                            onValueChange={(item) =>
                                form.setData(
                                    select.key,
                                    item === 'all' ? '' : item,
                                )
                            }
                        >
                            <SelectTrigger aria-label={select.label}>
                                <SelectValue placeholder={select.label} />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">
                                    {select.label} : tous
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
                    <Input
                        type="date"
                        aria-label="Reçu à partir du"
                        value={form.data.received_from}
                        onChange={(e) =>
                            form.setData('received_from', e.target.value)
                        }
                    />
                    <Input
                        type="date"
                        aria-label="Reçu jusqu’au"
                        value={form.data.received_to}
                        onChange={(e) =>
                            form.setData('received_to', e.target.value)
                        }
                    />
                    <Button disabled={form.processing}>Filtrer</Button>
                </div>
                <InputError message={errors.received_to ?? errors.search} />
            </form>
            <p className="text-sm text-muted-foreground">
                {applications.total} dossier(s)
            </p>
            {applications.data.length === 0 ? (
                <p>Aucun dossier ne correspond à ces critères.</p>
            ) : (
                <div className="overflow-x-auto rounded-md border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                {[
                                    'N° dossier',
                                    'DDB',
                                    'Étudiant',
                                    'Établissement',
                                    'Niveau',
                                    'Campagne',
                                    'Statut',
                                    'Réception',
                                    '',
                                ].map((label) => (
                                    <th key={label} className="p-3 font-medium">
                                        {label}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {applications.data.map((item) => (
                                <tr key={item.id}>
                                    <td className="p-3 font-mono">
                                        {item.application_number}
                                    </td>
                                    <td className="p-3">
                                        {item.ddb_number ?? '—'}
                                    </td>
                                    <td className="p-3">
                                        {item.student.last_name}{' '}
                                        {item.student.first_names}
                                        <span className="block text-muted-foreground">
                                            {item.student.student_number ??
                                                'Sans matricule'}
                                        </span>
                                    </td>
                                    <td className="p-3">
                                        {item.establishment}
                                    </td>
                                    <td className="p-3">{item.level}</td>
                                    <td className="p-3">{item.campaign}</td>
                                    <td className="p-3">
                                        <StatusBadge
                                            status={item.status}
                                            label={item.status_label}
                                        />
                                    </td>
                                    <td className="p-3">
                                        {formatDate(item.received_at)}
                                    </td>
                                    <td className="p-3">
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            asChild
                                        >
                                            <Link
                                                href={`/scholarships/applications/${item.id}`}
                                            >
                                                Ouvrir
                                            </Link>
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
            <Pagination links={applications.links} />
        </div>
    );
}
ApplicationsIndex.layout = {
    breadcrumbs: [{ title: 'Dossiers', href: '/scholarships/applications' }],
};
