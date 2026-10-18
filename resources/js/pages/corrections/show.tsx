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
    Layers,
    MessageSquareQuote,
    X,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useState } from 'react';
import { CorrectionStatusBadge } from '@/components/corrections/status-badge';
import InputError from '@/components/input-error';
import { PageBody, PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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
import { cn } from '@/lib/utils';

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
    /** Saved by the student without validation (sex, nationality, address). */
    direct: boolean;
    student: {
        uuid: string;
        last_name: string;
        first_names: string | null;
        birth_date: string | null;
        is_active: boolean;
        establishments: string[];
    };
};

const date = (value: string) =>
    new Date(value).toLocaleString('fr-FR', {
        dateStyle: 'long',
        timeStyle: 'short',
    });

function CardHeading({
    icon: Icon,
    children,
}: {
    icon: LucideIcon;
    children: string;
}) {
    return (
        <CardTitle className="flex items-center gap-2 text-base">
            <Icon className="size-4 text-primary" aria-hidden="true" />
            {children}
        </CardTitle>
    );
}

/** One correction request: official value against the proposal, the decision and its follow-up. */
export default function CorrectionShow({
    correction,
    canReview,
    canViewStudent,
    otherPending,
}: {
    correction: Correction;
    canReview: boolean;
    canViewStudent: boolean;
    /** Other pending requests of the same student. */
    otherPending: number;
}) {
    const [decision, setDecision] = useState<'approve' | 'reject' | null>(null);
    const form = useForm({ decision: 'approve', rejection_reason: '' });
    const errors = form.errors as Record<string, string | undefined>;
    const open = (value: 'approve' | 'reject') => {
        form.reset();
        form.clearErrors();
        form.setData((data) => ({ ...data, decision: value }));
        setDecision(value);
    };
    const student = correction.student;
    const name = `${student.last_name} ${student.first_names ?? ''}`.trim();
    const steps: { label: string; value: string }[] = [
        { label: 'Demandée', value: date(correction.created_at) },
        ...(correction.reviewed_at && !correction.direct
            ? [
                  {
                      label: correction.status_label,
                      value: `${date(correction.reviewed_at)}${correction.reviewer ? ` · ${correction.reviewer}` : ''}`,
                  },
              ]
            : []),
    ];

    return (
        <>
            <Head title={`Correction · ${name}`} />
            <PageBody>
                <PageHeader
                    icon={FilePenLine}
                    title={name}
                    description={[
                        student.birth_date
                            ? `Né(e) le ${new Date(student.birth_date).toLocaleDateString('fr-FR')}`
                            : 'Date de naissance non renseignée',
                        student.establishments.join(', '),
                    ]
                        .filter(Boolean)
                        .join(' · ')}
                    meta={
                        <div className="flex flex-wrap items-center gap-2">
                            <CorrectionStatusBadge
                                status={correction.status}
                                label={
                                    correction.direct
                                        ? 'Enregistrée par l’étudiant'
                                        : correction.status_label
                                }
                            />
                            <span className="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-medium text-muted-foreground">
                                {correction.field_label}
                            </span>
                        </div>
                    }
                    actions={
                        <>
                            <Button variant="ghost" asChild>
                                <Link href="/corrections">
                                    <ArrowLeft aria-hidden="true" />
                                    Liste
                                </Link>
                            </Button>
                            <Button variant="outline" asChild>
                                <Link
                                    href={`/corrections/students/${student.uuid}`}
                                >
                                    <Layers aria-hidden="true" />
                                    Toutes ses demandes
                                    {otherPending > 0 && (
                                        <span className="rounded-full bg-warning-soft px-1.5 text-xs font-semibold text-warning-foreground tabular-nums">
                                            +{otherPending}
                                        </span>
                                    )}
                                </Link>
                            </Button>
                            {canViewStudent && (
                                <Button variant="outline" asChild>
                                    <Link href={`/students/${student.uuid}`}>
                                        <CircleUserRound aria-hidden="true" />
                                        Fiche
                                    </Link>
                                </Button>
                            )}
                        </>
                    }
                />

                {(correction.conflict || !student.is_active) && (
                    <div className="space-y-2">
                        {correction.conflict && (
                            <p
                                role="alert"
                                className="flex items-start gap-3 rounded-lg border border-danger/30 bg-danger-soft px-4 py-3 text-sm text-danger-foreground"
                            >
                                <AlertTriangle
                                    className="mt-0.5 size-4 shrink-0"
                                    aria-hidden="true"
                                />
                                La fiche a changé depuis la demande :
                                l’approbation est bloquée. Refusez la demande si
                                elle n’est plus utile.
                            </p>
                        )}
                        {!student.is_active && (
                            <p
                                role="alert"
                                className="flex items-start gap-3 rounded-lg border border-warning/30 bg-warning-soft px-4 py-3 text-sm text-warning-foreground"
                            >
                                <AlertTriangle
                                    className="mt-0.5 size-4 shrink-0"
                                    aria-hidden="true"
                                />
                                La fiche étudiante est inactive.
                            </p>
                        )}
                    </div>
                )}

                <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
                    <Card className="gap-0 overflow-hidden py-0">
                        <CardHeader className="border-b px-5 py-4">
                            <CardHeading icon={FilePenLine}>
                                {correction.field_label}
                            </CardHeading>
                            <CardDescription>
                                {correction.direct
                                    ? 'Modifiée directement par l’étudiant.'
                                    : 'Valeur officielle et valeur proposée.'}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-5 px-5 py-5">
                            <dl className="grid gap-3 md:grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] md:items-center">
                                <div className="rounded-lg bg-muted/60 px-4 py-3">
                                    <dt className="text-xs text-muted-foreground">
                                        {correction.direct
                                            ? 'Valeur officielle'
                                            : 'Actuelle'}
                                    </dt>
                                    <dd
                                        className={cn(
                                            'mt-1 text-lg font-semibold break-words whitespace-pre-line text-muted-foreground',
                                            correction.current_value === null &&
                                                'italic',
                                            correction.status === 'pending' &&
                                                correction.current_value !==
                                                    null &&
                                                'line-through decoration-danger/60',
                                        )}
                                    >
                                        {correction.current_value ??
                                            'Non renseignée'}
                                    </dd>
                                </div>
                                <span
                                    className="flex justify-center text-muted-foreground"
                                    aria-hidden="true"
                                >
                                    <ArrowRight className="hidden size-5 md:block" />
                                    <ArrowDown className="size-5 md:hidden" />
                                </span>
                                <div className="rounded-lg border border-primary/25 bg-accent/40 px-4 py-3">
                                    <dt className="text-xs text-muted-foreground">
                                        Proposée
                                    </dt>
                                    <dd className="mt-1 text-lg font-bold break-words whitespace-pre-line text-foreground">
                                        {correction.proposed_value}
                                    </dd>
                                </div>
                            </dl>

                            <div className="space-y-1.5">
                                <p className="flex items-center gap-2 text-xs text-muted-foreground">
                                    <MessageSquareQuote
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    Message de l’étudiant
                                </p>
                                {correction.student_message ? (
                                    <blockquote className="rounded-md border-l-2 border-primary/40 bg-muted/40 px-4 py-2.5 text-sm break-words whitespace-pre-line text-foreground">
                                        {correction.student_message}
                                    </blockquote>
                                ) : (
                                    <p className="text-sm text-muted-foreground">
                                        Aucun message.
                                    </p>
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    <div className="space-y-6">
                        <Card className="gap-0 overflow-hidden py-0">
                            <CardHeader className="border-b px-5 py-4">
                                <CardHeading icon={Gavel}>Décision</CardHeading>
                            </CardHeader>
                            <CardContent className="space-y-3 px-5 py-4">
                                {canReview ? (
                                    <p className="text-sm text-muted-foreground">
                                        Seule cette information change sur la
                                        fiche. Un refus doit être motivé.
                                    </p>
                                ) : correction.status === 'pending' ? (
                                    <p className="flex items-start gap-2 text-sm text-muted-foreground">
                                        <Info
                                            className="mt-0.5 size-4 shrink-0"
                                            aria-hidden="true"
                                        />
                                        Consultation seule : la décision revient
                                        à un compte qui peut modifier cette
                                        identité.
                                    </p>
                                ) : (
                                    <p className="text-sm text-muted-foreground">
                                        {correction.direct
                                            ? 'Aucune décision : la valeur est déjà enregistrée.'
                                            : 'Demande traitée.'}
                                    </p>
                                )}
                                {correction.rejection_reason && (
                                    <div className="rounded-md border border-danger/30 bg-danger-soft px-3 py-2.5 text-sm text-danger-foreground">
                                        <p className="text-xs font-semibold">
                                            Motif (vu par l’étudiant)
                                        </p>
                                        <p className="mt-1 break-words whitespace-pre-line">
                                            {correction.rejection_reason}
                                        </p>
                                    </div>
                                )}
                                <InputError message={errors.correction} />
                            </CardContent>
                            {canReview && (
                                <CardFooter className="grid grid-cols-2 gap-2 border-t bg-muted/30 px-5 py-3">
                                    <Button
                                        variant="outline"
                                        className="text-danger hover:bg-danger-soft hover:text-danger-foreground"
                                        onClick={() => open('reject')}
                                    >
                                        <X aria-hidden="true" />
                                        Refuser
                                    </Button>
                                    <Button
                                        disabled={correction.conflict}
                                        onClick={() => open('approve')}
                                    >
                                        <Check aria-hidden="true" />
                                        Approuver
                                    </Button>
                                </CardFooter>
                            )}
                        </Card>

                        <Card className="gap-0 overflow-hidden py-0">
                            <CardHeader className="border-b px-5 py-4">
                                <CardHeading icon={Clock}>Suivi</CardHeading>
                            </CardHeader>
                            <CardContent className="px-5 py-4">
                                <ol className="relative space-y-4 before:absolute before:top-1.5 before:bottom-1.5 before:left-[5px] before:w-px before:bg-border">
                                    {steps.map((step) => (
                                        <li
                                            key={step.label}
                                            className="relative pl-6"
                                        >
                                            <span
                                                className="absolute top-1.5 left-0 size-[11px] rounded-full border-2 border-card bg-primary ring-1 ring-primary/30"
                                                aria-hidden="true"
                                            />
                                            <p className="text-xs text-muted-foreground">
                                                {step.label}
                                            </p>
                                            <p className="text-sm font-medium break-words text-foreground">
                                                {step.value}
                                            </p>
                                        </li>
                                    ))}
                                </ol>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </PageBody>

            <Dialog
                open={decision !== null}
                onOpenChange={(value) => {
                    if (!value && !form.processing) setDecision(null);
                }}
            >
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>
                            {decision === 'approve'
                                ? 'Approuver la correction'
                                : 'Refuser la demande'}
                        </DialogTitle>
                        <DialogDescription>
                            {decision === 'approve'
                                ? `${correction.field_label} deviendra « ${correction.proposed_value} » sur la fiche officielle.`
                                : 'L’étudiant verra ce motif. N’y mettez aucune information interne.'}
                        </DialogDescription>
                    </DialogHeader>
                    {decision === 'reject' && (
                        <div className="space-y-2">
                            <div className="flex items-baseline justify-between gap-2">
                                <Label htmlFor="reason">Motif</Label>
                                <span className="text-xs text-muted-foreground tabular-nums">
                                    {form.data.rejection_reason.length}/500
                                </span>
                            </div>
                            <Textarea
                                id="reason"
                                autoFocus
                                className="min-h-24"
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
                    <DialogFooter className="gap-2">
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
                            {form.processing && <Spinner />}
                            {decision === 'approve' ? 'Approuver' : 'Refuser'}
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
