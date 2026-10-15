import { KeyRound, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import type { Passkey } from '@/types/auth';

type Props = {
    passkey: Passkey;
    onDelete: (id: number, onError: () => void) => void;
};

export default function PasskeyItem({ passkey, onDelete }: Props) {
    const [isDeleting, setIsDeleting] = useState(false);

    const handleDelete = () => {
        setIsDeleting(true);
        onDelete(passkey.id, () => setIsDeleting(false));
    };

    return (
        <div className="flex items-center justify-between gap-3 border-b p-4 transition-colors last:border-b-0 hover:bg-muted/40">
            <div className="flex min-w-0 items-center gap-4">
                <div
                    className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-accent text-primary"
                    aria-hidden="true"
                >
                    <KeyRound className="size-5" />
                </div>
                <div className="min-w-0 space-y-1">
                    <div className="flex flex-wrap items-center gap-2">
                        <p className="truncate font-semibold tracking-tight text-foreground">
                            {passkey.name}
                        </p>
                        {passkey.authenticator && (
                            <span className="inline-flex items-center rounded-full bg-secondary px-2 py-0.5 text-[11px] font-semibold tracking-wide text-muted-foreground uppercase">
                                {passkey.authenticator}
                            </span>
                        )}
                    </div>
                    <p className="text-xs text-muted-foreground sm:text-sm">
                        Ajoutée {passkey.created_at_diff}
                        {passkey.last_used_at_diff && (
                            <>
                                <span className="mx-1 text-muted-foreground/50">
                                    /
                                </span>
                                Dernière utilisation {passkey.last_used_at_diff}
                            </>
                        )}
                    </p>
                </div>
            </div>

            <Dialog>
                <DialogTrigger asChild>
                    <Button
                        variant="ghost"
                        size="icon"
                        className="size-9 shrink-0 text-muted-foreground hover:bg-danger-soft hover:text-danger-foreground"
                    >
                        <Trash2 className="size-4" aria-hidden="true" />
                        <span className="sr-only">
                            Supprimer la clé d'accès {passkey.name}
                        </span>
                    </Button>
                </DialogTrigger>
                <DialogContent>
                    <DialogTitle>Supprimer la clé d'accès</DialogTitle>
                    <DialogDescription>
                        Voulez-vous vraiment supprimer la clé d'accès «{' '}
                        {passkey.name} » ? Vous ne pourrez plus l'utiliser pour
                        vous connecter.
                    </DialogDescription>
                    <DialogFooter className="gap-2">
                        <DialogClose asChild>
                            <Button variant="secondary">Annuler</Button>
                        </DialogClose>
                        <Button
                            variant="destructive"
                            onClick={handleDelete}
                            disabled={isDeleting}
                        >
                            {isDeleting
                                ? 'Suppression...'
                                : "Supprimer la clé d'accès"}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
