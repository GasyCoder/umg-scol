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
    History,
    MessageSquareQuote,
    X,
} from 'lucide-react';
import { useState } from 'react';
import { CorrectionStatusBadge } from '@/components/corrections/status-badge';
import InputError from '@/components/input-error';
import { PageBody, PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
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
    /** Sex, nationality or address the student saved without validation. */
    direct: boolean;
    conflict: boolean;
    canReview: boolean;
};

const date = (value: string) =>
    new Date(value).toLocaleDateString('fr-FR', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });

/**
 * Every request of one student on one page (ADR-090), pending first: each is decided on the spot,
 * then the agent stays here for the next one.
 */
export default function CorrectionsStudent({
    student,
    corrections,
    canViewStudent,
}: {
    student: {
        uuid: string;
        last_name: string;
        first_names: string | null;
        birth_date: string | null;
        is_active: boolean;
        establishments: string[];
    };
    corrections: Correction[];
    canViewStudent: boolean;
}) {
    const [target, setTarget] = useState<{
        correction: Correction;
        decision: 'approve' | 'reject';
    } | null>(null);
    const form = useForm({
        decision: 'approve',
        rejection_reason: '',
        from_student: true,
    });
    const errors = form.errors as Record<string, string | undefined>;
    const open = (correction: Correction, decision: 'approve' | 'reject') => {
        form.reset();
        form.clearErrors();
        form.setData((data) => ({ ...data, decision }));
        setTarget({ correction, decision });
    };
    const confirm = () => {
        if (!target) return;
        form.post(`/corrections/${target.correction.uuid}/review`, {
            preserveScroll: true,
            onSuccess: () => setTarget(null),
        });
    };
    const pending = corrections.filter((item) => item.status === 'pending');
    const history = corrections.filter((item) => item.status !== 'pending');
    const name = `${student.last_name} ${student.first_names ?? ''}`.trim();

    return (
        <>
            <Head title={`Corrections · ${name}`} />
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
                        <span className="inline-flex items-center rounded-full bg-accent px-3 py-1 text-sm font-medium text-accent-foreground tabular-nums">
                            {pending.length > 0
                                ? `${pending.length} à examiner sur ${corrections.length}`
                                : `${corrections.length} demande${corrections.length > 1 ? 's' : ''}, toutes traitées`}
                        </span>
                    }
                    actions={
                        <>
                            {canViewStudent && (
                                <Button variant="outline" asChild>
                                    <Link href={`/students/${student.uuid}`}>
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

                {pending.length > 0 && (
                    <section aria-labelledby="to-review" className="space-y-3">
                        <h2
                            id="to-review"
                            className="flex items-center gap-2 text-sm font-semibold text-foreground"
                        >
                            <Clock
                                className="size-4 text-warning"
                                aria-hidden="true"
                            />
                            À examiner
                            <span className="text-muted-foreground tabular-nums">
                                {pending.length}
                            </span>
                        </h2>
                        <div className="grid gap-4 lg:grid-cols-2">
                            {pending.map((correction) => (
                                <Card
                                    key={correction.uuid}
                                    className="gap-0 overflow-hidden py-0"
                                >
                                    <CardHeader className="flex-row items-start justify-between gap-3 border-b px-5 py-3.5">
                                        <div className="min-w-0">
                                            <CardTitle className="text-base">
                                                {correction.field_label}
                                            </CardTitle>
                                            <p className="mt-0.5 text-xs text-muted-foreground tabular-nums">
                                                Demandée le{' '}
                                                {date(correction.created_at)}
                                            </p>
                                        </div>
                                        <CorrectionStatusBadge
                                            status={correction.status}
                                            label={correction.status_label}
                                        />
                                    </CardHeader>
                                    <CardContent className="space-y-3 px-5 py-4">
                                        {correction.conflict && (
                                            <p
                                                role="alert"
                                                className="flex items-start gap-2 rounded-md border border-danger/30 bg-danger-soft px-3 py-2 text-sm text-danger-foreground"
                                            >
                                                <AlertTriangle
                                                    className="mt-0.5 size-4 shrink-0"
                                                    aria-hidden="true"
                                                />
                                                La fiche a changé depuis la
                                                demande : vérifiez avant de
                                                décider.
                                            </p>
                                        )}
                                        <dl className="grid gap-2 sm:grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] sm:items-center">
                                            <div className="rounded-md bg-muted/60 px-3 py-2.5">
                                                <dt className="text-xs text-muted-foreground">
                                                    Actuelle
                                                </dt>
                                                <dd
                                                    className={cn(
                                                        'mt-0.5 text-sm font-medium break-words text-muted-foreground',
                                                        correction.current_value ===
                                                            null && 'italic',
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
                                                <ArrowRight className="hidden size-4 sm:block" />
                                                <ArrowDown className="size-4 sm:hidden" />
                                            </span>
                                            <div className="rounded-md border border-primary/25 bg-accent/40 px-3 py-2.5">
                                                <dt className="text-xs text-muted-foreground">
                                                    Proposée
                                                </dt>
                                                <dd className="mt-0.5 text-sm font-semibold break-words text-foreground">
                                                    {correction.proposed_value}
                                                </dd>
                                            </div>
                                        </dl>
                                        {correction.student_message && (
                                            <p className="flex items-start gap-2 text-sm text-muted-foreground">
                                                <MessageSquareQuote
                                                    className="mt-0.5 size-4 shrink-0"
                                                    aria-hidden="true"
                                                />
                                                <span className="whitespace-pre-line">
                                                    {correction.student_message}
                                                </span>
                                            </p>
                                        )}
                                    </CardContent>
                                    {correction.canReview ? (
                                        <CardFooter className="justify-end gap-2 border-t bg-muted/30 px-5 py-3">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                className="text-danger hover:bg-danger-soft hover:text-danger-foreground"
                                                onClick={() =>
                                                    open(correction, 'reject')
                                                }
                                            >
                                                <X aria-hidden="true" />
                                                Refuser
                                            </Button>
                                            <Button
                                                size="sm"
                                                onClick={() =>
                                                    open(correction, 'approve')
                                                }
                                            >
                                                <Check aria-hidden="true" />
                                                Approuver
                                            </Button>
                                        </CardFooter>
                                    ) : (
                                        <CardFooter className="border-t bg-muted/30 px-5 py-3 text-xs text-muted-foreground">
                                            Consultation seule : la décision
                                            revient à l’établissement de
                                            l’étudiant.
                                        </CardFooter>
                                    )}
                                </Card>
                            ))}
                        </div>
                    </section>
                )}

                {history.length > 0 && (
                    <Card className="gap-0 overflow-hidden py-0">
                        <CardHeader className="border-b px-5 py-3.5">
                            <CardTitle className="flex items-center gap-2 text-base">
                                <History
                                    className="size-4 text-primary"
                                    aria-hidden="true"
                                />
                                Historique
                            </CardTitle>
                        </CardHeader>
                        <ul className="divide-y">
                            {history.map((correction) => (
                                <li
                                    key={correction.uuid}
                                    className="flex flex-col gap-1 px-5 py-3 sm:flex-row sm:items-start sm:justify-between sm:gap-4"
                                >
                                    <div className="min-w-0">
                                        <p className="text-sm font-medium text-foreground">
                                            {correction.field_label}
                                            <span className="font-normal text-muted-foreground">
                                                {' '}
                                                → {correction.proposed_value}
                                            </span>
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {correction.direct
                                                ? `Enregistrée par l’étudiant le ${date(correction.created_at)}`
                                                : correction.status ===
                                                    'cancelled'
                                                  ? `Annulée par l’étudiant · demandée le ${date(correction.created_at)}`
                                                  : `${correction.status_label}${correction.reviewed_at ? ` le ${date(correction.reviewed_at)}` : ''}${correction.reviewer ? ` par ${correction.reviewer}` : ''}`}
                                        </p>
                                        {correction.rejection_reason && (
                                            <p className="mt-1 text-sm text-foreground">
                                                <span className="text-muted-foreground">
                                                    Motif :
                                                </span>{' '}
                                                {correction.rejection_reason}
                                            </p>
                                        )}
                                    </div>
                                    <CorrectionStatusBadge
                                        status={correction.status}
                                        label={
                                            correction.direct
                                                ? 'Enregistrée'
                                                : correction.status_label
                                        }
                                    />
                                </li>
                            ))}
                        </ul>
                    </Card>
                )}
            </PageBody>

            <Dialog
                open={target !== null}
                onOpenChange={(next) =>
                    !next && !form.processing && setTarget(null)
                }
            >
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>
                            {target?.decision === 'approve'
                                ? 'Approuver la correction'
                                : 'Refuser la demande'}
                        </DialogTitle>
                        <DialogDescription>
                            {target?.decision === 'approve'
                                ? `${target.correction.field_label} deviendra « ${target.correction.proposed_value} » sur la fiche officielle.`
                                : 'L’étudiant verra ce motif dans son espace.'}
                        </DialogDescription>
                    </DialogHeader>
                    {target?.decision === 'reject' && (
                        <div className="space-y-2">
                            <Label htmlFor="rejection-reason">Motif</Label>
                            <Textarea
                                id="rejection-reason"
                                autoFocus
                                maxLength={500}
                                className="min-h-24"
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
                            onClick={() => setTarget(null)}
                        >
                            Annuler
                        </Button>
                        <Button
                            variant={
                                target?.decision === 'reject'
                                    ? 'destructive'
                                    : 'default'
                            }
                            disabled={form.processing}
                            onClick={confirm}
                        >
                            {form.processing && <Spinner />}
                            {target?.decision === 'approve'
                                ? 'Approuver'
                                : 'Refuser'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
CorrectionsStudent.layout = {
    breadcrumbs: [{ title: 'Corrections', href: '/corrections' }],
};
