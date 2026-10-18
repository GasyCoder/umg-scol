import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    AlertTriangle,
    Building2,
    CalendarDays,
    FileCheck2,
    FileUp,
    FolderOpen,
    GraduationCap,
    Layers,
    Library,
    RotateCcw,
    Route,
    Search,
    Trash2,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { EmptyState } from '@/components/empty-state';
import { IconAction } from '@/components/icon-action';
import InputError from '@/components/input-error';
import { PageBody, PageHeader } from '@/components/page-header';
import { Pagination, formatDate } from '@/components/scholarships/shared';
import type { PageLink } from '@/components/scholarships/shared';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { cn } from '@/lib/utils';
type Item = {
    uuid: string;
    label: string;
    deleted_at: string;
    deleted_by: number | null;
};

const kindIcons: Record<string, LucideIcon> = {
    establishments: Building2,
    'academic-years': CalendarDays,
    levels: Layers,
    mentions: Library,
    programs: Route,
    imports: FileUp,
    students: GraduationCap,
    'student-records': FileCheck2,
    applications: FolderOpen,
};

export default function Trash({
    kind,
    kinds,
    records,
    filters,
    canPurge,
    pendingFileCleanupCount,
}: {
    kind: string;
    kinds: Record<string, string>;
    records: { data: Item[]; links: PageLink[]; total?: number };
    filters: { search?: string };
    canPurge: boolean;
    pendingFileCleanupCount: number;
}) {
    const filter = useForm({ kind, search: filters.search ?? '' });
    const [selection, setSelection] = useState<{
        item: Item;
        action: 'restore' | 'purge';
    } | null>(null);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');
    const submit = (event: FormEvent) => {
        event.preventDefault();
        filter.get('/trash');
    };
    const selectKind = (value: string) => {
        filter.setData('kind', value);
        router.get('/trash', { ...filter.data, kind: value });
    };
    const confirm = () => {
        if (!selection) return;
        setBusy(true);
        const url = `/trash/${kind}/${selection.item.uuid}/${selection.action}`;
        const options = {
            preserveScroll: true,
            onError: (errors: Record<string, string>) =>
                setError(
                    errors.trash ??
                        Object.values(errors)[0] ??
                        'Opération refusée.',
                ),
            onSuccess: () => setSelection(null),
            onFinish: () => setBusy(false),
        };
        if (selection.action === 'restore') router.post(url, {}, options);
        else router.delete(url, options);
    };
    const choose = (item: Item, action: 'restore' | 'purge') => {
        setError('');
        setSelection({ item, action });
    };
    const KindIcon = kindIcons[kind] ?? Trash2;
    const count = records.total ?? records.data.length;
    const purge = selection?.action === 'purge';
    return (
        <>
            <Head title="Corbeille" />
            <PageBody>
                <PageHeader
                    icon={Trash2}
                    title="Corbeille"
                    description="Les suppressions sont réversibles. La purge est définitive et reste soumise aux protections de l’historique."
                />
                {pendingFileCleanupCount > 0 && (
                    <p
                        role="status"
                        className="flex items-start gap-3 rounded-xl border border-info/30 bg-info-soft p-4 text-sm text-info-foreground"
                    >
                        <RotateCcw
                            className="mt-0.5 size-4 shrink-0"
                            aria-hidden="true"
                        />
                        {pendingFileCleanupCount} suppression(s) de fichiers
                        sont en attente de reprise automatique. Les données ont
                        été purgées ; le nettoyage du stockage sera réessayé.
                    </p>
                )}

                <nav aria-label="Type d’élément" className="-mx-1 px-1">
                    <ul className="flex gap-2 overflow-x-auto pb-1 lg:flex-wrap">
                        {Object.entries(kinds).map(([value, label]) => {
                            const Icon = kindIcons[value] ?? Trash2;
                            const active = value === kind;
                            return (
                                <li key={value} className="shrink-0">
                                    <button
                                        type="button"
                                        aria-pressed={active}
                                        onClick={() => selectKind(value)}
                                        className={cn(
                                            'inline-flex h-9 items-center gap-2 rounded-full border px-3.5 text-sm font-medium whitespace-nowrap transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                            active
                                                ? 'border-primary bg-primary text-primary-foreground shadow-sm'
                                                : 'bg-card text-muted-foreground hover:border-primary/40 hover:text-foreground',
                                        )}
                                    >
                                        <Icon
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        {label}
                                        {active && (
                                            <span className="rounded-full bg-primary-foreground/20 px-1.5 text-xs font-semibold tabular-nums">
                                                {count}
                                            </span>
                                        )}
                                    </button>
                                </li>
                            );
                        })}
                    </ul>
                </nav>

                <section className="surface overflow-hidden">
                    <form
                        onSubmit={submit}
                        className="flex flex-col gap-3 border-b p-4 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <h2 className="flex items-center gap-2 text-base font-bold text-foreground">
                            <KindIcon
                                className="size-4 text-primary"
                                aria-hidden="true"
                            />
                            {kinds[kind] ?? kind}
                        </h2>
                        <div className="flex gap-2">
                            <div className="relative flex-1 sm:w-72">
                                <Search
                                    className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                    aria-hidden="true"
                                />
                                <Input
                                    className="pl-9"
                                    aria-label="Rechercher"
                                    placeholder="Rechercher…"
                                    value={filter.data.search}
                                    onChange={(event) =>
                                        filter.setData(
                                            'search',
                                            event.target.value,
                                        )
                                    }
                                />
                            </div>
                            <Button disabled={filter.processing}>
                                Filtrer
                            </Button>
                        </div>
                    </form>
                    {records.data.length === 0 ? (
                        filters.search ? (
                            <EmptyState
                                illustration="search"
                                title="Aucun résultat"
                                description="Aucun élément supprimé ne correspond à cette recherche."
                                action={
                                    <Button variant="outline" asChild>
                                        <Link href={`/trash?kind=${kind}`}>
                                            Effacer la recherche
                                        </Link>
                                    </Button>
                                }
                            />
                        ) : (
                            <EmptyState
                                illustration="done"
                                title="Corbeille vide"
                                description="La corbeille est vide pour cette catégorie."
                            />
                        )
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="data-table min-w-[36rem]">
                                <caption className="sr-only">
                                    Éléments supprimés : {kinds[kind] ?? kind}
                                </caption>
                                <thead>
                                    <tr>
                                        <th scope="col">Élément</th>
                                        <th scope="col">Supprimé le</th>
                                        <th
                                            scope="col"
                                            className="hidden md:table-cell"
                                        >
                                            Par
                                        </th>
                                        <th scope="col" className="text-right">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {records.data.map((item) => (
                                        <tr key={item.uuid}>
                                            <td className="max-w-72">
                                                <span className="flex items-center gap-3">
                                                    <span
                                                        className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-secondary text-muted-foreground"
                                                        aria-hidden="true"
                                                    >
                                                        <KindIcon className="size-4" />
                                                    </span>
                                                    <span className="truncate font-medium text-foreground">
                                                        {item.label}
                                                    </span>
                                                </span>
                                            </td>
                                            <td className="whitespace-nowrap text-muted-foreground tabular-nums">
                                                {formatDate(item.deleted_at)}
                                                <span className="block text-xs md:hidden">
                                                    compte #
                                                    {item.deleted_by ??
                                                        'système'}
                                                </span>
                                            </td>
                                            <td className="hidden text-muted-foreground md:table-cell">
                                                compte #
                                                {item.deleted_by ?? 'système'}
                                            </td>
                                            <td>
                                                <div className="flex items-center justify-end gap-1">
                                                    <IconAction
                                                        icon={RotateCcw}
                                                        label="Restaurer"
                                                        tone="success"
                                                        onClick={() =>
                                                            choose(
                                                                item,
                                                                'restore',
                                                            )
                                                        }
                                                    />
                                                    {canPurge && (
                                                        <IconAction
                                                            icon={Trash2}
                                                            label="Purger définitivement"
                                                            tone="danger"
                                                            onClick={() =>
                                                                choose(
                                                                    item,
                                                                    'purge',
                                                                )
                                                            }
                                                        />
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>
                <Pagination links={records.links} />
            </PageBody>
            <Dialog
                open={selection !== null}
                onOpenChange={(value) => {
                    if (!value && !busy) setSelection(null);
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <span
                            className={cn(
                                'mx-auto mb-1 flex size-10 items-center justify-center rounded-full sm:mx-0',
                                purge
                                    ? 'bg-danger-soft text-danger'
                                    : 'bg-accent text-primary',
                            )}
                            aria-hidden="true"
                        >
                            {purge ? (
                                <AlertTriangle className="size-5" />
                            ) : (
                                <RotateCcw className="size-5" />
                            )}
                        </span>
                        <DialogTitle>
                            {selection?.action === 'restore'
                                ? 'Restaurer cet élément'
                                : 'Purger définitivement'}
                        </DialogTitle>
                        <DialogDescription>
                            {selection?.action === 'restore'
                                ? 'Les dépendances et les conflits seront vérifiés. Un étudiant retrouve uniquement les situations annuelles déplacées avec lui.'
                                : 'Cette action est irréversible. Les données liées et les dossiers déjà traités restent protégés. La purge d’un import supprime le fichier et le staging, jamais les étudiants créés.'}
                        </DialogDescription>
                    </DialogHeader>
                    <p
                        className={cn(
                            'flex items-center gap-2 rounded-lg border p-3 text-sm font-medium break-words',
                            purge
                                ? 'border-danger/30 bg-danger-soft text-danger-foreground'
                                : 'bg-muted/40 text-foreground',
                        )}
                    >
                        <KindIcon
                            className="size-4 shrink-0"
                            aria-hidden="true"
                        />
                        {selection?.item.label}
                    </p>
                    <InputError message={error} />
                    <DialogFooter>
                        <Button
                            variant="outline"
                            disabled={busy}
                            onClick={() => setSelection(null)}
                        >
                            Annuler
                        </Button>
                        <Button
                            variant={purge ? 'destructive' : 'default'}
                            disabled={busy}
                            onClick={confirm}
                        >
                            {busy ? 'Traitement…' : 'Confirmer'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
Trash.layout = { breadcrumbs: [{ title: 'Corbeille', href: '/trash' }] };
