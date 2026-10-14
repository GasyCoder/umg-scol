import { Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { statuses } from './types';
import type { ImportItem, Page } from './types';
export default function ImportsIndex({
    imports,
    canCreate,
}: {
    imports: Page<ImportItem>;
    canCreate: boolean;
}) {
    return (
        <div className="space-y-6 p-4 md:p-6">
            <Head title="Imports des étudiants" />
            <div className="flex flex-wrap justify-between gap-3">
                <Heading
                    title="Imports des étudiants"
                    description="Déclarations d’étudiants déjà régulièrement inscrits dans leur établissement."
                />
                {canCreate && (
                    <Button asChild>
                        <Link href="/imports/create">Importer un fichier</Link>
                    </Button>
                )}
            </div>
            <div className="overflow-x-auto rounded-md border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            {[
                                'Fichier',
                                'Établissement / année',
                                'Auteur / date',
                                'Statut',
                                'Total',
                                'Valides',
                                'Avertissements / rejets / doublons',
                                'Identités créées / complétées',
                                'Déclarations / ignorées',
                            ].map((x) => (
                                <TableHead key={x}>{x}</TableHead>
                            ))}
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {imports.data.length === 0 ? (
                            <TableRow>
                                <TableCell colSpan={9}>
                                    Aucun import pour le moment.
                                </TableCell>
                            </TableRow>
                        ) : (
                            imports.data.map((item) => (
                                <TableRow key={item.id}>
                                    <TableCell>
                                        <Link
                                            className="underline"
                                            href={`/imports/${item.id}`}
                                        >
                                            {item.original_filename}
                                        </Link>
                                    </TableCell>
                                    <TableCell>
                                        {item.establishment.name}
                                        <br />
                                        {item.academic_year.name}
                                    </TableCell>
                                    <TableCell>
                                        {item.user.name}
                                        <br />
                                        {new Date(
                                            item.created_at,
                                        ).toLocaleDateString('fr-FR')}
                                    </TableCell>
                                    <TableCell>
                                        <Badge variant="secondary">
                                            {statuses[item.status]}
                                        </Badge>
                                    </TableCell>
                                    <TableCell>{item.total_rows}</TableCell>
                                    <TableCell>{item.valid_rows}</TableCell>
                                    <TableCell>
                                        {item.warning_rows} /{' '}
                                        {item.rejected_rows} /{' '}
                                        {item.duplicate_rows}
                                    </TableCell>
                                    <TableCell>
                                        {item.created_rows} /{' '}
                                        {item.updated_rows}
                                    </TableCell>
                                    <TableCell>
                                        {item.declared_rows} /{' '}
                                        {item.skipped_rows}
                                    </TableCell>
                                </TableRow>
                            ))
                        )}
                    </TableBody>
                </Table>
            </div>
            <nav aria-label="Pagination" className="flex flex-wrap gap-2">
                {imports.links.map((link, i) =>
                    link.url ? (
                        <Button
                            key={i}
                            variant={link.active ? 'default' : 'outline'}
                            asChild
                        >
                            <Link href={link.url}>
                                {i === 0
                                    ? 'Précédent'
                                    : i === imports.links.length - 1
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
ImportsIndex.layout = { breadcrumbs: [{ title: 'Imports', href: '/imports' }] };
