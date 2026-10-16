import { router, usePage } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { useState } from 'react';
import { IconAction } from '@/components/icon-action';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
export function TrashButton({
    kind,
    uuid,
    label,
    redirect,
    iconOnly = false,
}: {
    kind: string;
    uuid: string;
    label: string;
    redirect?: string;
    /** Table rows: icon with tooltip, no text. */
    iconOnly?: boolean;
}) {
    const { auth } = usePage().props;
    const [open, setOpen] = useState(false);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');
    if (!auth.canManageTrash) return null;
    return (
        <>
            {iconOnly ? (
                <IconAction
                    icon={Trash2}
                    label="Supprimer"
                    tone="danger"
                    onClick={() => {
                        setError('');
                        setOpen(true);
                    }}
                />
            ) : (
                <Button
                    size="sm"
                    variant="outline"
                    className="border-danger/30 text-danger hover:bg-danger-soft hover:text-danger-foreground"
                    onClick={() => {
                        setError('');
                        setOpen(true);
                    }}
                >
                    <Trash2 className="size-4" aria-hidden="true" />
                    Supprimer
                </Button>
            )}
            <Dialog
                open={open}
                onOpenChange={(value) => {
                    if (!busy) setOpen(value);
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <span
                            className="mx-auto mb-1 flex size-10 items-center justify-center rounded-full bg-danger-soft text-danger sm:mx-0"
                            aria-hidden="true"
                        >
                            <Trash2 className="size-5" />
                        </span>
                        <DialogTitle>Déplacer dans la corbeille</DialogTitle>
                        <DialogDescription>
                            « {label} » disparaîtra des listes courantes et
                            pourra être restauré. Les éléments utilisés peuvent
                            être protégés. Pour un étudiant, ses situations
                            annuelles visibles sont déplacées avec lui.
                        </DialogDescription>
                    </DialogHeader>
                    <InputError message={error} />
                    <DialogFooter>
                        <Button
                            variant="outline"
                            disabled={busy}
                            onClick={() => setOpen(false)}
                        >
                            Annuler
                        </Button>
                        <Button
                            variant="destructive"
                            disabled={busy}
                            onClick={() => {
                                setBusy(true);
                                router.delete(`/trash/${kind}/${uuid}`, {
                                    preserveScroll: true,
                                    onError: (errors) =>
                                        setError(
                                            errors.trash ??
                                                Object.values(errors)[0] ??
                                                'Suppression refusée.',
                                        ),
                                    onSuccess: () => {
                                        setOpen(false);
                                        if (redirect) router.visit(redirect);
                                    },
                                    onFinish: () => setBusy(false),
                                });
                            }}
                        >
                            {busy
                                ? 'Traitement…'
                                : 'Déplacer dans la corbeille'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
