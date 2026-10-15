import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowLeft,
    Ban,
    CalendarCheck,
    CalendarClock,
    CircleCheck,
    CircleDashed,
    CircleMinus,
    CircleX,
    ExternalLink,
    FilePenLine,
    FileText,
    FolderOpen,
    Gavel,
    History,
    Inbox,
    Info,
    ListChecks,
    MessageSquareWarning,
    PlayCircle,
    UserRound,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useState } from 'react';
import { PageBody, PageHeader } from '@/components/page-header';
import { formatDate, StatusBadge } from '@/components/scholarships/shared';
import type { Option } from '@/components/scholarships/shared';
import { ToneBadge } from '@/components/tone-badge';
import type { Tone } from '@/components/tone-badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';

type Stamp = { at: string; by: string | null } | null;
type Check = {
    key: string;
    label: string;
    status: string;
    status_label: string;
    note: string | null;
    checked: Stamp;
};
type Application = {
    id: number;
    uuid: string;
    application_number: string;
    ddb_number: string | null;
    status: string;
    status_label: string;
    decision_reason: string | null;
    internal_notes: string | null;
    campaign: { name: string; academic_year: string; status_label: string };
    received: { at: string; by: string };
    review_started: Stamp;
    decided: Stamp;
    student: {
        uuid: string;
        student_number: string | null;
        last_name: string;
        first_names: string | null;
        birth_date: string | null;
        birth_place: string | null;
        is_active: boolean;
        academic_year: string;
        establishment: string;
        level: string;
        program: string | null;
        record_status: string;
    };
    checks: Check[];
    history: {
        from: string | null;
        to: string;
        by: string;
        at: string;
        reason: string | null;
    }[];
};

const actionLabels: Record<string, string> = {
    in_review: 'Commencer la vérification',
    validated: 'Valider le dossier',
    needs_correction: 'Demander une correction',
    rejected: 'Rejeter le dossier',
};

const alertTones = {
    warning: 'border-warning/30 bg-warning-soft text-warning-foreground',
    info: 'border-info/30 bg-info-soft text-info-foreground',
    danger: 'border-danger/30 bg-danger-soft text-danger-foreground',
};

/** One control of the checklist: icon, tone and wording per result. */
const checkStyles: Record<
    string,
    { icon: LucideIcon; tone: Tone; tile: string }
> = {
    ok: {
        icon: CircleCheck,
        tone: 'success',
        tile: 'bg-success-soft text-success',
    },
    issue: {
        icon: CircleX,
        tone: 'danger',
        tile: 'bg-danger-soft text-danger',
    },
    not_applicable: {
        icon: CircleMinus,
        tone: 'neutral',
        tile: 'bg-secondary text-muted-foreground',
    },
    pending: {
        icon: CircleDashed,
        tone: 'warning',
        tile: 'bg-warning-soft text-warning',
    },
};
const checkStyle = (status: string) =>
    checkStyles[status] ?? checkStyles.pending;

const actionIcons: Record<string, LucideIcon> = {
    in_review: PlayCircle,
    validated: CircleCheck,
    needs_correction: FilePenLine,
    rejected: Ban,
};

function Section({
    title,
    description,
    icon: Icon,
    action,
    children,
    className,
}: {
    title: string;
    description?: string;
    icon: LucideIcon;
    action?: React.ReactNode;
    children: React.ReactNode;
    className?: string;
}) {
    return (
        <section className={cn('surface min-w-0', className)}>
            <header className="flex flex-wrap items-start justify-between gap-3 border-b px-5 py-4">
                <div className="flex min-w-0 items-start gap-3">
                    <span
                        className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-accent text-primary"
                        aria-hidden="true"
                    >
                        <Icon className="size-[18px]" />
                    </span>
                    <div className="min-w-0">
                        <h2 className="text-base font-bold text-foreground">
                            {title}
                        </h2>
                        {description && (
                            <p className="text-sm text-muted-foreground">
                                {description}
                            </p>
                        )}
                    </div>
                </div>
                {action}
            </header>
            <div className="p-5">{children}</div>
        </section>
    );
}

