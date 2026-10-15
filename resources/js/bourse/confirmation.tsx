import { AlertCircle, BadgeCheck, CalendarCheck, Info } from 'lucide-react';
import { useState } from 'react';
import { ToneBadge } from '@/components/tone-badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
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

/** Status pill shared by the hero card and the confirmation section. */
export function ConfirmationBadge({
    status,
}: {
    status: ConfirmationState['status'];
}) {
    if (status === 'confirmed') {
        return <ToneBadge tone="success">Confirmées</ToneBadge>;
    }

    if (status === 'stale') {
        return <ToneBadge tone="warning">À confirmer de nouveau</ToneBadge>;
    }

    return <ToneBadge tone="info">À vérifier</ToneBadge>;
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
    const confirmed = state.status === 'confirmed';

    return (
        <section
            id="verification"
            aria-labelledby="verification-title"
            className="surface scroll-mt-24 overflow-hidden"
        >
            <header className="flex flex-wrap items-start gap-x-4 gap-y-3 border-b bg-muted/30 px-5 py-4 sm:px-6">
                <span
                    className={
                        confirmed
                            ? 'flex size-10 shrink-0 items-center justify-center rounded-lg bg-success-soft text-success-foreground'
                            : 'flex size-10 shrink-0 items-center justify-center rounded-lg bg-accent text-primary'
                    }
                    aria-hidden="true"
                >
                    <BadgeCheck className="size-5" />
                </span>
                <div className="min-w-0 flex-1">
                    <h2
                        id="verification-title"
                        className="font-heading text-base font-bold text-foreground"
                    >
                        Vérification de mes informations
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        Confirmez que les informations affichées sont exactes.
                    </p>
                </div>
                <div className="order-last basis-full pl-14 sm:order-none sm:basis-auto sm:pl-0">
                    <ConfirmationBadge status={state.status} />
                </div>
            </header>

            <div className="space-y-4 px-5 py-5 sm:px-6">
                {confirmed && state.confirmed_at && (
                    <p className="flex items-start gap-3 rounded-lg border border-success/30 bg-success-soft px-4 py-3 text-sm font-medium text-success-foreground">
                        <CalendarCheck
                            className="mt-0.5 size-4 shrink-0"
                            aria-hidden="true"
                        />
                        <span>
                            Informations confirmées le{' '}
                            {formatDateTime(state.confirmed_at)}
                            {state.academic_year &&
                                ` pour l’année ${state.academic_year}`}
                            .
                        </span>
                    </p>
                )}
                {state.status === 'stale' && (
                    <p className="flex items-start gap-3 rounded-lg border border-warning/30 bg-warning-soft px-4 py-3 text-sm text-warning-foreground">
                        <AlertCircle
                            className="mt-0.5 size-4 shrink-0"
                            aria-hidden="true"
                        />
                        <span>
                            Vos informations ont changé depuis votre dernière
                            confirmation
                            {state.confirmed_at &&
                                ` (le ${formatDateTime(state.confirmed_at)})`}
                            . Vérifiez-les à nouveau.
                        </span>
                    </p>
                )}
                {state.status === 'never_confirmed' && (
                    <p className="text-sm text-foreground">
                        Après vérification des informations ci-dessus, confirmez
                        qu’elles sont correctes.
                    </p>
                )}

                <p className="flex items-start gap-3 rounded-lg bg-muted px-4 py-3 text-xs leading-relaxed text-muted-foreground sm:text-sm">
                    <Info
                        className="mt-0.5 size-4 shrink-0"
                        aria-hidden="true"
                    />
                    <span>
                        Cette confirmation indique seulement que vous avez
                        vérifié les informations affichées. Elle ne constitue ni
                        une inscription, ni une validation de dossier de bourse.
                        Les données CIN, non affichées, ne sont pas concernées.
                    </span>
                </p>

                {pending ? (
                    <p className="flex items-start gap-3 rounded-lg border border-info/30 bg-info-soft px-4 py-3 text-sm text-info-foreground">
                        <Info
                            className="mt-0.5 size-4 shrink-0"
                            aria-hidden="true"
                        />
                        <span>
                            Vous avez une demande de correction en attente. Vous
                            pourrez confirmer vos informations après son
                            traitement ou son annulation.{' '}
                            <a
                                href="#mes-demandes"
                                className="rounded-sm font-semibold underline underline-offset-4 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            >
                                Voir mes demandes
                            </a>
                        </span>
                    </p>
                ) : (
                    state.message && (
                        <p className="text-sm text-muted-foreground">
                            {state.message}
                        </p>
                    )
                )}
                {error && (
                    <p
                        role="alert"
                        className="flex items-start gap-3 rounded-lg border border-danger/30 bg-danger-soft px-4 py-3 text-sm font-medium text-danger-foreground"
                    >
                        <AlertCircle
                            className="mt-0.5 size-4 shrink-0"
                            aria-hidden="true"
                        />
                        <span>{error}</span>
                    </p>
                )}
                {!confirmed && (
                    <Button
                        className="h-auto min-h-11 w-full py-2.5 font-semibold whitespace-normal sm:w-auto"
                        disabled={!state.can_confirm || !state.version || busy}
                        onClick={() => {
                            setError('');
                            setOpen(true);
                        }}
                    >
                        <BadgeCheck aria-hidden="true" />
                        Je confirme que mes informations sont correctes
                    </Button>
                )}
            </div>

            <Dialog
                open={open}
                onOpenChange={(next) => {
                    if (!busy) setOpen(next);
                }}
            >
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <span
                            className="mx-auto mb-2 flex size-11 items-center justify-center rounded-xl bg-accent text-primary sm:mx-0"
                            aria-hidden="true"
                        >
                            <BadgeCheck className="size-5" />
                        </span>
                        <DialogTitle>Confirmer mes informations</DialogTitle>
                        <DialogDescription>
                            Je confirme avoir vérifié les informations
                            affichées.
                        </DialogDescription>
                    </DialogHeader>
                    <p className="rounded-lg bg-muted px-4 py-3 text-sm text-muted-foreground">
                        Si une information est inexacte, annulez et utilisez
                        plutôt « Signaler une erreur ».
                    </p>
                    <DialogFooter className="gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            disabled={busy}
                            onClick={() => setOpen(false)}
                        >
                            Annuler
                        </Button>
                        <Button disabled={busy} onClick={submit}>
                            {busy && <Spinner />}
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
