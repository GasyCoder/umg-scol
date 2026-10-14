import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Pagination, formatDate } from '@/components/scholarships/shared';
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
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
type Item = {
    uuid: string;
    label: string;
    deleted_at: string;
    deleted_by: number | null;
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
    records: { data: Item[]; links: PageLink[] };
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
    return (
        <div className="space-y-6 p-4 md:p-6">
            <Head title="Corbeille" />
            <Heading
                title="Administration / Corbeille"
                description="Les suppressions sont réversibles. La purge est définitive et reste soumise aux protections de l’historique."
            />
            {pendingFileCleanupCount > 0 && (
                <p role="status" className="rounded-md border p-3">
                    {pendingFileCleanupCount} suppression(s) de fichiers sont en
                    attente de reprise automatique. Les données ont été purgées
                    ; le nettoyage du stockage sera réessayé.
                </p>
            )}
            <form onSubmit={submit} className="flex flex-wrap gap-3">
                <Select
                    value={filter.data.kind}
                    onValueChange={(value) => filter.setData('kind', value)}
                >
                    <SelectTrigger className="w-64" aria-label="Type d’élément">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        {Object.entries(kinds).map(([value, label]) => (
                            <SelectItem key={value} value={value}>
                                {label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <Input
                    className="max-w-sm"
                    aria-label="Rechercher"
                    placeholder="Rechercher…"
                    value={filter.data.search}
                    onChange={(event) =>
                        filter.setData('search', event.target.value)
                    }
                />
                <Button disabled={filter.processing}>Filtrer</Button>
            </form>
            <section className="rounded-lg border bg-card">
                {records.data.length === 0 ? (
                    <p className="p-6 text-muted-foreground">
                        La corbeille est vide pour cette catégorie.
                    </p>
                ) : (
                    records.data.map((item) => (
                        <article
                            key={item.uuid}
                            className="flex flex-wrap items-center justify-between gap-3 border-b p-4 last:border-0"
                        >
                            <div>
                                <p className="font-medium">{item.label}</p>
                                <p className="text-sm text-muted-foreground">
                                    Supprimé le {formatDate(item.deleted_at)} ·
                                    compte #{item.deleted_by ?? 'système'}
                                </p>
                            </div>
                            <div className="flex gap-2">
                                <Button
                                    variant="outline"
                                    onClick={() => {
                                        setError('');
                                        setSelection({
                                            item,
                                            action: 'restore',
                                        });
                                    }}
                                >
                                    Restaurer
                                </Button>
                                {canPurge && (
                                    <Button
                                        variant="outline"
                                        className="text-destructive"
                                        onClick={() => {
                                            setError('');
                                            setSelection({
                                                item,
                                                action: 'purge',
                                            });
                                        }}
                                    >
                                        Purger définitivement
                                    </Button>
                                )}
                            </div>
                        </article>
                    ))
                )}
            </section>
            <Pagination links={records.links} />
            <Dialog
                open={selection !== null}
                onOpenChange={(value) => {
                    if (!value && !busy) setSelection(null);
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {selection?.action === 'restore'
                                ? 'Restaurer cet élément'
                                : 'Purger définitivement'}
                        </DialogTitle>
                        <DialogDescription>
                            {selection?.action === 'restore'
                                ? 'Les dépendances et les conflits seront vérifiés. Un étudiant retrouve uniquement les déclarations déplacées avec lui.'
                                : 'Cette action est irréversible. Les données liées et les dossiers déjà traités restent protégés. La purge d’un import supprime le fichier et le staging, jamais les étudiants créés.'}
                        </DialogDescription>
                    </DialogHeader>
                    <p>{selection?.item.label}</p>
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
                            variant={
                                selection?.action === 'purge'
                                    ? 'destructive'
                                    : 'default'
                            }
                            disabled={busy}
                            onClick={confirm}
                        >
                            {busy ? 'Traitement…' : 'Confirmer'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
Trash.layout = { breadcrumbs: [{ title: 'Corbeille', href: '/trash' }] };
