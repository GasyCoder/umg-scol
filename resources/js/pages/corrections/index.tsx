import { Head, Link, router, useForm } from '@inertiajs/react';
import { Eye, FilePenLine, RotateCcw, Search } from 'lucide-react';
import type { FormEvent } from 'react';
import { CorrectionStatusBadge } from '@/components/corrections/status-badge';
import { EmptyState } from '@/components/empty-state';
import { IconAction } from '@/components/icon-action';
import { PageBody, PageHeader } from '@/components/page-header';
import { Pagination } from '@/components/scholarships/shared';
import type { PageLink } from '@/components/scholarships/shared';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';

type Row = {
    id: number;
    uuid: string;
    field_label: string;
    status: string;
    status_label: string;
    created_at: string;
    student: {
        last_name: string;
        first_names: string | null;
        birth_date: string | null;
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
        links: PageLink[];
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
    const selectStatus = (status: string) => {
        form.setData('status', status);
        router.get(
            '/corrections',
            { ...form.data, status },
            { preserveState: true },
        );
    };
    const selects = [
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
    const tabs: Option[] = [
        { value: 'all', label: 'Toutes' },
        ...options.statuses,
    ];
    const currentStatus = filters.status ?? 'pending';
    const narrowed = Boolean(
        filters.search || filters.field || filters.establishment_id,
    );

    return (
        <>
            <Head title="Demandes de correction" />
            <PageBody>
                <PageHeader
                    icon={FilePenLine}
                    title="Demandes de correction"
                    description="Corrections d’informations proposées par les étudiants. La fiche officielle n’est modifiée qu’après approbation."
                    meta={
                        <span className="inline-flex items-center rounded-full bg-accent px-3 py-1 text-sm font-medium text-accent-foreground tabular-nums">
                            {corrections.total} demande(s)
                        </span>
                    }
                />

                <nav aria-label="Filtrer par statut" className="-mx-1 px-1">
                    <ul className="flex w-full gap-1 overflow-x-auto rounded-xl border bg-muted/60 p-1 sm:w-fit">
                        {tabs.map((tab) => {
                            const active = currentStatus === tab.value;
                            return (
                                <li key={tab.value} className="shrink-0">
                                    <button
                                        type="button"
                                        aria-pressed={active}
                                        disabled={form.processing}
                                        onClick={() => selectStatus(tab.value)}
                                        className={cn(
                                            'inline-flex h-9 items-center rounded-lg px-4 text-sm font-medium whitespace-nowrap transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                            active
                                                ? 'bg-card text-foreground shadow-sm ring-1 ring-border'
                                                : 'text-muted-foreground hover:bg-card/60 hover:text-foreground',
                                        )}
                                    >
                                        {tab.label}
                                    </button>
                                </li>
                            );
                        })}
                    </ul>
                </nav>

                <form
                    onSubmit={submit}
                    className="surface grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-[minmax(0,2fr)_repeat(3,minmax(0,1fr))] lg:items-center"
                >
                    <div className="relative sm:col-span-2 lg:col-span-1">
                        <Search
                            className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <Input
                            aria-label="Recherche étudiant"
                            placeholder="Nom ou prénoms"
                            maxLength={100}
                            className="pl-9"
                            value={form.data.search}
                            onChange={(event) =>
                                form.setData('search', event.target.value)
                            }
                        />
                    </div>
                    {selects.map((select) => (
                        <Select
                            key={select.key}
                            value={form.data[select.key] || 'all'}
                            onValueChange={(value) =>
                                form.setData(
                                    select.key,
                                    value === 'all' ? '' : value,
                                )
                            }
                        >
                            <SelectTrigger
                                className="w-full"
                                aria-label={select.label}
                            >
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
                    <div className="flex gap-2">
                        <Button className="flex-1" disabled={form.processing}>
                            Filtrer
                        </Button>
                        {narrowed && (
                            <Button
                                variant="ghost"
                                size="icon"
                                asChild
                                aria-label="Réinitialiser les filtres"
                            >
                                <Link href="/corrections">
                                    <RotateCcw aria-hidden="true" />
                                </Link>
                            </Button>
                        )}
                    </div>
                </form>

                {corrections.data.length === 0 ? (
                    <div className="surface">
                        {narrowed ? (
                            <EmptyState
                                illustration="search"
                                title="Aucun résultat"
                                description="Aucune demande ne correspond à ces critères dans votre périmètre."
                                action={
                                    <Button variant="outline" asChild>
                                        <Link href="/corrections">
                                            Réinitialiser les filtres
                                        </Link>
                                    </Button>
                                }
                            />
                        ) : currentStatus === 'pending' ? (
                            <EmptyState
                                illustration="done"
                                title="Aucune demande en attente"
                                description="Toutes les demandes de votre périmètre ont été traitées."
                            />
                        ) : (
                            <EmptyState
                                illustration="inbox"
                                title="Aucune demande"
                                description="Aucune demande ne correspond à ce statut dans votre périmètre."
                            />
                        )}
                    </div>
                ) : (
                    <div className="surface overflow-hidden">
                        <div className="overflow-x-auto">
                            <table className="data-table min-w-[40rem]">
                                <caption className="sr-only">
                                    Demandes de correction
                                </caption>
                                <thead>
                                    <tr>
                                        <th scope="col">Étudiant</th>
                                        <th scope="col">Information</th>
                                        <th
                                            scope="col"
                                            className="hidden lg:table-cell"
                                        >
                                            Établissement
                                        </th>
                                        <th scope="col">Demandée le</th>
                                        <th scope="col">Statut</th>
                                        <th scope="col">
                                            <span className="sr-only">
                                                Actions
                                            </span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {corrections.data.map((item) => (
                                        <tr key={item.id}>
                                            <td className="max-w-64">
                                                <Link
                                                    href={`/corrections/${item.uuid}`}
                                                    className="block truncate font-semibold text-foreground hover:text-primary"
                                                >
                                                    {item.student.last_name}{' '}
                                                    {item.student.first_names}
                                                </Link>
                                                <span className="block text-xs text-muted-foreground">
                                                    {item.student.birth_date
                                                        ? `Né(e) le ${new Date(item.student.birth_date).toLocaleDateString('fr-FR')}`
                                                        : 'Date de naissance non renseignée'}
                                                </span>
                                                {item.establishments.length >
                                                    0 && (
                                                    <span className="block truncate text-xs text-muted-foreground lg:hidden">
                                                        {item.establishments.join(
                                                            ', ',
                                                        )}
                                                    </span>
                                                )}
                                            </td>
                                            <td>
                                                <span className="inline-flex items-center rounded-md bg-secondary px-2 py-0.5 text-xs font-medium text-secondary-foreground">
                                                    {item.field_label}
                                                </span>
                                            </td>
                                            <td className="hidden max-w-56 truncate text-muted-foreground lg:table-cell">
                                                {item.establishments.length > 0
                                                    ? item.establishments.join(
                                                          ', ',
                                                      )
                                                    : '—'}
                                            </td>
                                            <td className="whitespace-nowrap text-muted-foreground tabular-nums">
                                                {new Date(
                                                    item.created_at,
                                                ).toLocaleDateString('fr-FR')}
                                            </td>
                                            <td>
                                                <CorrectionStatusBadge
                                                    status={item.status}
                                                    label={item.status_label}
                                                />
                                            </td>
                                            <td>
                                                <div className="flex items-center justify-end gap-1">
                                                    <IconAction
                                                        icon={Eye}
                                                        label="Examiner la demande"
                                                        href={`/corrections/${item.uuid}`}
                                                    />
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}
                <Pagination links={corrections.links} />
            </PageBody>
        </>
    );
}
CorrectionsIndex.layout = {
    breadcrumbs: [{ title: 'Corrections', href: '/corrections' }],
};
