import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
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
import { article, baseUrl, definitions } from './definitions';
import type {
    EstablishmentOption,
    ReferenceKind,
    ReferenceRecord,
} from './definitions';

type Pagination = {
    data: ReferenceRecord[];
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
};
export type ReferenceListProps = {
    records: Pagination;
    filters: { search?: string; establishment_id?: string };
    canCreate: boolean;
    establishments?: EstablishmentOption[];
};
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
            `${baseUrl(kind)}/${confirmation.record.id}/${confirmation.current ? 'current' : 'status'}`,
            { preserveScroll: true, onSuccess: () => setConfirmation(null) },
        );
    };
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
    const cells = (row: ReferenceRecord) =>
        kind === 'establishments'
            ? [
                  row.code,
                  row.name,
                  row.type ?? '—',
                  [row.phone, row.email].filter(Boolean).join(' · ') || '—',
              ]
            : kind === 'academic-years'
              ? [
                    row.name,
                    `${row.starts_at?.slice(0, 10) ?? '—'} / ${row.ends_at?.slice(0, 10) ?? '—'}`,
                    row.is_current ? 'Courante' : '—',
                ]
              : kind === 'levels'
                ? [row.code, row.name, String(row.sort_order)]
                : kind === 'programs'
                  ? [
                        row.establishment?.name ?? '—',
                        row.mention?.name ?? 'Sans mention',
                        row.code ?? '—',
                        row.name,
                    ]
                  : [row.establishment?.name ?? '—', row.code ?? '—', row.name];
    return (
        <div className="space-y-6 p-4 md:p-6">
            <Head title={definition.title} />
            <div className="flex flex-wrap items-start justify-between gap-4">
                <Heading
                    title={definition.title}
                    description="Administration des référentiels institutionnels."
                />
                {canCreate && (
                    <Button asChild>
                        <Link href={`${baseUrl(kind)}/create`}>
                            Créer {articleText} {definition.singular}
                        </Link>
                    </Button>
                )}
            </div>
            <form
                onSubmit={filter}
                className="flex flex-wrap items-center gap-3"
            >
                <Input
                    aria-label="Recherche"
                    placeholder="Rechercher…"
                    className="w-full sm:max-w-xs"
                    value={search.data.search}
                    onChange={(event) =>
                        search.setData('search', event.target.value)
                    }
                    maxLength={100}
                />
                {(kind === 'programs' || kind === 'mentions') && (
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
                <Button variant="outline" disabled={search.processing}>
                    Rechercher
                </Button>
                <Button variant="ghost" asChild>
                    <Link href={baseUrl(kind)}>Réinitialiser</Link>
                </Button>
                <InputError
                    message={
                        search.errors.search ?? search.errors.establishment_id
                    }
                />
            </form>
            <div className="overflow-x-auto rounded-lg border">
                <table className="w-full text-left text-sm">
                    <caption className="sr-only">{definition.title}</caption>
                    <thead className="bg-muted">
                        <tr>
                            {[...columns, 'Statut', 'Actions'].map((column) => (
                                <th
                                    scope="col"
                                    key={column}
                                    className="px-4 py-3 font-medium"
                                >
                                    {column}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {records.data.length === 0 ? (
                            <tr>
                                <td
                                    colSpan={columns.length + 2}
                                    className="p-8 text-center text-muted-foreground"
                                >
                                    Aucun résultat. Modifiez les filtres ou
                                    créez un référentiel.
                                </td>
                            </tr>
                        ) : (
                            records.data.map((row) => (
                                <tr key={row.id} className="border-t">
                                    {cells(row).map((cell, index) => (
                                        <td className="px-4 py-3" key={index}>
                                            {cell}
                                        </td>
                                    ))}
                                    <td className="px-4 py-3">
                                        <Badge
                                            variant={
                                                row.is_active
                                                    ? 'secondary'
                                                    : 'outline'
                                            }
                                        >
                                            {row.is_active
                                                ? 'Actif'
                                                : 'Inactif'}
                                        </Badge>
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex flex-wrap gap-2">
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                asChild
                                            >
                                                <Link
                                                    href={`${baseUrl(kind)}/${row.id}/edit`}
                                                >
                                                    Modifier
                                                </Link>
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() => open(row)}
                                            >
                                                {row.is_active
                                                    ? 'Désactiver'
                                                    : 'Activer'}
                                            </Button>
                                            {kind === 'academic-years' &&
                                                !row.is_current &&
                                                row.is_active && (
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() =>
                                                            open(row, true)
                                                        }
                                                    >
                                                        Définir comme année
                                                        courante
                                                    </Button>
                                                )}
                                        </div>
                                    </td>
                                </tr>
                            ))
                        )}
                    </tbody>
                </table>
            </div>
            <div className="flex flex-wrap items-center justify-between gap-3">
                <p className="text-sm text-muted-foreground">
                    {records.total} résultat(s)
                </p>
                <nav aria-label="Pagination" className="flex flex-wrap gap-1">
                    {records.links.map((link, index) => {
                        const label =
                            index === 0
                                ? 'Précédent'
                                : index === records.links.length - 1
                                  ? 'Suivant'
                                  : link.label.replace('&hellip;', '…');
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
            <Dialog
                open={confirmation !== null}
                onOpenChange={(openState) => {
                    if (!openState && !action.processing) setConfirmation(null);
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            Confirmer{' '}
                            {confirmation?.current
                                ? 'le changement d’année courante'
                                : 'le changement de statut'}
                        </DialogTitle>
                        <DialogDescription>
                            {confirmation?.record.name}
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
                            {action.processing ? 'Traitement…' : 'Confirmer'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
