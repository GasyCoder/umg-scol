import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { formatDate, Pagination } from '@/components/scholarships/shared';
import type { Option, PageLink } from '@/components/scholarships/shared';
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

type Row = {
    id: number;
    at: string | null;
    actor: string;
    role: string | null;
    module_label: string;
    event_label: string;
    subject_type: string | null;
    subject_id: number | string | null;
};
const KEYS = ['from', 'to', 'actor', 'module', 'event', 'subject'] as const;

export default function AuditIndex({
    activities,
    filters,
    options,
    canExport,
}: {
    activities: { data: Row[]; total: number; links: PageLink[] };
    filters: Partial<Record<(typeof KEYS)[number], string | number>>;
    options: {
        actors: { id: number; name: string }[];
        modules: Option[];
        events: Option[];
        subjects: string[];
    };
    canExport: boolean;
}) {
    const applied = (key: (typeof KEYS)[number]) =>
        filters[key] === undefined || filters[key] === null
            ? ''
            : String(filters[key]);
    const form = useForm<Record<string, string>>(
        Object.fromEntries(KEYS.map((key) => [key, applied(key)])),
    );
    const errors = form.errors as Record<string, string | undefined>;
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.get('/audits', { preserveState: true });
    };
    const query = new URLSearchParams(
        KEYS.map((key): [string, string] => [key, applied(key)]).filter(
            ([, value]) => value !== '',
        ),
    ).toString();
    const selects: { key: string; label: string; items: Option[] }[] = [
        {
            key: 'actor',
            label: 'Acteur',
            items: options.actors.map((actor) => ({
                value: String(actor.id),
                label: actor.name,
            })),
        },
        { key: 'module', label: 'Module', items: options.modules },
        { key: 'event', label: 'Événement', items: options.events },
        {
            key: 'subject',
            label: 'Type de sujet',
            items: options.subjects.map((subject) => ({
                value: subject,
                label: subject,
            })),
        },
    ];
    return (
        <div className="space-y-6 p-4 md:p-6">
            <Head title="Audit" />
            <div className="flex flex-wrap items-start justify-between gap-3">
                <Heading
                    title="Journal d’audit"
                    description="Actions tracées sur la plateforme, tous acteurs confondus, développeurs compris. Aucune donnée personnelle n’est affichée."
                />
                {canExport && (
                    <Button variant="outline" asChild>
                        <a href={`/audits/export${query ? `?${query}` : ''}`}>
                            Exporter (CSV)
                        </a>
                    </Button>
                )}
            </div>
            <form onSubmit={submit} className="space-y-3">
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <div className="grid gap-1">
                        <Label htmlFor="from">Du</Label>
                        <Input
                            id="from"
                            type="date"
                            value={form.data.from}
                            onChange={(e) =>
                                form.setData('from', e.target.value)
                            }
                        />
                        <InputError message={errors.from} />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="to">Au</Label>
                        <Input
                            id="to"
                            type="date"
                            value={form.data.to}
                            onChange={(e) => form.setData('to', e.target.value)}
                        />
                        <InputError message={errors.to} />
                    </div>
                    {selects.map((select) => (
                        <div key={select.key} className="grid gap-1">
                            <Label htmlFor={`filter-${select.key}`}>
                                {select.label}
                            </Label>
                            <Select
                                value={form.data[select.key] || 'all'}
                                onValueChange={(value) =>
                                    form.setData(
                                        select.key,
                                        value === 'all' ? '' : value,
                                    )
                                }
                            >
                                <SelectTrigger
                                    id={`filter-${select.key}`}
                                    className="w-full"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Tous</SelectItem>
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
                            <InputError message={errors[select.key]} />
                        </div>
                    ))}
                </div>
                <div className="flex gap-2">
                    <Button disabled={form.processing}>Filtrer</Button>
                    <Button variant="ghost" asChild>
                        <Link href="/audits">Réinitialiser</Link>
                    </Button>
                </div>
            </form>
            <p className="text-sm text-muted-foreground">
                {activities.total} entrée(s)
            </p>
            {activities.data.length === 0 ? (
                <p>Aucune entrée ne correspond à ces critères.</p>
            ) : (
                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full min-w-[56rem] text-sm">
                        <caption className="sr-only">Journal d’audit</caption>
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                {[
                                    'Date et heure',
                                    'Acteur',
                                    'Module',
                                    'Événement',
                                    'Sujet',
                                    'Actions',
                                ].map((title) => (
                                    <th
                                        key={title}
                                        scope="col"
                                        className="px-4 py-3 font-medium"
                                    >
                                        {title}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {activities.data.map((row) => (
                                <tr key={row.id}>
                                    <td className="px-4 py-3 whitespace-nowrap">
                                        {formatDate(row.at, true)}
                                    </td>
                                    <td className="px-4 py-3">
                                        {row.actor}
                                        {row.role && (
                                            <span className="block text-xs text-muted-foreground">
                                                {row.role}
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-4 py-3">
                                        <Badge variant="outline">
                                            {row.module_label}
                                        </Badge>
                                    </td>
                                    <td className="px-4 py-3">
                                        {row.event_label}
                                    </td>
                                    <td className="px-4 py-3">
                                        {row.subject_type
                                            ? `${row.subject_type} n° ${row.subject_id}`
                                            : '—'}
                                    </td>
                                    <td className="px-4 py-3">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            asChild
                                        >
                                            <Link href={`/audits/${row.id}`}>
                                                Détail
                                            </Link>
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
            <Pagination links={activities.links} />
        </div>
    );
}

AuditIndex.layout = {
    breadcrumbs: [{ title: 'Audit', href: '/audits' }],
};
