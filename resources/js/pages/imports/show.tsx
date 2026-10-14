import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { statuses } from './types';
import type { ImportItem, ImportRow, Page } from './types';
export default function ImportShow({
    import: item,
    rows,
    columns,
    filter,
    canAnalyze,
    canCommit,
    canExport,
}: {
    import: ImportItem;
    rows: Page<ImportRow>;
    columns: Record<string, string>;
    filter: string;
    canAnalyze: boolean;
    canCommit: boolean;
    canExport: boolean;
}) {
    const mapping = useForm<{ mapping: Record<string, number> }>({
        mapping: item.mapping ?? {},
    });
    const commit = useForm({});
    const [confirm, setConfirm] = useState(false);
    const [editMapping, setEditMapping] = useState(false);
    const analyzing =
        item.status === 'uploaded' || (item.status === 'ready' && editMapping);
    const analyze = (event: FormEvent) => {
        event.preventDefault();
        mapping.post(`/imports/${item.id}/analyze`, {
            onSuccess: () => setEditMapping(false),
        });
    };
    return (
        <div className="space-y-6 p-4 md:p-6">
            <Head title={`Import ${item.id}`} />
            <Heading
                title={
                    analyzing
                        ? '2. Colonnes'
                        : item.status === 'completed'
                          ? '5. Résultat'
                          : '3. Vérification'
                }
                description="Fichier → Colonnes → Vérification → Confirmation → Résultat"
            />
            <p>
                {item.original_filename} — {item.establishment.name} —{' '}
                {item.academic_year.name}{' '}
                <Badge variant="secondary">{statuses[item.status]}</Badge>
            </p>
            {item.failure_reason && <p role="alert">{item.failure_reason}</p>}
            {analyzing && canAnalyze ? (
                <form onSubmit={analyze} className="space-y-5">
                    <p>
                        Associez chaque champ à une colonne. Nom et niveau sont
                        obligatoires. Les autres colonnes peuvent être ignorées.
                    </p>
                    <div className="grid gap-4 md:grid-cols-2">
                        {Object.entries(columns).map(([field, label]) => (
                            <div key={field} className="space-y-2">
                                <Label htmlFor={`map-${field}`}>
                                    {label}
                                    {['last_name', 'level'].includes(field)
                                        ? ' *'
                                        : ''}
                                </Label>
                                <Select
                                    value={
                                        mapping.data.mapping[field] ===
                                        undefined
                                            ? 'ignore'
                                            : String(
                                                  mapping.data.mapping[field],
                                              )
                                    }
                                    onValueChange={(value) => {
                                        const next = {
                                            ...mapping.data.mapping,
                                        };
                                        if (value === 'ignore')
                                            delete next[field];
                                        else next[field] = Number(value);
                                        mapping.setData('mapping', next);
                                    }}
                                >
                                    <SelectTrigger id={`map-${field}`}>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="ignore">
                                            Ignorer
                                        </SelectItem>
                                        {item.headers.map((header, index) => (
                                            <SelectItem
                                                key={index}
                                                value={String(index)}
                                            >
                                                {index + 1}.{' '}
                                                {header || '(sans titre)'}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError
                                    message={
                                        (
                                            mapping.errors as Record<
                                                string,
                                                string
                                            >
                                        )[`mapping.${field}`]
                                    }
                                />
                            </div>
                        ))}
                    </div>
                    <InputError message={mapping.errors.mapping} />
                    <Button disabled={mapping.processing}>
                        {mapping.processing
                            ? 'Analyse…'
                            : 'Analyser les lignes'}
                    </Button>
                </form>
            ) : (
                <>
                    <div className="grid grid-cols-2 gap-3 md:grid-cols-5">
                        {[
                            ['Total', item.total_rows],
                            ['Valides', item.valid_rows],
                            ['Avertissements', item.warning_rows],
                            ['Rejetées', item.rejected_rows],
                            ['Doublons', item.duplicate_rows],
                        ].map(([label, count]) => (
                            <div key={label} className="rounded-md border p-3">
                                <p className="text-sm text-muted-foreground">
                                    {label}
                                </p>
                                <p className="text-xl font-semibold">{count}</p>
                            </div>
                        ))}
                    </div>
                    {item.status === 'completed' && (
                        <p role="status">
                            Import terminé : {item.created_rows} identités
                            créées, {item.updated_rows} identités complétées,{' '}
                            {item.declared_rows} déclarations créées,{' '}
                            {item.skipped_rows} lignes ignorées.
                        </p>
                    )}
                    <div className="flex flex-wrap gap-3">
                        <Select
                            value={filter || 'all'}
                            onValueChange={(value) =>
                                router.get(
                                    `/imports/${item.id}`,
                                    value === 'all' ? {} : { status: value },
                                    { preserveState: true },
                                )
                            }
                        >
                            <SelectTrigger
                                className="w-52"
                                aria-label="Filtrer les lignes"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">
                                    Toutes les lignes
                                </SelectItem>
                                {(
                                    [
                                        'valid',
                                        'warning',
                                        'rejected',
                                        'duplicate',
                                    ] as const
                                ).map((status) => (
                                    <SelectItem key={status} value={status}>
                                        {statuses[status]}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {canExport && (
                            <Button variant="outline" asChild>
                                <a href={`/imports/${item.id}/errors`}>
                                    Télécharger les erreurs
                                </a>
                            </Button>
                        )}
                        {item.status === 'ready' && canAnalyze && (
                            <Button
                                variant="outline"
                                onClick={() => setEditMapping(true)}
                            >
                                Corriger les colonnes
                            </Button>
                        )}
                        {item.status === 'ready' && canCommit && (
                            <Button
                                disabled={
                                    item.valid_rows === 0 || commit.processing
                                }
                                onClick={() => setConfirm(true)}
                            >
                                Confirmer l’import
                            </Button>
                        )}
                    </div>
                    <InputError
                        message={
                            (commit.errors as Record<string, string>).import
                        }
                    />
                    <div className="overflow-x-auto rounded-md border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    {[
                                        'Ligne',
                                        'Identité',
                                        'Matricule',
                                        'Niveau / parcours',
                                        'Statut',
                                        'Observations',
                                    ].map((label) => (
                                        <TableHead key={label}>
                                            {label}
                                        </TableHead>
                                    ))}
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {rows.data.length === 0 ? (
                                    <TableRow>
                                        <TableCell colSpan={6}>
                                            Aucune ligne pour ce filtre.
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    rows.data.map((row) => (
                                        <TableRow key={row.id}>
                                            <TableCell>
                                                {row.row_number}
                                            </TableCell>
                                            <TableCell>
                                                {row.data.last_name}{' '}
                                                {row.data.first_names}
                                                {row.matched_student_id && (
                                                    <p className="text-sm text-muted-foreground">
                                                        Identité existante #
                                                        {row.matched_student_id}
                                                    </p>
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                {row.data.student_number ?? '—'}
                                            </TableCell>
                                            <TableCell>
                                                {row.data.level} /{' '}
                                                {row.data.program ?? '—'}
                                            </TableCell>
                                            <TableCell>
                                                <Badge
                                                    variant={
                                                        row.status ===
                                                        'rejected'
                                                            ? 'destructive'
                                                            : 'secondary'
                                                    }
                                                >
                                                    {statuses[row.status]}
                                                </Badge>
                                                {row.resolution ===
                                                    'imported' && (
                                                    <p>Importée</p>
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                {[
                                                    ...(row.errors ?? []),
                                                    ...(row.warnings ?? []),
                                                ].map((issue, i) => (
                                                    <p key={i}>
                                                        {columns[issue.field] ??
                                                            issue.field}{' '}
                                                        : {issue.message}
                                                    </p>
                                                ))}
                                                {row.status === 'duplicate' &&
                                                    !row.warnings?.length && (
                                                        <p>
                                                            Déclaration
                                                            identique déjà
                                                            présente.
                                                        </p>
                                                    )}
                                            </TableCell>
                                        </TableRow>
                                    ))
                                )}
                            </TableBody>
                        </Table>
                    </div>
                    <nav
                        aria-label="Pagination"
                        className="flex flex-wrap gap-2"
                    >
                        {rows.links.map((link, i) =>
                            link.url ? (
                                <Button
                                    key={i}
                                    variant={
                                        link.active ? 'default' : 'outline'
                                    }
                                    asChild
                                >
                                    <Link href={link.url} preserveState>
                                        {i === 0
                                            ? 'Précédent'
                                            : i === rows.links.length - 1
                                              ? 'Suivant'
                                              : link.label}
                                    </Link>
                                </Button>
                            ) : null,
                        )}
                    </nav>
                </>
            )}
            <Button variant="outline" asChild>
                <Link href="/imports">Historique des imports</Link>
            </Button>
            <Dialog
                open={confirm}
                onOpenChange={(open) => {
                    if (!commit.processing) setConfirm(open);
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>4. Confirmer l’import</DialogTitle>
                        <DialogDescription>
                            Seules les {item.valid_rows} lignes valides seront
                            traitées. Les avertissements, rejets et doublons
                            seront ignorés. Les données officielles
                            contradictoires ne seront jamais écrasées.
                        </DialogDescription>
                    </DialogHeader>
                    <InputError
                        message={
                            (commit.errors as Record<string, string>).import
                        }
                    />
                    <DialogFooter>
                        <Button
                            variant="outline"
                            disabled={commit.processing}
                            onClick={() => setConfirm(false)}
                        >
                            Annuler
                        </Button>
                        <Button
                            disabled={commit.processing}
                            onClick={() =>
                                commit.post(`/imports/${item.id}/commit`, {
                                    onSuccess: () => setConfirm(false),
                                })
                            }
                        >
                            {commit.processing
                                ? 'Import…'
                                : 'Confirmer l’import'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
ImportShow.layout = { breadcrumbs: [{ title: 'Imports', href: '/imports' }] };
