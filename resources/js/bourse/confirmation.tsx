import { AlertCircle, BadgeCheck } from 'lucide-react';
import { useState } from 'react';
import { ToneBadge } from '@/components/tone-badge';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Card, CardDescription, CardHeader } from '@/components/ui/card';
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
import { SectionTitle } from './section-title';

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
    compact = false,
}: {
    state: ConfirmationState;
    onDone: (message: string) => Promise<void>;
    /** Narrow layout of the right sidebar. */
    compact?: boolean;
}) {
    const [open, setOpen] = useState(false);
    const [busy, setBusy] = useState(false);
    const submit = () => {
        if (!state.version) return;
        setBusy(true);
        api<{ message: string }>('confirmation', { version: state.version })
            .then(async (result) => {
                setOpen(false);
                await onDone(result.message);
            })
            .catch(async (reason: unknown) => {
                setOpen(false);
                toast.error(
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
        <Card
            id="verification"
            aria-labelledby="verification-title"
            className="scroll-mt-24 gap-0 overflow-hidden py-0"
        >
            <CardHeader className="flex-row items-start justify-between gap-3 border-b px-5 py-4 sm:px-6">
                <div className="min-w-0 space-y-1">
                    <SectionTitle icon={BadgeCheck} id="verification-title">
                        Vérification
                    </SectionTitle>
                    {state.academic_year && (
                        <CardDescription>{state.academic_year}</CardDescription>
                    )}
                </div>
                <ConfirmationBadge status={state.status} />
            </CardHeader>

            <div
                className={
                    compact
                        ? 'space-y-4 px-5 py-5'
                        : 'space-y-4 px-5 py-5 sm:px-6'
                }
            >
                {/* One short sentence per state, then the button when it can be used. */}
                {confirmed ? (
                    <p className="text-sm text-foreground">
                        Confirmée
                        {state.confirmed_at &&
                            ` le ${formatDateTime(state.confirmed_at)}`}
                        .
                    </p>
                ) : pending ? (
                    <p className="text-sm text-muted-foreground">
                        Une demande est en attente.{' '}
                        <a
                            href="/demandes"
                            className="rounded-sm font-medium text-foreground underline underline-offset-4 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        >
                            Voir
                        </a>
                    </p>
                ) : state.can_confirm ? (
                    <p className="flex items-start gap-2 text-sm text-foreground">
                        {state.status === 'stale' && (
                            <AlertCircle
                                className="mt-0.5 size-4 shrink-0 text-warning"
                                aria-hidden="true"
                            />
                        )}
                        {state.status === 'stale'
                            ? 'Votre fiche a changé. Est-elle toujours exacte ?'
                            : 'Votre fiche est-elle exacte ?'}
                    </p>
                ) : (
                    state.message && (
                        <p className="text-sm text-muted-foreground">
                            {state.message}
                        </p>
                    )
                )}

                {!confirmed && !pending && state.can_confirm && (
                    <Button
                        className={
                            compact
                                ? 'h-11 w-full text-base'
                                : 'h-11 w-full text-base sm:w-auto'
                        }
                        disabled={!state.version || busy}
                        onClick={() => setOpen(true)}
                    >
                        Oui, je confirme
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
                        <DialogTitle>Confirmer mes informations</DialogTitle>
                        <DialogDescription>
                            Je confirme que les informations affichées sont
                            exactes.
                        </DialogDescription>
                    </DialogHeader>
                    {/* The fine print lives here, at the moment of deciding, not on the card. */}
                    <p className="text-sm text-muted-foreground">
                        Une erreur ? Annulez et touchez le crayon à côté de
                        l’information. Cette confirmation ne vaut ni inscription
                        ni dossier de bourse.
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
        </Card>
    );
}
