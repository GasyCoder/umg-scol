import { Head, Link, useForm } from '@inertiajs/react';
import {
    ArrowRight,
    Mail,
    Pencil,
    Phone,
    Plus,
    Power,
    PowerOff,
    RotateCcw,
    Search,
    Star,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import { EmptyState } from '@/components/empty-state';
import InputError from '@/components/input-error';
import { PageBody, PageHeader } from '@/components/page-header';
import { formatNumber } from '@/components/reporting/shared';
import { Pagination } from '@/components/scholarships/shared';
import { ToneBadge } from '@/components/tone-badge';
import { TrashButton } from '@/components/trash/trash-button';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';
import { article, baseUrl, definitions } from './definitions';
import type {
    EstablishmentOption,
    ReferenceKind,
    ReferenceRecord,
} from './definitions';
import { formatDay, listDescriptions, referenceIcons } from './reference-meta';
import { ReferenceTransferToolbar } from './reference-transfer';

type Paginated = {
    data: ReferenceRecord[];
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
};
export type ReferenceListProps = {
    records: Paginated;
    filters: { search?: string; establishment_id?: string };
    canCreate: boolean;
    establishments?: EstablishmentOption[];
};

function Code({ value }: { value?: string | null }) {
    if (!value) return <span className="text-muted-foreground">—</span>;
    return (
        <span className="inline-flex shrink-0 rounded-md border bg-muted/60 px-1.5 py-0.5 font-mono text-xs font-semibold whitespace-nowrap text-foreground">
            {value}
        </span>
    );
}

function Muted({ children }: { children: ReactNode }) {
    return <span className="text-muted-foreground">{children}</span>;
}

export default function ReferenceList({
    kind,
    records,
    filters,
    canCreate,
    establishments = [],
}: ReferenceListProps & { kind: ReferenceKind }) {
    const definition = definitions[kind];
    const articleText = article(kind);
    const search = useForm({
        search: filters.search ?? '',
        establishment_id: filters.establishment_id
            ? String(filters.establishment_id)
            : '',
    });
    const action = useForm({ is_active: true });
    const [confirmation, setConfirmation] = useState<{
        record: ReferenceRecord;
        current: boolean;
    } | null>(null);
    const filter = (event: FormEvent) => {
        event.preventDefault();
        search.get(baseUrl(kind), { preserveState: true });
    };
    const open = (record: ReferenceRecord, current = false) => {
        action.clearErrors();
        action.setData('is_active', !record.is_active);
        setConfirmation({ record, current });
    };
    const confirm = () => {
        if (!confirmation) return;
        action.patch(
            `${baseUrl(kind)}/${confirmation.record.uuid}/${confirmation.current ? 'current' : 'status'}`,
            { preserveScroll: true, onSuccess: () => setConfirmation(null) },
        );
    };
    const byEstablishment = kind === 'programs' || kind === 'mentions';
    const filtered = Boolean(filters.search || filters.establishment_id);
    const Icon = referenceIcons[kind];
    const createLabel = `Créer ${articleText} ${definition.singular}`;
    const name = (row: ReferenceRecord) => (
        <span
            className={cn(
                'font-semibold',
                row.is_active ? 'text-foreground' : 'text-muted-foreground',
            )}
        >
            {row.name}
        </span>
    );
    const establishment = (row: ReferenceRecord) =>
        row.establishment ? (
            <span className="flex min-w-0 items-center gap-2">
                <Code value={row.establishment.code} />
                <span className="truncate">{row.establishment.name}</span>
            </span>
        ) : (
            <Muted>—</Muted>
        );
    const columns =
        kind === 'establishments'
            ? ['Code', 'Nom', 'Type', 'Contact']
            : kind === 'academic-years'
              ? ['Nom', 'Dates', 'Année courante']
              : kind === 'levels'
                ? ['Code', 'Nom', 'Ordre']
                : kind === 'programs'
                  ? ['Établissement', 'Mention', 'Code', 'Nom']
                  : ['Établissement', 'Code', 'Nom'];
    const cells = (row: ReferenceRecord): ReactNode[] =>
        kind === 'establishments'
            ? [
                  <Code key="code" value={row.code} />,
                  name(row),
                  row.type ?? <Muted>—</Muted>,
                  row.phone || row.email ? (
                      <span className="flex flex-col gap-0.5 text-xs">
                          {row.phone && (
                              <span className="inline-flex items-center gap-1.5">
                                  <Phone
                                      className="size-3.5 text-muted-foreground"
                                      aria-hidden="true"
                                  />
                                  {row.phone}
                              </span>
                          )}
                          {row.email && (
                              <span className="inline-flex items-center gap-1.5">
                                  <Mail
                                      className="size-3.5 text-muted-foreground"
                                      aria-hidden="true"
                                  />
                                  {row.email}
                              </span>
                          )}
                      </span>
                  ) : (
                      <Muted>—</Muted>
                  ),
              ]
            : kind === 'academic-years'
              ? [
                    name(row),
                    <span
                        key="dates"
                        className="inline-flex items-center gap-1.5 text-sm tabular-nums"
                    >
                        {formatDay(row.starts_at) ?? <Muted>—</Muted>}
                        <ArrowRight
                            className="size-3.5 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <span className="sr-only">au</span>
                        {formatDay(row.ends_at) ?? <Muted>—</Muted>}
                    </span>,
                    row.is_current ? (
                        <ToneBadge key="current" tone="primary">
                            Courante
                        </ToneBadge>
                    ) : (
                        <Muted>—</Muted>
                    ),
                ]
              : kind === 'levels'
                ? [
                      <Code key="code" value={row.code} />,
                      name(row),
                      <span key="order" className="tabular-nums">
                          {row.sort_order}
                      </span>,
                  ]
                : kind === 'programs'
                  ? [
                        establishment(row),
                        row.mention?.name ?? <Muted>Sans mention</Muted>,
                        <Code key="code" value={row.code} />,
                        name(row),
                    ]
                  : [
                        establishment(row),
                        <Code key="code" value={row.code} />,
                        name(row),
                    ];

    return (
        <PageBody>
            <Head title={definition.title} />
            <PageHeader
                icon={Icon}
                title={definition.title}
                description={listDescriptions[kind]}
                meta={
                    <span className="inline-flex items-center gap-1.5 rounded-full bg-secondary px-3 py-1 text-xs font-semibold text-muted-foreground tabular-nums">
                        {formatNumber(records.total)} résultat(s)
                        {filtered ? ' filtré(s)' : ''}
                    </span>
                }
                actions={
                    canCreate && (
                        <Button asChild>
                            <Link href={`${baseUrl(kind)}/create`}>
                                <Plus aria-hidden="true" />
                                {createLabel}
                            </Link>
                        </Button>
                    )
                }
            />

            {canCreate && byEstablishment && (
                <ReferenceTransferToolbar kind={kind} filters={filters} />
            )}

            <form
                onSubmit={filter}
                className="surface flex flex-col gap-3 p-3 sm:flex-row sm:flex-wrap sm:items-center sm:p-4"
                role="search"
                aria-label={`Rechercher dans ${definition.title.toLowerCase()}`}
            >
                <div className="relative w-full sm:max-w-xs sm:flex-1">
                    <Search
                        className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <Input
                        aria-label="Recherche"
                        placeholder={
                            kind === 'academic-years'
                                ? 'Rechercher une année…'
                                : 'Rechercher par nom ou code…'
                        }
                        className="pl-9"
                        value={search.data.search}
                        onChange={(event) =>
                            search.setData('search', event.target.value)
                        }
                        maxLength={100}
                    />
                </div>
                {byEstablishment && (
                    <Select
                        value={search.data.establishment_id || 'all'}
                        onValueChange={(value) =>
                            search.setData(
                                'establishment_id',
                                value === 'all' ? '' : value,
                            )
                        }
                    >
                        <SelectTrigger
                            className="w-full sm:w-72"
                            aria-label="Filtrer par établissement"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">
                                Tous les établissements
                            </SelectItem>
                            {establishments.map((option) => (
                                <SelectItem
                                    key={option.id}
                                    value={String(option.id)}
                                >
                                    {option.code} — {option.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                )}
                <div className="flex gap-2 sm:ml-auto">
                    <Button
                        variant="outline"
                        className="flex-1 sm:flex-none"
                        disabled={search.processing}
                    >
                        {search.processing ? (
                            <Spinner />
                        ) : (
                            <Search aria-hidden="true" />
                        )}
                        Rechercher
                    </Button>
                    <Button
                        variant="ghost"
                        className="flex-1 sm:flex-none"
                        asChild
                    >
                        <Link href={baseUrl(kind)}>
                            <RotateCcw aria-hidden="true" />
                            Réinitialiser
                        </Link>
                    </Button>
                </div>
                <InputError
                    className="w-full"
                    message={
                        search.errors.search ?? search.errors.establishment_id
                    }
                />
            </form>

            <section className="surface overflow-hidden">
                {records.data.length === 0 ? (
                    filtered ? (
                        <EmptyState
                            illustration="search"
                            title="Aucun résultat"
                            description="Modifiez la recherche ou les filtres pour élargir la liste."
                            action={
                                <Button variant="outline" asChild>
                                    <Link href={baseUrl(kind)}>
                                        <RotateCcw aria-hidden="true" />
                                        Réinitialiser les filtres
                                    </Link>
                                </Button>
                            }
                        />
                    ) : (
                        <EmptyState
                            illustration="inbox"
                            title="Aucun élément pour le moment"
                            description={
                                canCreate
                                    ? 'Créez le premier élément de ce référentiel pour le proposer dans les formulaires.'
                                    : 'Les éléments de ce référentiel apparaîtront ici.'
                            }
                            action={
                                canCreate && (
                                    <Button asChild>
                                        <Link href={`${baseUrl(kind)}/create`}>
                                            <Plus aria-hidden="true" />
                                            {createLabel}
                                        </Link>
                                    </Button>
                                )
                            }
                        />
                    )
                ) : (
                    <div className="overflow-x-auto">
                        <table className="data-table min-w-[44rem]">
                            <caption className="sr-only">
                                {definition.title}
                            </caption>
                            <thead>
                                <tr>
                                    {columns.map((column) => (
                                        <th scope="col" key={column}>
                                            {column}
                                        </th>
                                    ))}
                                    <th scope="col">Statut</th>
                                    <th scope="col" className="text-right">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {records.data.map((row) => (
                                    <tr key={row.id}>
                                        {cells(row).map((cell, index) => (
                                            <td key={index}>{cell}</td>
                                        ))}
                                        <td>
                                            <ToneBadge
                                                tone={
                                                    row.is_active
                                                        ? 'success'
                                                        : 'neutral'
                                                }
                                            >
                                                {row.is_active
                                                    ? 'Actif'
                                                    : 'Inactif'}
                                            </ToneBadge>
                                        </td>
                                        <td>
                                            <div
                                                className="flex flex-wrap items-center justify-end gap-1.5"
                                                role="group"
                                                aria-label={`Actions pour ${row.name}`}
                                            >
                                                {kind === 'academic-years' &&
                                                    !row.is_current &&
                                                    row.is_active && (
                                                        <Button
                                                            size="sm"
                                                            variant="ghost"
                                                            className="text-primary hover:text-primary"
                                                            onClick={() =>
                                                                open(row, true)
                                                            }
                                                        >
                                                            <Star aria-hidden="true" />
                                                            Définir comme année
                                                            courante
                                                        </Button>
                                                    )}
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    asChild
                                                >
                                                    <Link
                                                        href={`${baseUrl(kind)}/${row.uuid}/edit`}
                                                    >
                                                        <Pencil aria-hidden="true" />
                                                        Modifier
                                                    </Link>
                                                </Button>
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() => open(row)}
                                                >
                                                    {row.is_active ? (
                                                        <PowerOff aria-hidden="true" />
                                                    ) : (
                                                        <Power aria-hidden="true" />
                                                    )}
                                                    {row.is_active
                                                        ? 'Désactiver'
                                                        : 'Activer'}
                                                </Button>
                                                <TrashButton
                                                    kind={kind}
                                                    uuid={row.uuid}
                                                    label={row.name}
                                                />
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </section>

            {records.data.length > 0 && (
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <p className="text-sm text-muted-foreground">
                        {formatNumber(records.total)} résultat(s)
                    </p>
                    <Pagination links={records.links} />
                </div>
            )}

            <Dialog
                open={confirmation !== null}
                onOpenChange={(openState) => {
                    if (!openState && !action.processing) setConfirmation(null);
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <span
                            className={cn(
                                'mb-1 flex size-11 items-center justify-center rounded-xl',
                                confirmation?.current
                                    ? 'bg-accent text-primary'
                                    : confirmation?.record.is_active
                                      ? 'bg-warning-soft text-warning'
                                      : 'bg-success-soft text-success',
                            )}
                            aria-hidden="true"
                        >
                            {confirmation?.current ? (
                                <Star className="size-5" />
                            ) : confirmation?.record.is_active ? (
                                <PowerOff className="size-5" />
                            ) : (
                                <Power className="size-5" />
                            )}
                        </span>
                        <DialogTitle>
                            Confirmer{' '}
                            {confirmation?.current
                                ? 'le changement d’année courante'
                                : 'le changement de statut'}
                        </DialogTitle>
                        <DialogDescription>
                            <span className="font-semibold text-foreground">
                                {confirmation?.record.name}
                            </span>
                            {confirmation?.current
                                ? ' deviendra l’année courante. L’année précédente perdra ce statut.'
                                : confirmation?.record.is_active
                                  ? ' sera désactivé. Les données et relations seront conservées.'
                                  : ' sera activé.'}
                        </DialogDescription>
                    </DialogHeader>
                    <InputError message={Object.values(action.errors)[0]} />
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setConfirmation(null)}
                            disabled={action.processing}
                        >
                            Annuler
                        </Button>
                        <Button onClick={confirm} disabled={action.processing}>
                            {action.processing && <Spinner />}
                            {action.processing ? 'Traitement…' : 'Confirmer'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </PageBody>
    );
}
