import { useForm } from '@inertiajs/react';
import { Mail } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';

import { ToneBadge } from '@/components/tone-badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';

/** What the server sends about a student for this email: field states, never values. */
export type StudentNotification = {
    has_email: boolean;
    fields: {
        value: string;
        label: string;
        state: 'missing' | 'doubtful' | null;
    }[];
    last: { at: string; by: string | null; count: number } | null;
};

const dateTime = (value: string) =>
    new Date(value).toLocaleString('fr-FR', {
        dateStyle: 'short',
        timeStyle: 'short',
    });

/**
 * « Prévenir l'étudiant » : header button and its dialog. Missing and doubtful fields come ticked;
 * the agent adds others or a message, then the student receives the list by email (ADR-088).
 */
export function NotifyStudentButton({
    studentUuid,
    notification,
    applicationUuid,
}: {
    studentUuid: string;
    notification: StudentNotification;
    /** Scholarship file the email is sent from, recorded with it. */
    applicationUuid?: string;
}) {
    const [open, setOpen] = useState(false);
    const suggested = notification.fields
        .filter((field) => field.state !== null)
        .map((field) => field.value);
    const form = useForm<{
        fields: string[];
        message: string;
        application: string;
    }>({ fields: suggested, message: '', application: applicationUuid ?? '' });
    const toggle = (value: string, checked: boolean) =>
        form.setData(
            'fields',
            checked
                ? [...form.data.fields, value]
                : form.data.fields.filter((item) => item !== value),
        );
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(`/students/${studentUuid}/notifications`, {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    };
    const empty = form.data.fields.length === 0 && !form.data.message.trim();
    const button = (
        <Button
            variant="outline"
            disabled={!notification.has_email}
            onClick={() => {
                form.reset();
                form.clearErrors();
                setOpen(true);
            }}
        >
            <Mail aria-hidden="true" />
            Prévenir l’étudiant
        </Button>
    );

    return (
        <>
            {notification.has_email ? (
                button
            ) : (
                <Tooltip>
                    <TooltipTrigger asChild>
                        {/* A disabled button gets no pointer events: the wrapper carries the tooltip. */}
                        <span tabIndex={0}>{button}</span>
                    </TooltipTrigger>
                    <TooltipContent>Aucun email sur la fiche</TooltipContent>
                </Tooltip>
            )}
            <Dialog
                open={open}
                onOpenChange={(next) => !form.processing && setOpen(next)}
            >
                <DialogContent className="sm:max-w-lg">
                    <form onSubmit={submit} className="space-y-5">
                        <DialogHeader>
                            <DialogTitle>Prévenir l’étudiant</DialogTitle>
                            <DialogDescription>
                                Il reçoit par email la liste des informations à
                                compléter ou à vérifier, et les corrige depuis
                                le portail étudiant.
                            </DialogDescription>
                        </DialogHeader>

                        <fieldset className="space-y-2">
                            <legend className="mb-2 text-sm font-medium">
                                Informations à signaler
                            </legend>
                            <div className="grid max-h-64 gap-1 overflow-y-auto rounded-lg border p-1 sm:grid-cols-2">
                                {notification.fields.map((field) => (
                                    <Label
                                        key={field.value}
                                        htmlFor={`notify-${field.value}`}
                                        className="flex min-h-10 cursor-pointer items-center gap-2.5 rounded-md px-2.5 py-2 font-normal hover:bg-muted"
                                    >
                                        <Checkbox
                                            id={`notify-${field.value}`}
                                            checked={form.data.fields.includes(
                                                field.value,
                                            )}
                                            onCheckedChange={(checked) =>
                                                toggle(
                                                    field.value,
                                                    checked === true,
                                                )
                                            }
                                        />
                                        <span className="min-w-0 flex-1 text-sm">
                                            {field.label}
                                        </span>
                                        {field.state && (
                                            <ToneBadge
                                                tone={
                                                    field.state === 'missing'
                                                        ? 'warning'
                                                        : 'info'
                                                }
                                                className="shrink-0"
                                            >
                                                {field.state === 'missing'
                                                    ? 'Vide'
                                                    : 'Douteux'}
                                            </ToneBadge>
                                        )}
                                    </Label>
                                ))}
                            </div>
                            {form.errors.fields && (
                                <p className="text-sm text-danger" role="alert">
                                    {form.errors.fields}
                                </p>
                            )}
                        </fieldset>

                        <div className="space-y-2">
                            <div className="flex items-baseline justify-between gap-2">
                                <Label htmlFor="notify-message">
                                    Message (facultatif)
                                </Label>
                                <span
                                    className="text-xs text-muted-foreground tabular-nums"
                                    aria-hidden="true"
                                >
                                    {form.data.message.length}/1000
                                </span>
                            </div>
                            <Textarea
                                id="notify-message"
                                className="min-h-20"
                                maxLength={1000}
                                placeholder="Par exemple : merci d’apporter votre CIN à la scolarité."
                                value={form.data.message}
                                onChange={(event) =>
                                    form.setData('message', event.target.value)
                                }
                            />
                            {form.errors.message && (
                                <p className="text-sm text-danger" role="alert">
                                    {form.errors.message}
                                </p>
                            )}
                        </div>

                        {notification.last && (
                            <p className="text-xs text-muted-foreground">
                                Dernier email le{' '}
                                {dateTime(notification.last.at)}
                                {notification.last.by &&
                                    ` par ${notification.last.by}`}
                                .
                            </p>
                        )}

                        <DialogFooter className="gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                disabled={form.processing}
                                onClick={() => setOpen(false)}
                            >
                                Annuler
                            </Button>
                            <Button disabled={form.processing || empty}>
                                {form.processing && <Spinner />}
                                Envoyer l’email
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}
