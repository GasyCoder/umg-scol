import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { CorrectionStatusBadge } from '@/components/corrections/status-badge';
import Heading from '@/components/heading';
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
import { Label } from '@/components/ui/label';

type Correction = {
    id: number;
    field_label: string;
    status: string;
    status_label: string;
    current_value: string | null;
    proposed_value: string;
    student_message: string | null;
    rejection_reason: string | null;
    created_at: string;
    reviewed_at: string | null;
    reviewer: string | null;
    conflict: boolean;
    student: {
        id: number;
        last_name: string;
        first_names: string | null;
        student_number: string | null;
        is_active: boolean;
        establishments: string[];
    };
};

const date = (value: string) => new Date(value).toLocaleString('fr-FR');

export default function CorrectionShow({
    correction,
    canReview,
    canViewStudent,
}: {
    correction: Correction;
    canReview: boolean;
    canViewStudent: boolean;
}) {
    const [decision, setDecision] = useState<'approve' | 'reject' | null>(null);
    const form = useForm({ decision: 'approve', rejection_reason: '' });
    const errors = form.errors as Record<string, string | undefined>;
    const rows: [string, string | null][] = [
        ['Information', correction.field_label],
        [
            'Valeur officielle actuelle',
            correction.current_value ?? 'Non renseignée',
        ],
        ['Valeur proposée', correction.proposed_value],
        [
            'Message de l’étudiant',
            correction.student_message ?? 'Aucun message',
        ],
        ['Demandée le', date(correction.created_at)],
    ];
    if (correction.reviewed_at)
        rows.push([
            'Décision',
            `${date(correction.reviewed_at)}${correction.reviewer ? ` par ${correction.reviewer}` : ''}`,
        ]);
    if (correction.rejection_reason)
        rows.push([
            'Motif du refus (visible par l’étudiant)',
            correction.rejection_reason,
        ]);

    return (
        <div className="max-w-3xl space-y-6 p-4 md:p-6">
            <Head title="Demande de correction" />
            <div className="flex flex-wrap items-start justify-between gap-3">
                <Heading
                    title={`${correction.student.last_name} ${correction.student.first_names ?? ''}`}
                    description={`${correction.student.student_number ?? 'Sans matricule'}${correction.student.establishments.length > 0 ? ` · ${correction.student.establishments.join(', ')}` : ''}`}
                />
                <CorrectionStatusBadge
                    status={correction.status}
                    label={correction.status_label}
                />
            </div>
            {correction.conflict && (
                <p
                    role="alert"
                    className="rounded-md border border-destructive p-3 text-destructive"
                >
                    La valeur officielle a changé depuis la demande.
                    L’approbation est bloquée : vérifiez la fiche et refusez la
                    demande si elle n’est plus pertinente.
                </p>
            )}
            {!correction.student.is_active && (
                <p role="alert" className="rounded-md border p-3">
                    La fiche étudiante est inactive.
                </p>
            )}
            <dl className="divide-y rounded-md border">
                {rows.map(([label, value]) => (
                    <div key={label} className="grid gap-1 p-4 sm:grid-cols-3">
                        <dt className="text-muted-foreground">{label}</dt>
                        <dd className="break-words whitespace-pre-line sm:col-span-2">
                            {value}
                        </dd>
                    </div>
                ))}
            </dl>
            <InputError message={errors.correction} />
            <div className="flex flex-wrap gap-3">
                {canReview && (
                    <>
                        <Button
                            disabled={correction.conflict}
                            onClick={() => {
                                form.clearErrors();
                                form.setData('decision', 'approve');
                                setDecision('approve');
                            }}
                        >
                            Approuver
                        </Button>
                        <Button
                            variant="outline"
                            onClick={() => {
                                form.clearErrors();
                                form.setData('decision', 'reject');
                                setDecision('reject');
                            }}
                        >
                            Refuser
                        </Button>
                    </>
                )}
                {canViewStudent && (
                    <Button variant="ghost" asChild>
                        <Link href={`/students/${correction.student.id}`}>
                            Voir la fiche
                        </Link>
                    </Button>
                )}
                <Button variant="ghost" asChild>
                    <Link href="/corrections">Retour à la liste</Link>
                </Button>
            </div>
            {!canReview && correction.status === 'pending' && (
                <p className="text-sm text-muted-foreground">
                    Consultation seule : la décision relève d’un compte autorisé
                    à modifier cette identité.
                </p>
            )}
            <Dialog
                open={decision !== null}
                onOpenChange={(open) => {
                    if (!open && !form.processing) setDecision(null);
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {decision === 'approve'
                                ? 'Approuver la correction'
                                : 'Refuser la correction'}
                        </DialogTitle>
                        <DialogDescription>
                            {decision === 'approve'
                                ? `La fiche officielle sera modifiée : ${correction.field_label} deviendra « ${correction.proposed_value} ». Seule cette information change.`
                                : 'Le motif sera visible par l’étudiant. N’y mettez aucune information interne ou personnelle d’un tiers.'}
                        </DialogDescription>
                    </DialogHeader>
                    {decision === 'reject' && (
                        <div className="space-y-2">
                            <Label htmlFor="reason">Motif du refus</Label>
                            <textarea
                                id="reason"
                                className="min-h-24 w-full rounded-md border bg-transparent p-2 text-sm"
                                minLength={10}
                                maxLength={500}
                                value={form.data.rejection_reason}
                                onChange={(event) =>
                                    form.setData(
                                        'rejection_reason',
                                        event.target.value,
                                    )
                                }
                            />
                            <InputError message={errors.rejection_reason} />
                        </div>
                    )}
                    <InputError message={errors.correction} />
                    <DialogFooter>
                        <Button
                            variant="outline"
                            disabled={form.processing}
                            onClick={() => setDecision(null)}
                        >
                            Annuler
                        </Button>
                        <Button
                            disabled={form.processing}
                            onClick={() =>
                                form.post(
                                    `/corrections/${correction.id}/review`,
                                    {
                                        preserveScroll: true,
                                        onSuccess: () => setDecision(null),
                                    },
                                )
                            }
                        >
                            {form.processing ? 'Traitement…' : 'Confirmer'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
CorrectionShow.layout = {
    breadcrumbs: [{ title: 'Corrections', href: '/corrections' }],
};
