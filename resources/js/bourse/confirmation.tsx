import { useState } from 'react';
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
import { api } from './api';

export type ConfirmationState = {
    status: 'never_confirmed' | 'confirmed' | 'stale';
    confirmed_at: string | null;
    academic_year: string | null;
    can_confirm: boolean;
    reason: string | null;
    message: string | null;
    version: string | null;
};

function formatDateTime(value: string): string {
    const date = new Date(value);

    return `${date.toLocaleDateString('fr-FR')} à ${date.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })}`;
}

export function ConfirmationSection({
    state,
    onDone,
}: {
    state: ConfirmationState;
    onDone: (message: string) => Promise<void>;
}) {
    const [open, setOpen] = useState(false);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');
    const submit = () => {
        if (!state.version) return;
        setBusy(true);
        setError('');
        api<{ message: string }>('confirmation', { version: state.version })
            .then(async (result) => {
                setOpen(false);
                await onDone(result.message);
            })
            .catch(async (reason: unknown) => {
                setOpen(false);
                setError(
                    reason instanceof Error
                        ? reason.message
                        : 'La confirmation n’a pas pu être enregistrée.',
                );
                // The displayed information may have changed: reload it before any new attempt.
                await onDone('').catch(() => undefined);
            })
            .finally(() => setBusy(false));
    };
    const pending = state.reason === 'pending_corrections';

    return (
        <section className="space-y-3 rounded-lg border p-5">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h2 className="text-lg font-semibold">
                    Vérification de mes informations
                </h2>
                {state.status === 'confirmed' && (
                    <Badge variant="secondary">Confirmées</Badge>
                )}
                {state.status === 'stale' && (
                    <Badge variant="outline">À confirmer de nouveau</Badge>
                )}
            </div>
            {state.status === 'confirmed' && state.confirmed_at && (
                <p>
                    Informations confirmées le{' '}
                    {formatDateTime(state.confirmed_at)}
                    {state.academic_year &&
                        ` pour l’année ${state.academic_year}`}
                    .
                </p>
            )}
            {state.status === 'stale' && (
                <p>
                    Vos informations ont changé depuis votre dernière
                    confirmation
                    {state.confirmed_at &&
                        ` (le ${formatDateTime(state.confirmed_at)})`}
                    . Vérifiez-les à nouveau.
                </p>
            )}
            {state.status === 'never_confirmed' && (
                <p>
                    Après vérification des informations ci-dessus, confirmez
                    qu’elles sont correctes.
                </p>
            )}
            <p className="text-sm text-muted-foreground">
                Cette confirmation indique seulement que vous avez vérifié les
                informations affichées. Elle ne constitue ni une inscription, ni
                une validation de dossier de bourse. Les données CIN, non
                affichées, ne sont pas concernées.
            </p>
            {pending ? (
                <p className="text-sm">
                    Vous avez une demande de correction en attente. Vous pourrez
                    confirmer vos informations après son traitement ou son
                    annulation.{' '}
                    <a
                        href="#mes-demandes"
                        className="underline underline-offset-4"
                    >
                        Voir mes demandes
                    </a>
                </p>
            ) : (
                state.message && <p className="text-sm">{state.message}</p>
            )}
            {error && (
                <p role="alert" className="text-sm text-destructive">
                    {error}
                </p>
            )}
            {state.status !== 'confirmed' && (
                <Button
                    disabled={!state.can_confirm || !state.version || busy}
                    onClick={() => {
                        setError('');
                        setOpen(true);
                    }}
                >
                    Je confirme que mes informations sont correctes
                </Button>
            )}
            <Dialog
                open={open}
                onOpenChange={(next) => {
                    if (!busy) setOpen(next);
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Confirmer mes informations</DialogTitle>
                        <DialogDescription>
                            Je confirme avoir vérifié les informations
                            affichées.
                        </DialogDescription>
                    </DialogHeader>
                    <p className="text-sm">
                        Si une information est inexacte, annulez et utilisez
                        plutôt « Signaler une erreur ».
                    </p>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            disabled={busy}
                            onClick={() => setOpen(false)}
                        >
                            Annuler
                        </Button>
                        <Button disabled={busy} onClick={submit}>
                            {busy
                                ? 'Enregistrement…'
                                : 'Confirmer mes informations'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </section>
    );
}
