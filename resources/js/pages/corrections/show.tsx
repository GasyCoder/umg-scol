import { Head, Link, useForm } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowDown,
    ArrowLeft,
    ArrowRight,
    Check,
    CircleUserRound,
    Clock,
    FilePenLine,
    Gavel,
    Info,
    MessageSquareQuote,
    X,
} from 'lucide-react';
import { useState } from 'react';
import { CorrectionStatusBadge } from '@/components/corrections/status-badge';
import InputError from '@/components/input-error';
import { PageBody, PageHeader } from '@/components/page-header';
import { Panel } from '@/components/reporting/shared';
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
    uuid: string;
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
        uuid: string;
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
    const open = (value: 'approve' | 'reject') => {
        form.clearErrors();
        form.setData('decision', value);
        setDecision(value);
    };
    const timeline: { label: string; value: string; icon: typeof Clock }[] = [
        {
            label: 'Demandée le',
            value: date(correction.created_at),
            icon: Clock,
        },
    ];
    if (correction.reviewed_at)
        timeline.push({
            label: 'Décision',
            value: `${date(correction.reviewed_at)}${correction.reviewer ? ` par ${correction.reviewer}` : ''}`,
            icon: Gavel,
        });

    return (
        <>
            <Head title="Demande de correction" />
            <PageBody>
                <PageHeader
                    icon={FilePenLine}
                    title={`${correction.student.last_name} ${correction.student.first_names ?? ''}`}
                    description={`${correction.student.student_number ?? 'Sans matricule'}${correction.student.establishments.length > 0 ? ` · ${correction.student.establishments.join(', ')}` : ''}`}
                    meta={
                        <div className="flex flex-wrap items-center gap-2">
                            <CorrectionStatusBadge
                                status={correction.status}
                                label={correction.status_label}
                            />
                            <span className="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-medium text-muted-foreground">
                                {correction.field_label}
                            </span>
                        </div>
                    }
                    actions={
                        <>
                            {canViewStudent && (
                                <Button variant="outline" asChild>
                                    <Link
                                        href={`/students/${correction.student.uuid}`}
                                    >
                                        <CircleUserRound aria-hidden="true" />
                                        Voir la fiche
                                    </Link>
                                </Button>
                            )}
                            <Button variant="ghost" asChild>
                                <Link href="/corrections">
                                    <ArrowLeft aria-hidden="true" />
                                    Retour à la liste
                                </Link>
                            </Button>
                        </>
                    }
                />

                {correction.conflict && (
                    <p
                        role="alert"
                        className="flex items-start gap-3 rounded-xl border border-danger/30 bg-danger-soft p-4 text-sm text-danger-foreground"
                    >
                        <AlertTriangle
                            className="mt-0.5 size-5 shrink-0 text-danger"
                            aria-hidden="true"
                        />
                        <span>
                            <strong className="block font-semibold">
                                Conflit avec la fiche officielle
                            </strong>
                            La valeur officielle a changé depuis la demande.
                            L’approbation est bloquée : vérifiez la fiche et
                            refusez la demande si elle n’est plus pertinente.
                        </span>
                    </p>
                )}
                {!correction.student.is_active && (
                    <p
                        role="alert"
                        className="flex items-start gap-3 rounded-xl border border-warning/30 bg-warning-soft p-4 text-sm text-warning-foreground"
                    >
                        <AlertTriangle
                            className="mt-0.5 size-5 shrink-0 text-warning"
                            aria-hidden="true"
                        />
                        La fiche étudiante est inactive.
                    </p>
                )}

                <div className="grid gap-6 lg:grid-cols-3">
                    <div className="space-y-6 lg:col-span-2">
                        <Panel
                            icon={FilePenLine}
                            title={correction.field_label}
                            description="Information concernée par la demande"
                        >
                            <div className="grid items-stretch gap-3 md:grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)]">
                                <div className="rounded-xl border bg-muted/50 p-4">
                                    <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                        Valeur officielle actuelle
                                    </p>
                                    <p
                                        className={`mt-2 text-lg font-semibold break-words whitespace-pre-line text-muted-foreground ${correction.current_value === null ? 'italic' : correction.status === 'pending' ? 'line-through decoration-danger/60 decoration-2' : ''}`}
                                    >
                                        {correction.current_value ??
                                            'Non renseignée'}
                                    </p>
                                </div>
                                <div
                                    className="flex items-center justify-center"
                                    aria-hidden="true"
                                >
                                    <span className="flex size-9 items-center justify-center rounded-full border bg-card text-primary shadow-card">
                                        <ArrowRight className="hidden size-4 md:block" />
                                        <ArrowDown className="size-4 md:hidden" />
                                    </span>
                                </div>
                                <div className="rounded-xl border border-primary/40 bg-accent p-4 ring-1 ring-primary/15">
                                    <p className="text-xs font-semibold tracking-wide text-primary uppercase">
                                        Valeur proposée
                                    </p>
                                    <p className="mt-2 text-lg font-bold break-words whitespace-pre-line text-foreground">
                                        {correction.proposed_value}
                                    </p>
                                </div>
                            </div>
                        </Panel>

                        <Panel
                            icon={MessageSquareQuote}
                            title="Message de l’étudiant"
                        >
                            {correction.student_message ? (
                                <blockquote className="rounded-lg border-l-4 border-primary/40 bg-muted/40 px-4 py-3 text-sm break-words whitespace-pre-line text-foreground">
                                    {correction.student_message}
                                </blockquote>
                            ) : (
                                <p className="text-sm text-muted-foreground italic">
                                    Aucun message
                                </p>
                            )}
                        </Panel>
                    </div>

                    <div className="space-y-6">
                        <section
                            aria-labelledby="review-title"
                            className="surface overflow-hidden"
                        >
                            <div className="border-b bg-muted/40 px-5 py-4">
                                <h2
                                    id="review-title"
                                    className="flex items-center gap-2 text-base font-bold text-foreground"
                                >
                                    <Gavel
                                        className="size-4 text-primary"
                                        aria-hidden="true"
                                    />
                                    Examen de la demande
                                </h2>
                            </div>
                            <div className="space-y-4 p-5">
                                {canReview ? (
                                    <>
                                        <p className="text-sm text-muted-foreground">
                                            L’approbation modifie uniquement
                                            cette information sur la fiche
                                            officielle. Un refus doit être
                                            motivé.
                                        </p>
                                        <div className="grid gap-2">
                                            <Button
                                                disabled={correction.conflict}
                                                onClick={() => open('approve')}
                                            >
                                                <Check aria-hidden="true" />
                                                Approuver
                                            </Button>
                                            <Button
                                                variant="outline"
                                                className="border-danger/30 text-danger hover:bg-danger-soft hover:text-danger-foreground"
                                                onClick={() => open('reject')}
                                            >
                                                <X aria-hidden="true" />
                                                Refuser
                                            </Button>
                                        </div>
                                        {correction.conflict && (
                                            <p className="text-xs text-muted-foreground">
                                                Approbation indisponible tant
                                                que le conflit persiste.
                                            </p>
                                        )}
                                    </>
                                ) : correction.status === 'pending' ? (
                                    <p className="flex items-start gap-2 text-sm text-muted-foreground">
                                        <Info
                                            className="mt-0.5 size-4 shrink-0"
                                            aria-hidden="true"
                                        />
                                        Consultation seule : la décision relève
                                        d’un compte autorisé à modifier cette
                                        identité.
                                    </p>
                                ) : (
                                    <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                        Demande traitée :
                                        <CorrectionStatusBadge
                                            status={correction.status}
                                            label={correction.status_label}
                                        />
                                    </div>
                                )}
                                <InputError message={errors.correction} />
                            </div>
                        </section>

                        <Panel icon={Clock} title="Suivi">
                            <ol className="relative space-y-4 text-sm before:absolute before:top-2 before:bottom-2 before:left-[7px] before:w-px before:bg-border">
                                {timeline.map((step) => (
                                    <li
                                        key={step.label}
                                        className="relative pl-7"
                                    >
                                        <span
                                            className="absolute top-1 left-0 size-[15px] rounded-full border-[3px] border-card bg-primary ring-1 ring-primary/30"
                                            aria-hidden="true"
                                        />
                                        <p className="text-xs text-muted-foreground">
                                            {step.label}
                                        </p>
                                        <p className="font-medium break-words text-foreground">
                                            {step.value}
                                        </p>
                                    </li>
                                ))}
                            </ol>
                            {correction.rejection_reason && (
                                <div className="mt-4 rounded-lg border border-danger/30 bg-danger-soft p-3 text-sm text-danger-foreground">
                                    <p className="text-xs font-semibold">
                                        Motif du refus (visible par l’étudiant)
                                    </p>
                                    <p className="mt-1 break-words whitespace-pre-line">
                                        {correction.rejection_reason}
                                    </p>
                                </div>
                            )}
                        </Panel>
                    </div>
                </div>
            </PageBody>
            <Dialog
                open={decision !== null}
                onOpenChange={(value) => {
                    if (!value && !form.processing) setDecision(null);
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
                                className="min-h-24 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
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
                            <p className="text-right text-xs text-muted-foreground tabular-nums">
                                {form.data.rejection_reason.length} / 500
                            </p>
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
                            variant={
                                decision === 'reject'
                                    ? 'destructive'
                                    : 'default'
                            }
                            disabled={form.processing}
                            onClick={() =>
                                form.post(
                                    `/corrections/${correction.uuid}/review`,
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
        </>
    );
}
CorrectionShow.layout = {
    breadcrumbs: [{ title: 'Corrections', href: '/corrections' }],
};