function Rows({
    rows,
    columns = 2,
}: {
    rows: [string, React.ReactNode][];
    columns?: 1 | 2;
}) {
    return (
        <dl
            className={cn(
                'grid gap-x-6 gap-y-4',
                columns === 2 && 'sm:grid-cols-2',
            )}
        >
            {rows.map(([label, value]) => (
                <div key={label} className="min-w-0">
                    <dt className="text-xs font-medium text-muted-foreground">
                        {label}
                    </dt>
                    <dd className="mt-0.5 font-medium break-words whitespace-pre-line text-foreground">
                        {value ?? '—'}
                    </dd>
                </div>
            ))}
        </dl>
    );
}

function stamp(value: Stamp) {
    return value
        ? `${formatDate(value.at, true)} par ${value.by ?? '—'}`
        : null;
}

export default function ApplicationShow({
    application,
    checkStatuses,
    actions,
    canEditChecks,
    canViewStudent,
}: {
    application: Application;
    checkStatuses: Option[];
    actions: string[];
    canEditChecks: boolean;
    canViewStudent: boolean;
}) {
    const [target, setTarget] = useState<string | null>(null);
    const form = useForm({ status: '', reason: '' });
    const errors = {
        ...(usePage().props.errors as Record<string, string | undefined>),
        ...(form.errors as Record<string, string | undefined>),
    };
    const needsReason = target === 'needs_correction' || target === 'rejected';
    const updateCheck = (check: Check, status: string, note: string) =>
        router.post(
            `/scholarships/applications/${application.uuid}/checks`,
            { key: check.key, status, note },
            { preserveScroll: true },
        );
    const student = application.student;
    const fullName = `${student.last_name} ${student.first_names ?? ''}`.trim();
    const done = application.checks.filter(
        (check) => check.status !== 'pending',
    ).length;
    const issues = application.checks.filter(
        (check) => check.status === 'issue',
    ).length;
    const total = application.checks.length;
    const progress = total > 0 ? Math.round((done / total) * 100) : 0;
    const pageError = errors.status ?? errors.check;
    const hasActions = actions.length > 0;
    const reasonTone: keyof typeof alertTones =
        application.status === 'rejected'
            ? 'danger'
            : application.status === 'needs_correction'
              ? 'warning'
              : 'info';
    const startAction = (action: string) => {
        form.reset();
        form.clearErrors();
        form.setData('status', action);
        setTarget(action);
    };

    return (
        <PageBody>
            <Head title={`Dossier ${application.application_number}`} />
            <PageHeader
                icon={FileText}
                title={`Dossier ${application.application_number}`}
                description={`${fullName} — ${application.campaign.name}`}
                meta={
                    <div className="flex flex-wrap items-center gap-2">
                        <StatusBadge
                            status={application.status}
                            label={application.status_label}
                        />
                        {application.ddb_number && (
                            <ToneBadge tone="neutral">
                                DDB {application.ddb_number}
                            </ToneBadge>
                        )}
                        <ToneBadge tone="neutral">
                            Reçu le {formatDate(application.received.at)}
                        </ToneBadge>
                    </div>
                }
                actions={
                    <Button variant="outline" asChild>
                        <Link href="/scholarships/applications">
                            <ArrowLeft aria-hidden="true" />
                            Tous les dossiers
                        </Link>
                    </Button>
                }
            />

            {pageError && (
                <p
                    role="alert"
                    className={cn(
                        'flex items-start gap-2 rounded-xl border px-4 py-3 text-sm',
                        alertTones.danger,
                    )}
                >
                    <AlertTriangle
                        className="mt-0.5 size-4 shrink-0"
                        aria-hidden="true"
                    />
                    {pageError}
                </p>
            )}

            <div className="grid items-start gap-6 lg:grid-cols-3 lg:grid-rows-[auto_1fr]">
                {hasActions && (
                    <section
                        aria-labelledby="decision-title"
                        className="surface relative overflow-hidden border-primary/30 lg:col-start-3 lg:row-start-1"
                    >
                        <div
                            className="bg-grid absolute inset-0 text-primary opacity-40"
                            aria-hidden="true"
                        />
                        <div className="relative space-y-4 p-5">
                            <div className="flex items-start gap-3">
                                <span
                                    className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary text-primary-foreground"
                                    aria-hidden="true"
                                >
                                    <Gavel className="size-[18px]" />
                                </span>
                                <div className="min-w-0">
                                    <h2
                                        id="decision-title"
                                        className="text-base font-bold text-foreground"
                                    >
                                        Décision
                                    </h2>
                                    <p className="text-sm text-muted-foreground">
                                        Chaque action est confirmée avant d’être
                                        enregistrée.
                                    </p>
                                </div>
                            </div>
                            <div className="grid gap-2">
                                {actions.map((action) => {
                                    const Icon = actionIcons[action];
                                    return (
                                        <Button
                                            key={action}
                                            className={cn(
                                                'h-10 w-full justify-start',
                                                action === 'rejected' &&
                                                    'text-destructive hover:text-destructive',
                                            )}
                                            variant={
                                                action === 'rejected' ||
                                                action === 'needs_correction'
                                                    ? 'outline'
                                                    : 'default'
                                            }
                                            onClick={() => startAction(action)}
                                        >
                                            {Icon && (
                                                <Icon aria-hidden="true" />
                                            )}
                                            {actionLabels[action] ?? action}
                                        </Button>
                                    );
                                })}
                            </div>
                            {actions.includes('validated') && done < total && (
                                <p className="text-xs text-muted-foreground">
                                    {total - done} contrôle
                                    {total - done > 1 ? 's' : ''} encore à
                                    traiter avant la validation.
                                </p>
                            )}
                        </div>
                    </section>
                )}

                <div className="min-w-0 space-y-6 lg:col-span-2 lg:col-start-1 lg:row-span-2 lg:row-start-1">
                    {application.decision_reason && (
                        <div
                            className={cn(
                                'flex items-start gap-3 rounded-xl border px-4 py-3 text-sm',
                                alertTones[reasonTone],
                            )}
                        >
                            <MessageSquareWarning
                                className="mt-0.5 size-5 shrink-0"
                                aria-hidden="true"
                            />
                            <div className="min-w-0">
                                <p className="font-semibold">
                                    Motif (correction ou rejet)
                                </p>
                                <p className="break-words whitespace-pre-line">
                                    {application.decision_reason}
                                </p>
                            </div>
                        </div>
                    )}

                    <Section
                        title="Vérification"
                        description="La validation exige que chaque contrôle soit conforme ou non applicable. Enregistrer un contrôle sur un dossier reçu démarre la vérification."
                        icon={ListChecks}
                    >
                        <div className="mb-5 space-y-2">
                            <div className="flex flex-wrap items-baseline justify-between gap-2 text-sm">
                                <p className="text-muted-foreground">
                                    <span className="font-heading text-lg font-bold text-foreground tabular-nums">
                                        {done}/{total}
                                    </span>{' '}
                                    contrôles traités
                                </p>
                                {issues > 0 && (
                                    <ToneBadge tone="danger">
                                        {issues} non conforme
                                        {issues > 1 ? 's' : ''}
                                    </ToneBadge>
                                )}
                            </div>
                            <div
                                className="h-2 overflow-hidden rounded-full bg-muted"
                                role="progressbar"
                                aria-label="Avancement de la vérification"
                                aria-valuemin={0}
                                aria-valuemax={total}
                                aria-valuenow={done}
                            >
                                <div
                                    className={cn(
                                        'h-full rounded-full transition-all motion-reduce:transition-none',
                                        issues > 0
                                            ? 'bg-warning'
                                            : 'bg-success',
                                    )}
                                    style={{ width: `${progress}%` }}
                                />
                            </div>
                        </div>
                        <ul className="-mx-5 divide-y border-t">
                            {application.checks.map((check) => (
                                <CheckRow
                                    key={check.key}
                                    check={check}
                                    statuses={checkStatuses}
                                    editable={canEditChecks}
                                    onSave={updateCheck}
                                />
                            ))}
                        </ul>
                    </Section>

                    <Section
                        title="Étudiant (données officielles)"
                        icon={UserRound}
                        action={
                            canViewStudent && (
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={`/students/${student.uuid}`}>
                                        <ExternalLink aria-hidden="true" />
                                        Comparer avec la fiche officielle
                                        complète
                                    </Link>
                                </Button>
                            )
                        }
                    >
                        <div className="mb-5 flex items-center gap-4">
                            <span
                                className="flex size-14 shrink-0 items-center justify-center rounded-full bg-primary font-heading text-lg font-bold text-primary-foreground ring-4 ring-accent"
                                aria-hidden="true"
                            >
                                {`${student.last_name.charAt(0)}${student.first_names?.charAt(0) ?? ''}`.toUpperCase()}
                            </span>
                            <div className="min-w-0 space-y-1">
                                <p className="font-heading text-lg leading-tight font-bold break-words text-foreground">
                                    {fullName}
                                </p>
                                <p className="font-mono text-sm text-muted-foreground">
                                    {student.student_number ?? '—'}
                                </p>
                                <div className="flex flex-wrap gap-1.5">
                                    <ToneBadge
                                        tone={
                                            student.is_active
                                                ? 'success'
                                                : 'danger'
                                        }
                                    >
                                        {student.is_active
                                            ? 'Fiche active'
                                            : 'Fiche inactive'}
                                    </ToneBadge>
                                    <ToneBadge
                                        tone={
                                            student.record_status === 'active'
                                                ? 'success'
                                                : 'warning'
                                        }
                                    >
                                        Déclaration{' '}
                                        {student.record_status === 'active'
                                            ? 'active'
                                            : 'inactive'}
                                    </ToneBadge>
                                </div>
                            </div>
                        </div>
                        <Rows
                            rows={[
                                ['Matricule', student.student_number],
                                ['Nom et prénoms', fullName],
                                [
                                    'Naissance',
                                    `${formatDate(student.birth_date)}${student.birth_place ? ` à ${student.birth_place}` : ''}`,
                                ],
                                ['Année universitaire', student.academic_year],
                                ['Établissement', student.establishment],
                                ['Niveau', student.level],
                                [
                                    'Parcours',
                                    student.program ?? 'Non renseigné',
                                ],
                            ]}
                        />
                        <p className="mt-5 flex items-start gap-2 rounded-lg bg-muted px-3 py-2.5 text-sm text-muted-foreground">
                            <Info
                                className="mt-0.5 size-4 shrink-0"
                                aria-hidden="true"
                            />
                            Si une donnée officielle est fausse, l’étudiant ou
                            son établissement doit la faire corriger ; le
                            dossier de bourse ne modifie jamais la fiche.
                        </p>
                    </Section>
                </div>

                <div
                    className={cn(
                        'min-w-0 space-y-6 lg:col-start-3',
                        hasActions
                            ? 'lg:row-start-2'
                            : 'lg:row-span-2 lg:row-start-1',
                    )}
                >
                    <Section title="Dossier" icon={FolderOpen}>
                        <Rows
                            columns={1}
                            rows={[
                                [
                                    'N° dossier',
                                    <span className="font-mono">
                                        {application.application_number}
                                    </span>,
                                ],
                                ['N° DDB', application.ddb_number],
                                [
                                    'Campagne',
                                    `${application.campaign.name} (${application.campaign.academic_year}) — ${application.campaign.status_label}`,
                                ],
                                ['Statut', application.status_label],
                                [
                                    'Observations internes',
                                    application.internal_notes,
                                ],
                            ]}
                        />
                    </Section>

                    <Section title="Historique" icon={History}>
                        <dl className="mb-5 grid gap-3">
                            {(
                                [
                                    [
                                        'Réception',
                                        stamp(application.received),
                                        Inbox,
                                    ],
                                    [
                                        'Début de vérification',
                                        stamp(application.review_started),
                                        CalendarClock,
                                    ],
                                    [
                                        'Décision définitive',
                                        stamp(application.decided),
                                        CalendarCheck,
                                    ],
                                ] as [string, string | null, LucideIcon][]
                            ).map(([label, value, Icon]) => (
                                <div
                                    key={label}
                                    className="flex items-start gap-3 rounded-lg border bg-muted/40 px-3 py-2.5"
                                >
                                    <Icon
                                        className={cn(
                                            'mt-0.5 size-4 shrink-0',
                                            value
                                                ? 'text-primary'
                                                : 'text-muted-foreground',
                                        )}
                                        aria-hidden="true"
                                    />
                                    <div className="min-w-0">
                                        <dt className="text-xs text-muted-foreground">
                                            {label}
                                        </dt>
                                        <dd className="text-sm font-medium text-foreground">
                                            {value ?? '—'}
                                        </dd>
                                    </div>
                                </div>
                            ))}
                        </dl>
                        <ol className="relative space-y-4 before:absolute before:top-2 before:bottom-2 before:left-[7px] before:w-px before:bg-border">
                            {application.history.map((entry, index) => {
                                const last =
                                    index === application.history.length - 1;
                                return (
                                    <li key={index} className="relative pl-7">
                                        <span
                                            className={cn(
                                                'absolute top-1 left-0 size-[15px] rounded-full border-[3px] border-card ring-1',
                                                last
                                                    ? 'bg-primary ring-primary/40'
                                                    : 'bg-muted-foreground/50 ring-border',
                                            )}
                                            aria-hidden="true"
                                        />
                                        <p className="text-sm font-semibold text-foreground">
                                            {entry.from ? (
                                                <>
                                                    <span className="font-medium text-muted-foreground">
                                                        {entry.from}
                                                    </span>
                                                    <span aria-hidden="true">
                                                        {' → '}
                                                    </span>
                                                    <span className="sr-only">
                                                        {' vers '}
                                                    </span>
                                                </>
                                            ) : null}
                                            {entry.to}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            <time dateTime={entry.at}>
                                                {formatDate(entry.at, true)}
                                            </time>{' '}
                                            par {entry.by}
                                        </p>
                                        {entry.reason && (
                                            <p className="mt-1.5 rounded-md bg-muted px-2.5 py-1.5 text-xs text-foreground">
                                                Motif : {entry.reason}
                                            </p>
                                        )}
                                    </li>
                                );
                            })}
                        </ol>
                    </Section>
                </div>
            </div>

            <Dialog
                open={target !== null}
                onOpenChange={(open) => {
                    if (!open && !form.processing) setTarget(null);
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {target ? actionLabels[target] : ''}
                        </DialogTitle>
                        <DialogDescription>
                            {target === 'validated'
                                ? 'Le dossier sera déclaré conforme. Cette décision est définitive.'
                                : target === 'rejected'
                                  ? 'Le rejet est définitif. Indiquez un motif factuel.'
                                  : target === 'needs_correction'
                                    ? 'Indiquez ce qui doit être corrigé ou complété dans le dossier papier. La fiche officielle n’est pas modifiée.'
                                    : 'Vous commencez la vérification de ce dossier.'}
                        </DialogDescription>
                    </DialogHeader>
                    {needsReason && (
                        <div className="space-y-2">
                            <Label htmlFor="reason">Motif</Label>
                            <textarea
                                id="reason"
                                className="min-h-28 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive"
                                minLength={10}
                                maxLength={1000}
                                value={form.data.reason}
                                aria-invalid={errors.reason ? true : undefined}
                                aria-describedby={
                                    errors.reason ? undefined : 'reason-hint'
                                }
                                onChange={(e) =>
                                    form.setData('reason', e.target.value)
                                }
                            />
                            {errors.reason ? (
                                <p
                                    role="alert"
                                    className="text-sm text-destructive"
                                >
                                    {errors.reason}
                                </p>
                            ) : (
                                <p
                                    id="reason-hint"
                                    className="text-xs text-muted-foreground"
                                >
                                    {form.data.reason.length}/1000 caractères —
                                    10 au minimum.
                                </p>
                            )}
                        </div>
                    )}
                    {errors.status && (
                        <p role="alert" className="text-sm text-destructive">
                            {errors.status}
                        </p>
                    )}
                    <DialogFooter>
                        <Button
                            variant="outline"
                            disabled={form.processing}
                            onClick={() => setTarget(null)}
                        >
                            Annuler
                        </Button>
                        <Button
                            variant={
                                target === 'rejected'
                                    ? 'destructive'
                                    : 'default'
                            }
                            disabled={form.processing}
                            onClick={() =>
                                form.post(
                                    `/scholarships/applications/${application.uuid}/status`,
                                    {
                                        preserveScroll: true,
                                        onSuccess: () => setTarget(null),
                                    },
                                )
                            }
                        >
                            {form.processing && <Spinner />}
                            {form.processing ? 'Traitement…' : 'Confirmer'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </PageBody>
    );
}

function CheckRow({
    check,
    statuses,
    editable,
    onSave,
}: {
    check: Check;
    statuses: Option[];
    editable: boolean;
    onSave: (check: Check, status: string, note: string) => void;
}) {
    const [note, setNote] = useState(check.note ?? '');
    const style = checkStyle(check.status);
    const Icon = style.icon;
    return (
        <li className="flex gap-3 px-5 py-4">
            <span
                className={cn(
                    'flex size-9 shrink-0 items-center justify-center rounded-full',
                    style.tile,
                )}
                aria-hidden="true"
            >
                <Icon className="size-5" />
            </span>
            <div className="min-w-0 flex-1 space-y-2">
                <div className="flex flex-wrap items-start justify-between gap-2">
                    <div className="min-w-0">
                        <p className="font-semibold text-foreground">
                            {check.label}
                        </p>
                        {check.checked && (
                            <p className="text-xs text-muted-foreground">
                                {formatDate(check.checked.at, true)} par{' '}
                                {check.checked.by ?? '—'}
                            </p>
                        )}
                    </div>
                    <ToneBadge tone={style.tone}>
                        {check.status_label}
                    </ToneBadge>
                </div>
                {editable ? (
                    <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
                        <Input
                            aria-label={`Note : ${check.label}`}
                            className="h-9 min-w-0 flex-1"
                            maxLength={500}
                            placeholder="Note (facultative)"
                            value={note}
                            onChange={(e) => setNote(e.target.value)}
                        />
                        <div
                            role="group"
                            aria-label={`Résultat : ${check.label}`}
                            className="flex flex-wrap gap-1.5"
                        >
                            {statuses
                                .filter((status) => status.value !== 'pending')
                                .map((status) => {
                                    const active =
                                        check.status === status.value;
                                    const StatusIcon = checkStyle(
                                        status.value,
                                    ).icon;
                                    return (
                                        <Button
                                            key={status.value}
                                            size="sm"
                                            aria-pressed={active}
                                            variant={
                                                active ? 'default' : 'outline'
                                            }
                                            onClick={() =>
                                                onSave(
                                                    check,
                                                    status.value,
                                                    note,
                                                )
                                            }
                                        >
                                            <StatusIcon aria-hidden="true" />
                                            {status.label}
                                        </Button>
                                    );
                                })}
                        </div>
                    </div>
                ) : (
                    check.note && (
                        <p className="rounded-md bg-muted px-3 py-2 text-sm text-foreground">
                            {check.note}
                        </p>
                    )
                )}
            </div>
        </li>
    );
}
ApplicationShow.layout = {
    breadcrumbs: [{ title: 'Dossiers', href: '/scholarships/applications' }],
};
