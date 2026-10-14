import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    Download,
    FolderOpen,
    Inbox,
    RotateCcw,
    Search,
    SlidersHorizontal,
} from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { TrashButton } from '@/components/trash/trash-button';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import {
    applicationStatusLabels,
    formatNumber,
} from '@/components/reporting/shared';
import {
    formatDate,
    Pagination,
    StatusBadge,
} from '@/components/scholarships/shared';
import type { Option, PageLink } from '@/components/scholarships/shared';
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
    uuid: string;
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

const LIST_URL = '/scholarships/applications';
const STATUS_TABS = [
    'pending',
    'in_review',
    'needs_correction',
    'validated',
    'rejected',
];

/** Query string without empty values, so the URL stays readable. */
const clean = (data: Record<string, string>) =>
    Object.fromEntries(Object.entries(data).filter(([, item]) => item !== ''));

function Field({
    id,
    label,
    className = '',
    children,
}: {
    id: string;
    label: string;
    className?: string;
    children: ReactNode;
}) {
    return (
        <div className={`grid min-w-0 content-start gap-1.5 ${className}`}>
            <Label
                htmlFor={id}
                className="text-xs font-medium text-muted-foreground"
            >
                {label}
            </Label>
            {children}
        </div>
    );
}

export default function ApplicationsIndex({
    applications,
    filters,
    statusCounts,
    options,
    canExport,
    canReceive,
}: {
    applications: { data: Row[]; total: number; links: PageLink[] };
    filters: Record<string, string | number | undefined>;
    statusCounts: Record<string, number>;
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
    const applied = Object.fromEntries(
        Object.keys(form.data).map((key): [string, string] => [
            key,
            value(key),
        ]),
    );
    const activeStatus = applied.status;
    const hasFilters = Object.values(applied).some((item) => item !== '');
    const hasEdits = Object.values(form.data).some((item) => item !== '');
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.get(LIST_URL, { preserveState: true });
    };
    // A tab keeps the filters the list currently shows and only swaps the status.
    const showStatus = (status: string) => {
        form.setData('status', status);
        router.get(LIST_URL, clean({ ...applied, status }), {
            preserveState: true,
            preserveScroll: true,
        });
    };
    const reset = () => {
        form.setData(
            Object.fromEntries(
                Object.keys(form.data).map((key): [string, string] => [
                    key,
                    '',
                ]),
            ),
        );
        form.clearErrors();
        router.get(LIST_URL, {}, { preserveState: true });
    };
    const named = (items: Named[]) =>
        items.map((item) => ({ value: String(item.id), label: item.name }));
    const selects: { key: string; label: string; items: Option[] }[] = [
        {
            key: 'campaign_id',
            label: 'Campagne',
            items: named(options.campaigns),
        },
        {
            key: 'establishment_id',
            label: 'Établissement',
            items: named(options.establishments),
        },
        { key: 'level_id', label: 'Niveau', items: named(options.levels) },
    ];
    const statusLabel = (status: string) =>
        applicationStatusLabels[status] ??
        options.statuses.find((item) => item.value === status)?.label ??
        status;
    const tabs = [
        {
            value: '',
            label: 'Tous',
            count: Object.values(statusCounts).reduce(
                (sum, count) => sum + count,
                0,
            ),
        },
        ...STATUS_TABS.map((status) => ({
            value: status,
            label: statusLabel(status),
            count: statusCounts[status] ?? 0,
        })),
    ];
    // Export the applied filters (what the list shows), not unsubmitted form edits.
    const exportUrl = `${LIST_URL}/export?${new URLSearchParams(
        clean(applied),
    ).toString()}`;
    return (
        <div className="space-y-6 p-4 md:p-6">
            <Head title="Dossiers de bourse" />
            <div className="flex flex-wrap items-start justify-between gap-3">
                <Heading
                    title="Dossiers de bourse"
                    description="Dossiers papier reçus par la Scolarité Centrale et leur traitement."
                />
                <div className="flex flex-wrap gap-2">
                    {canExport && (
                        <Button variant="outline" asChild>
                            <a href={exportUrl}>
                                <Download aria-hidden="true" />
                                Exporter (CSV)
                            </a>
                        </Button>
                    )}
                    {canReceive && (
                        <Button asChild>
                            <Link href="/scholarships/reception">
                                <Inbox aria-hidden="true" />
                                Réceptionner un dossier
                            </Link>
                        </Button>
                    )}
                </div>
            </div>

            <nav
                aria-label="Filtrer par statut"
                className="-mx-4 overflow-x-auto px-4 md:mx-0 md:px-0"
            >
                <ul className="flex w-full min-w-max gap-1 border-b">
                    {tabs.map((tab) => {
                        const active = activeStatus === tab.value;
                        return (
                            <li key={tab.value || 'all'}>
                                <button
                                    type="button"
                                    onClick={() => showStatus(tab.value)}
                                    aria-current={active ? 'true' : undefined}
                                    className={`-mb-px flex items-center gap-2 rounded-t-md border-b-2 px-3 py-2.5 text-sm font-medium whitespace-nowrap transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none ${
                                        active
                                            ? 'border-primary text-primary'
                                            : 'border-transparent text-muted-foreground hover:border-border hover:text-foreground'
                                    }`}
                                >
                                    {tab.label}
                                    <span
                                        className={`rounded-full px-2 py-0.5 text-xs tabular-nums ${
                                            active
                                                ? 'bg-primary text-primary-foreground'
                                                : 'bg-secondary text-secondary-foreground'
                                        }`}
                                    >
                                        {formatNumber(tab.count)}
                                    </span>
                                </button>
                            </li>
                        );
                    })}
                </ul>
            </nav>

            <form
                onSubmit={submit}
                className="space-y-3 rounded-lg border bg-card p-4"
                aria-label="Filtres des dossiers"
            >
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 2xl:grid-cols-[minmax(16rem,2fr)_repeat(5,minmax(0,1fr))]">
                    <Field
                        id="filter-search"
                        label="Recherche"
                        className="sm:col-span-2 2xl:col-span-1"
                    >
                        <div className="relative">
                            <Search
                                className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                aria-hidden="true"
                            />
                            <Input
                                id="filter-search"
                                type="search"
                                className="pl-9"
                                placeholder="N° dossier, N° DDB, matricule, nom ou prénom"
                                maxLength={100}
                                value={form.data.search}
                                onChange={(e) =>
                                    form.setData('search', e.target.value)
                                }
                            />
                        </div>
                    </Field>
                    {selects.map((select) => (
                        <Field
                            key={select.key}
                            id={`filter-${select.key}`}
                            label={select.label}
                        >
                            <Select
                                value={form.data[select.key] || 'all'}
                                onValueChange={(item) =>
                                    form.setData(
                                        select.key,
                                        item === 'all' ? '' : item,
                                    )
                                }
                            >
                                <SelectTrigger
                                    id={`filter-${select.key}`}
                                    className="w-full"
                                >
                                    <SelectValue placeholder={select.label} />
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
                        </Field>
                    ))}
                    <Field id="filter-received-from" label="Reçu à partir du">
                        <Input
                            id="filter-received-from"
                            type="date"
                            value={form.data.received_from}
                            onChange={(e) =>
                                form.setData('received_from', e.target.value)
                            }
                        />
                    </Field>
                    <Field id="filter-received-to" label="Reçu jusqu’au">
                        <Input
                            id="filter-received-to"
                            type="date"
                            value={form.data.received_to}
                            aria-invalid={errors.received_to ? true : undefined}
                            onChange={(e) =>
                                form.setData('received_to', e.target.value)
                            }
                        />
                    </Field>
                </div>
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <InputError message={errors.received_to ?? errors.search} />
                    <div className="ml-auto flex flex-wrap gap-2">
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={reset}
                            disabled={
                                form.processing || (!hasFilters && !hasEdits)
                            }
                        >
                            <RotateCcw aria-hidden="true" />
                            Réinitialiser
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            <SlidersHorizontal aria-hidden="true" />
                            Filtrer
                        </Button>
                    </div>
                </div>
            </form>

            <section className="space-y-3" aria-labelledby="applications-total">
                <p
                    id="applications-total"
                    className="text-sm text-muted-foreground"
                    aria-live="polite"
                >
                    {formatNumber(applications.total)}{' '}
                    {applications.total > 1 ? 'dossiers' : 'dossier'}
                    {activeStatus && ` · ${statusLabel(activeStatus)}`}
                </p>
                {applications.data.length === 0 ? (
                    <div className="flex flex-col items-center gap-3 rounded-lg border border-dashed bg-card px-6 py-12 text-center">
                        <span
                            className="flex size-16 items-center justify-center rounded-full bg-accent text-primary"
                            aria-hidden="true"
                        >
                            <FolderOpen className="size-8" />
                        </span>
                        <div className="space-y-1">
                            <p className="font-bold text-foreground">
                                Aucun dossier ne correspond à ces critères.
                            </p>
                            <p className="max-w-md text-sm text-muted-foreground">
                                {hasFilters
                                    ? 'Élargissez la recherche ou réinitialisez les filtres.'
                                    : 'Les dossiers papier réceptionnés apparaîtront ici.'}
                            </p>
                        </div>
                        <div className="flex flex-wrap justify-center gap-2">
                            {hasFilters && (
                                <Button variant="outline" onClick={reset}>
                                    <RotateCcw aria-hidden="true" />
                                    Réinitialiser les filtres
                                </Button>
                            )}
                            {canReceive && (
                                <Button asChild>
                                    <Link href="/scholarships/reception">
                                        <Inbox aria-hidden="true" />
                                        Réceptionner un dossier
                                    </Link>
                                </Button>
                            )}
                        </div>
                    </div>
                ) : (
                    <div className="overflow-x-auto rounded-lg border bg-card">
                        <table className="w-full min-w-[64rem] text-left text-sm">
                            <caption className="sr-only">
                                Dossiers de bourse
                            </caption>
                            <thead className="border-b bg-muted text-muted-foreground">
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
                                    ].map((label) => (
                                        <th
                                            key={label}
                                            scope="col"
                                            className="px-4 py-3 font-medium"
                                        >
                                            {label}
                                        </th>
                                    ))}
                                    <th scope="col" className="px-4 py-3">
                                        <span className="sr-only">Actions</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {applications.data.map((item) => (
                                    <tr
                                        key={item.id}
                                        className="transition-colors hover:bg-muted/50"
                                    >
                                        <td className="px-4 py-3 font-mono text-xs font-semibold whitespace-nowrap text-foreground">
                                            {item.application_number}
                                        </td>
                                        <td className="px-4 py-3 whitespace-nowrap">
                                            {item.ddb_number ?? (
                                                <span className="text-muted-foreground">
                                                    —
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-4 py-3">
                                            <p className="font-semibold text-foreground">
                                                {item.student.last_name}{' '}
                                                {item.student.first_names}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {item.student.student_number ??
                                                    'Sans matricule'}
                                            </p>
                                        </td>
                                        <td className="px-4 py-3">
                                            {item.establishment}
                                        </td>
                                        <td className="px-4 py-3 whitespace-nowrap">
                                            {item.level}
                                        </td>
                                        <td className="px-4 py-3">
                                            {item.campaign}
                                        </td>
                                        <td className="px-4 py-3">
                                            <StatusBadge
                                                status={item.status}
                                                label={item.status_label}
                                            />
                                        </td>
                                        <td className="px-4 py-3 whitespace-nowrap tabular-nums">
                                            {formatDate(item.received_at)}
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                asChild
                                            >
                                                <Link
                                                    href={`${LIST_URL}/${item.uuid}`}
                                                    aria-label={`Ouvrir le dossier ${item.application_number}`}
                                                >
                                                    Ouvrir
                                                </Link>
                                            </Button>
                                            <TrashButton
                                                kind="applications"
                                                uuid={item.uuid}
                                                label={item.application_number}
                                            />
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
                <Pagination links={applications.links} />
            </section>
        </div>
    );
}
ApplicationsIndex.layout = {
    breadcrumbs: [{ title: 'Dossiers', href: '/scholarships/applications' }],
};
