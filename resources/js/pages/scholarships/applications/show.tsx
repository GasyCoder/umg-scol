import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { formatDate, StatusBadge } from '@/components/scholarships/shared';
import type { Option } from '@/components/scholarships/shared';
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

function Section({
    title,
    children,
}: {
    title: string;
    children: React.ReactNode;
}) {
    return (
        <section className="space-y-3 rounded-md border p-5">
            <h2 className="font-semibold">{title}</h2>
            {children}
        </section>
    );
}

function Rows({ rows }: { rows: [string, string | null][] }) {
    return (
        <dl className="grid gap-3 sm:grid-cols-2">
            {rows.map(([label, value]) => (
                <div key={label}>
                    <dt className="text-sm text-muted-foreground">{label}</dt>
                    <dd className="break-words whitespace-pre-line">
                        {value ?? '—'}
                    </dd>
                </div>
            ))}
        </dl>
    );
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
            `/scholarships/applications/${application.id}/checks`,
            { key: check.key, status, note },
            { preserveScroll: true },
        );
    const student = application.student;
    return (
        <div className="max-w-5xl space-y-6 p-4 md:p-6">
            <Head title={`Dossier ${application.application_number}`} />
            <div className="flex flex-wrap items-start justify-between gap-3">
                <Heading
                    title={`Dossier ${application.application_number}`}
                    description={`${student.last_name} ${student.first_names ?? ''} — ${application.campaign.name}`}
                />
                <StatusBadge
                    status={application.status}
                    label={application.status_label}
                />
            </div>
            <InputError message={errors.status ?? errors.check} />
            {actions.length > 0 && (
                <div className="flex flex-wrap gap-2">
                    {actions.map((action) => (
                        <Button
                            key={action}
                            variant={
                                action === 'rejected' ? 'outline' : 'default'
                            }
                            onClick={() => {
                                form.reset();
                                form.clearErrors();
                                form.setData('status', action);
                                setTarget(action);
                            }}
                        >
                            {actionLabels[action] ?? action}
                        </Button>
                    ))}
                </div>
            )}
            <Section title="Dossier">
                <Rows
                    rows={[
                        ['N° dossier', application.application_number],
                        ['N° DDB', application.ddb_number],
                        [
                            'Campagne',
                            `${application.campaign.name} (${application.campaign.academic_year}) — ${application.campaign.status_label}`,
                        ],
                        ['Statut', application.status_label],
                        [
                            'Motif (correction ou rejet)',
                            application.decision_reason,
                        ],
                        ['Observations internes', application.internal_notes],
                    ]}
                />
            </Section>
            <Section title="Étudiant (données officielles)">
                <Rows
                    rows={[
                        ['Matricule', student.student_number],
                        [
                            'Nom et prénoms',
                            `${student.last_name} ${student.first_names ?? ''}`,
                        ],
                        [
                            'Naissance',
                            `${formatDate(student.birth_date)}${student.birth_place ? ` à ${student.birth_place}` : ''}`,
                        ],
                        ['Année universitaire', student.academic_year],
                        ['Établissement', student.establishment],
                        ['Niveau', student.level],
                        ['Parcours', student.program ?? 'Non renseigné'],
                        [
                            'Situation',
                            `${student.is_active ? 'Fiche active' : 'Fiche inactive'} · déclaration ${student.record_status === 'active' ? 'active' : 'inactive'}`,
                        ],
                    ]}
                />
                {canViewStudent && (
                    <Button variant="outline" size="sm" asChild>
                        <Link href={`/students/${student.uuid}`}>
                            Comparer avec la fiche officielle complète
                        </Link>
                    </Button>
                )}
                <p className="text-sm text-muted-foreground">
                    Si une donnée officielle est fausse, l’étudiant ou son
                    établissement doit la faire corriger ; le dossier de bourse
                    ne modifie jamais la fiche.
                </p>
            </Section>
            <Section title="Vérification">
                <div className="divide-y">
                    {application.checks.map((check) => (
                        <CheckRow
                            key={check.key}
                            check={check}
                            statuses={checkStatuses}
                            editable={canEditChecks}
                            onSave={updateCheck}
                        />
                    ))}
                </div>
                <p className="text-sm text-muted-foreground">
                    La validation exige que chaque contrôle soit conforme ou non
                    applicable. Enregistrer un contrôle sur un dossier reçu
                    démarre la vérification.
                </p>
            </Section>
            <Section title="Historique">
                <ol className="space-y-2">
                    {application.history.map((entry, index) => (
                        <li key={index} className="text-sm">
                            <span className="font-medium">
                                {entry.from ? `${entry.from} → ` : ''}
                                {entry.to}
                            </span>{' '}
                            — {formatDate(entry.at, true)} par {entry.by}
                            {entry.reason && (
                                <span className="block text-muted-foreground">
                                    Motif : {entry.reason}
                                </span>
                            )}
                        </li>
                    ))}
                </ol>
                <Rows
                    rows={[
                        [
                            'Réception',
                            `${formatDate(application.received.at, true)} par ${application.received.by}`,
                        ],
                        [
                            'Début de vérification',
                            application.review_started
                                ? `${formatDate(application.review_started.at, true)} par ${application.review_started.by ?? '—'}`
                                : null,
                        ],
                        [
                            'Décision définitive',
                            application.decided
                                ? `${formatDate(application.decided.at, true)} par ${application.decided.by ?? '—'}`
                                : null,
                        ],
                    ]}
                />
            </Section>
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
                                className="min-h-24 w-full rounded-md border bg-transparent p-2 text-sm"
                                minLength={10}
                                maxLength={1000}
                                value={form.data.reason}
                                onChange={(e) =>
                                    form.setData('reason', e.target.value)
                                }
                            />
                            <InputError message={errors.reason} />
                        </div>
                    )}
                    <InputError message={errors.status} />
                    <DialogFooter>
                        <Button
                            variant="outline"
                            disabled={form.processing}
                            onClick={() => setTarget(null)}
                        >
                            Annuler
                        </Button>
                        <Button
                            disabled={form.processing}
                            onClick={() =>
                                form.post(
                                    `/scholarships/applications/${application.id}/status`,
                                    {
                                        preserveScroll: true,
                                        onSuccess: () => setTarget(null),
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
    return (
        <div className="space-y-2 py-3">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <p className="font-medium">{check.label}</p>
                <StatusBadge
                    status={
                        check.status === 'issue'
                            ? 'rejected'
                            : check.status === 'ok'
                              ? 'validated'
                              : 'pending'
                    }
                    label={check.status_label}
                />
            </div>
            {check.checked && (
                <p className="text-xs text-muted-foreground">
                    {formatDate(check.checked.at, true)} par{' '}
                    {check.checked.by ?? '—'}
                </p>
            )}
            {editable ? (
                <div className="flex flex-wrap items-center gap-2">
                    <input
                        aria-label={`Note : ${check.label}`}
                        className="h-9 min-w-48 flex-1 rounded-md border bg-transparent px-2 text-sm"
                        maxLength={500}
                        placeholder="Note (facultative)"
                        value={note}
                        onChange={(e) => setNote(e.target.value)}
                    />
                    {statuses
                        .filter((status) => status.value !== 'pending')
                        .map((status) => (
                            <Button
                                key={status.value}
                                size="sm"
                                variant={
                                    check.status === status.value
                                        ? 'default'
                                        : 'outline'
                                }
                                onClick={() =>
                                    onSave(check, status.value, note)
                                }
                            >
                                {status.label}
                            </Button>
                        ))}
                </div>
            ) : (
                check.note && <p className="text-sm">{check.note}</p>
            )}
        </div>
    );
}
ApplicationShow.layout = {
    breadcrumbs: [{ title: 'Dossiers', href: '/scholarships/applications' }],
};
