import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    AlertCircle,
    ArrowLeft,
    ArrowRight,
    BookOpenCheck,
    Check,
    ChevronRight,
    FileCheck2,
    FileText,
    IdCard,
    Info,
    Inbox,
    Phone,
    UserRound,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { FormEvent } from 'react';
import { ErrorSummary, FormField } from '@/components/forms/form-kit';
import { Stepper } from '@/components/forms/stepper';
import type { Step, StepState } from '@/components/forms/stepper';
import { PageBody, PageHeader } from '@/components/page-header';
import { DdbLabel } from '@/components/scholarships/ddb-label';
import type { DdbLabelTexts } from '@/components/scholarships/ddb-label';
import {
    IdentityInput,
    missingLabels,
    sectionsFor,
    splitMissing,
    valuesOf,
} from '@/components/scholarships/reception-shared';
import type {
    ReceptionYear,
    ReceptionStudent,
} from '@/components/scholarships/reception-shared';
import { formatDate, StatusBadge } from '@/components/scholarships/shared';
import { ToneBadge } from '@/components/tone-badge';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Spinner } from '@/components/ui/spinner';

const sectionSteps: Record<string, { icon: LucideIcon; description: string }> =
    {
        Identité: { icon: UserRound, description: 'Nom, naissance, mère' },
        Identification: { icon: IdCard, description: 'CIN, code bourse' },
        Contact: { icon: Phone, description: 'Téléphone, email, adresse' },
        Baccalauréat: { icon: BookOpenCheck, description: 'Année et série' },
    };

const recordStatus: Record<string, string> = {
    active: 'Active',
    pending: 'En attente',
    inactive: 'Inactive',
};

export default function ReceptionStudentPage({
    student,
    year,
    nextDdb,
    ddbLabel,
    backUrl,
}: {
    student: ReceptionStudent;
    /** The current academic year, whose files are received. */
    year: ReceptionYear | null;
    nextDdb: string | null;
    /** Next DDB number split as on the paper label, with the label texts. */
    ddbLabel: {
        prefix: string;
        number: string;
        suffix: string;
        texts: DdbLabelTexts;
        qr: string;
    } | null;
    backUrl: string;
}) {
    const sections = sectionsFor(student);
    const fields = sections.flatMap((section) => section.fields);
    const labels = Object.fromEntries(
        fields.map((field) => [field.key, field.label]),
    );
    const readOnly = !student.canUpdate;
    const steps: (Step & { keys: string[] })[] = [
        ...sections.map((section) => ({
            id: section.title,
            title: section.title,
            description: sectionSteps[section.title]?.description,
            icon: sectionSteps[section.title]?.icon ?? UserRound,
            keys: section.fields.map((field) => field.key as string),
        })),
        {
            id: 'reception',
            title: 'Réception',
            description: 'N° DDB et observations',
            icon: Inbox,
            keys: [],
        },
    ];
    const receptionStep = steps.length - 1;
    // Opens on the first step with something to complete, or directly on the reception.
    const [current, setCurrent] = useState(() => {
        // Only a required field makes a step « to complete »; optional ones do not hold the agent.
        const required = splitMissing(student.missing).required;
        const index = steps.findIndex((step) =>
            step.keys.some((key) => required.includes(key)),
        );
        return index >= 0 && !readOnly ? index : receptionStep;
    });

    const identity = useForm<Record<string, string>>(valuesOf(student));
    const identityErrors = identity.errors as Record<
        string,
        string | undefined
    >;
    const [savedAt, setSavedAt] = useState<Date | null>(null);
    const stepWithError = (errors: Record<string, unknown>) =>
        steps.findIndex((step) => step.keys.some((key) => key in errors));
    const dataKey = JSON.stringify(identity.data);
    // Values of the last save refused by the server: not sent again until the agent changes them.
    const refused = useRef<string | null>(null);
    /**
     * Saves the file when it changed (same rules, locks and audit as the student form), then
     * runs `then`. `jump` opens the step at fault on error; never during an automatic save.
     */
    const save = (then?: () => void, jump = false) => {
        if (identity.processing) return;
        if (readOnly || !identity.isDirty) {
            then?.();
            return;
        }
        const sent = { ...identity.data };
        const sentKey = JSON.stringify(sent);
        identity.put(`/scholarships/reception/students/${student.uuid}`, {
            preserveState: true,
            preserveScroll: true,
            onSuccess: (page) => {
                // Stored (normalized) values become the reference; what was typed during the save is kept.
                const values = valuesOf(page.props.student as ReceptionStudent);
                identity.setDefaults(values);
                identity.setData((data) =>
                    Object.fromEntries(
                        Object.keys(values).map((key) => [
                            key,
                            data[key] !== sent[key] ? data[key] : values[key],
                        ]),
                    ),
                );
                refused.current = null;
                setSavedAt(new Date());
                then?.();
            },
            onError: (errors) => {
                refused.current = sentKey;
                if (!jump) return;
                const index = stepWithError(errors);
                if (index >= 0) setCurrent(index);
            },
        });
    };
    const saveRef = useRef(save);
    saveRef.current = save;
    // Automatic save, shortly after the agent stops typing.
    useEffect(() => {
        if (
            readOnly ||
            identity.processing ||
            !identity.isDirty ||
            dataKey === refused.current
        )
            return;
        const timer = window.setTimeout(() => saveRef.current(), 1000);
        return () => window.clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [dataKey, identity.processing]);

    // The first field of a step takes the focus when the step opens, never while typing.
    const firstField = useRef<HTMLElement | null>(null);
    const setFirstField = useRef((element: HTMLElement | null) => {
        firstField.current = element;
    }).current;
    useEffect(() => {
        firstField.current?.focus({ preventScroll: true });
    }, [current]);
    const go = (index: number) => {
        setCurrent(index);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    // Ctrl/Cmd + S saves at once; leaving before a change is saved asks first.
    const dirtyRef = useRef(false);
    dirtyRef.current = identity.isDirty;
    useEffect(() => {
        const onKey = (event: KeyboardEvent) => {
            if ((event.ctrlKey || event.metaKey) && event.key === 's') {
                event.preventDefault();
                saveRef.current(undefined, true);
            }
        };
        window.addEventListener('keydown', onKey);
        const stopBefore = router.on('before', (event) => {
            if (
                dirtyRef.current &&
                event.detail.visit.method === 'get' &&
                !window.confirm(
                    'Une modification de la fiche n’est pas encore enregistrée. Quitter quand même ?',
                )
            )
                event.preventDefault();
        });
        return () => {
            window.removeEventListener('keydown', onKey);
            stopBefore();
        };
    }, []);

    const reception = useForm({
        student_id: String(student.id),
        internal_notes: '',
    });
    const receptionErrors = reception.errors as Record<
        string,
        string | undefined
    >;
    // Internal notes only when the agent answers « Oui »; hidden notes are not sent.
    const [withNotes, setWithNotes] = useState(false);
    // A change not yet saved is saved first, then the reception is registered.
    const receive = (event: FormEvent) => {
        event.preventDefault();
        reception.transform((data) => ({
            ...data,
            internal_notes: withNotes ? data.internal_notes : '',
        }));
        save(() => reception.post('/scholarships/applications'), true);
    };
    const saveState = readOnly ? null : (
        <SaveState
            processing={identity.processing}
            dirty={identity.isDirty}
            failed={identity.hasErrors}
            savedAt={savedAt}
        />
    );

    const yearRecord = student.records.find(
        (record) => record.is_reception_year,
    );
    const fullName = `${student.last_name} ${student.first_names ?? ''}`.trim();
    const missingNow = splitMissing(
        student.missing.filter((key) => !identity.data[key]),
    );
    const groupsOf = (keys: string[]) =>
        steps
            .map((item, index) => ({
                index,
                title: item.title,
                keys: item.keys.filter((key) => keys.includes(key)),
            }))
            .filter((group) => group.keys.length > 0);
    const stateOf = (index: number): StepState => {
        if (steps[index].keys.some((key) => identityErrors[key]))
            return 'error';
        if (index === current) return 'current';
        if (index === receptionStep) return 'upcoming';
        return steps[index].keys.some((key) =>
            missingNow.required.includes(key),
        )
            ? 'upcoming'
            : 'done';
    };
    const step = steps[current];
    const section = sections[current];

    return (
        <PageBody>
            <Head title={fullName} />
            <Link
                href={backUrl}
                className="inline-flex items-center gap-1.5 text-sm font-medium text-muted-foreground hover:text-foreground"
            >
                <ArrowLeft className="size-4" aria-hidden="true" />
                Retour à la recherche
            </Link>
            <PageHeader
                icon={UserRound}
                title={fullName}
                actions={
                    missingNow.required.length + missingNow.optional.length >
                        0 && (
                        <MissingFields
                            required={groupsOf(missingNow.required)}
                            optional={groupsOf(missingNow.optional)}
                            onOpenStep={readOnly ? undefined : go}
                        />
                    )
                }
                description={
                    year
                        ? `Dossier de l’étudiant · Bourse nationale ${year.name}`
                        : 'Dossier de l’étudiant'
                }
                meta={
                    <div className="flex flex-wrap gap-2">
                        {student.is_active ? (
                            <ToneBadge tone="success">Fiche active</ToneBadge>
                        ) : (
                            <ToneBadge tone="danger">Fiche inactive</ToneBadge>
                        )}
                        {yearRecord ? (
                            <ToneBadge
                                tone={
                                    yearRecord.status === 'inactive'
                                        ? 'warning'
                                        : 'success'
                                }
                            >
                                {yearRecord.establishment} · {yearRecord.level}
                            </ToneBadge>
                        ) : (
                            year && (
                                <ToneBadge tone="warning">
                                    Non déclaré pour {year.name}
                                </ToneBadge>
                            )
                        )}
                        {missingNow.required.length > 0 ? (
                            <ToneBadge tone="warning">
                                {missingNow.required.length} information
                                {missingNow.required.length > 1 && 's'}{' '}
                                obligatoire
                                {missingNow.required.length > 1 && 's'} à
                                compléter
                            </ToneBadge>
                        ) : (
                            <ToneBadge tone="success">Fiche complète</ToneBadge>
                        )}
                        {student.yearApplication && (
                            <ToneBadge tone="info">Dossier déjà reçu</ToneBadge>
                        )}
                    </div>
                }
            />

            <Stepper
                steps={steps}
                current={current}
                stateOf={stateOf}
                canOpen={() => !identity.processing}
                onOpen={go}
                subtitleOf={(index) => {
                    const count = steps[index].keys.filter((key) =>
                        missingNow.required.includes(key),
                    ).length;
                    return count > 0 ? `${count} à compléter` : undefined;
                }}
            />

            <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
                {current < receptionStep && section ? (
                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            save(() => go(current + 1), true);
                        }}
                        noValidate
                        aria-labelledby="step-title"
                        className="surface min-w-0"
                    >
                        <div className="space-y-5 p-5 sm:p-6">
                            <div className="flex flex-wrap items-baseline justify-between gap-2">
                                <h2
                                    id="step-title"
                                    className="text-base font-bold text-foreground"
                                >
                                    {step.title}
                                </h2>
                                {readOnly && (
                                    <p className="text-xs text-muted-foreground">
                                        Lecture seule : modification non
                                        autorisée.
                                    </p>
                                )}
                            </div>
                            <ErrorSummary
                                errors={identityErrors}
                                labels={labels}
                            />
                            <div className="grid gap-4 sm:grid-cols-2">
                                {section.fields.map((field, index) => (
                                    <IdentityInput
                                        key={field.key}
                                        field={field}
                                        value={identity.data[field.key] ?? ''}
                                        error={identityErrors[field.key]}
                                        missing={missingNow.required.includes(
                                            field.key,
                                        )}
                                        readOnly={readOnly}
                                        inputRef={
                                            index === 0
                                                ? setFirstField
                                                : undefined
                                        }
                                        onChange={(value) =>
                                            identity.setData(field.key, value)
                                        }
                                    />
                                ))}
                            </div>
                        </div>
                        <div className="flex flex-col-reverse gap-2 border-t px-5 py-4 sm:flex-row sm:items-center sm:px-6">
                            <div className="flex flex-1 items-center gap-2">
                                {current > 0 && (
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        disabled={identity.processing}
                                        onClick={() => go(current - 1)}
                                    >
                                        <ArrowLeft aria-hidden="true" />
                                        Précédent
                                    </Button>
                                )}
                                {saveState}
                            </div>
                            <div className="flex flex-col-reverse gap-2 sm:flex-row">
                                <Button
                                    type="submit"
                                    disabled={identity.processing}
                                >
                                    Suivant
                                    <ArrowRight aria-hidden="true" />
                                </Button>
                            </div>
                        </div>
                    </form>
                ) : (
                    <section
                        aria-labelledby="step-title"
                        className="surface min-w-0 space-y-5 p-5 sm:p-6"
                    >
                        <h2
                            id="step-title"
                            className="text-base font-bold text-foreground"
                        >
                            Réception du dossier papier
                        </h2>

                        {student.yearApplication ? (
                            <div className="space-y-3">
                                <p className="text-sm text-muted-foreground">
                                    Une seule demande de bourse par année
                                    universitaire : le dossier de{' '}
                                    {year?.name ?? 'cette année'} a déjà été
                                    reçu. Une erreur se corrige depuis ce
                                    dossier.
                                </p>
                                <div className="flex flex-wrap gap-2">
                                    <Button asChild variant="outline">
                                        <Link
                                            href={`/scholarships/applications/${student.yearApplication.uuid}`}
                                        >
                                            <FileText aria-hidden="true" />
                                            Ouvrir le dossier N° DDB{' '}
                                            {student.yearApplication.reference}
                                        </Link>
                                    </Button>
                                </div>
                            </div>
                        ) : student.blockReason ? (
                            <p className="flex items-start gap-2 rounded-lg border border-danger/30 bg-danger-soft px-3.5 py-3 text-sm text-danger-foreground">
                                <AlertCircle
                                    className="mt-0.5 size-4 shrink-0"
                                    aria-hidden="true"
                                />
                                {student.blockReason}
                            </p>
                        ) : (
                            <form onSubmit={receive} className="space-y-5">
                                <ErrorSummary
                                    errors={receptionErrors}
                                    labels={{
                                        student_id: 'Étudiant',
                                        ddb_number: 'Numéro DDB',
                                        internal_notes: 'Observations',
                                    }}
                                />
                                <dl className="grid gap-x-6 gap-y-3 rounded-lg border bg-muted/30 p-4 text-sm sm:grid-cols-2">
                                    {[
                                        ['Étudiant', fullName],
                                        [
                                            'Né(e) le',
                                            formatDate(
                                                identity.data.birth_date ||
                                                    null,
                                            ),
                                        ],
                                        [
                                            'Situation',
                                            yearRecord
                                                ? `${yearRecord.establishment} · ${yearRecord.level}${yearRecord.program ? ` · ${yearRecord.program}` : ''}`
                                                : '—',
                                        ],
                                        [
                                            'Année universitaire',
                                            year?.name ?? '—',
                                        ],
                                    ].map(([label, value]) => (
                                        <div key={label} className="min-w-0">
                                            <dt className="text-xs text-muted-foreground">
                                                {label}
                                            </dt>
                                            <dd className="font-medium break-words text-foreground">
                                                {value}
                                            </dd>
                                        </div>
                                    ))}
                                </dl>
                                {yearRecord &&
                                    yearRecord.verification !== 'verified' && (
                                        <p className="text-sm text-warning-foreground">
                                            Situation pas encore confirmée par
                                            la liste de l’établissement : le
                                            dossier pourra être instruit, mais
                                            pas validé.
                                        </p>
                                    )}
                                <div className="space-y-3">
                                    <Label
                                        id="with-notes-label"
                                        className="text-sm font-semibold text-foreground"
                                    >
                                        Ajouter des observations internes ?
                                    </Label>
                                    <RadioGroup
                                        aria-labelledby="with-notes-label"
                                        value={withNotes ? 'yes' : 'no'}
                                        onValueChange={(value) =>
                                            setWithNotes(value === 'yes')
                                        }
                                        className="flex gap-3"
                                    >
                                        {[
                                            { value: 'yes', label: 'Oui' },
                                            { value: 'no', label: 'Non' },
                                        ].map((choice) => (
                                            <Label
                                                key={choice.value}
                                                htmlFor={`with-notes-${choice.value}`}
                                                className="flex h-10 min-w-24 cursor-pointer items-center gap-2.5 rounded-md border px-3.5 text-sm font-medium text-foreground transition-colors hover:bg-accent/50 has-[[data-state=checked]]:border-primary has-[[data-state=checked]]:bg-accent"
                                            >
                                                <RadioGroupItem
                                                    id={`with-notes-${choice.value}`}
                                                    value={choice.value}
                                                />
                                                {choice.label}
                                            </Label>
                                        ))}
                                    </RadioGroup>
                                    {withNotes && (
                                        <FormField
                                            id="internal_notes"
                                            label="Observations internes"
                                            hint="Visibles uniquement par la Scolarité Centrale."
                                            error={
                                                receptionErrors.internal_notes
                                            }
                                        >
                                            <textarea
                                                id="internal_notes"
                                                autoFocus
                                                className="min-h-20 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive"
                                                maxLength={2000}
                                                value={
                                                    reception.data
                                                        .internal_notes
                                                }
                                                onChange={(e) =>
                                                    reception.setData(
                                                        'internal_notes',
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                        </FormField>
                                    )}
                                </div>
                                <div className="flex flex-col-reverse gap-2 sm:flex-row sm:items-center">
                                    <div className="flex flex-1 flex-wrap items-center gap-2">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            onClick={() => go(current - 1)}
                                        >
                                            <ArrowLeft aria-hidden="true" />
                                            Précédent
                                        </Button>
                                        {saveState}
                                    </div>
                                    <Button
                                        type="submit"
                                        size="lg"
                                        className="h-12 text-base"
                                        disabled={
                                            reception.processing ||
                                            identity.processing
                                        }
                                    >
                                        {reception.processing ||
                                        identity.processing ? (
                                            <Spinner />
                                        ) : (
                                            <FileCheck2 aria-hidden="true" />
                                        )}
                                        {reception.processing
                                            ? 'Enregistrement…'
                                            : 'Enregistrer la réception'}
                                    </Button>
                                </div>
                            </form>
                        )}
                    </section>
                )}

                <aside className="min-w-0 space-y-4 lg:sticky lg:top-6">
                    {/* Next DDB number, in the shape of the paper label: only while a reception is possible. */}
                    {!student.yearApplication && !student.blockReason && (
                        <section
                            aria-labelledby="ddb-title"
                            className="surface space-y-3 p-5"
                        >
                            <h2
                                id="ddb-title"
                                className="text-sm font-bold text-foreground"
                            >
                                Étiquette de référence DDB
                            </h2>
                            {ddbLabel ? (
                                // The label (99.1 mm) is shown slightly reduced to fit the column.
                                <div className="[zoom:0.82]">
                                    <DdbLabel
                                        texts={ddbLabel.texts}
                                        academicYear={year?.name ?? ''}
                                        prefix={ddbLabel.prefix}
                                        number={ddbLabel.number}
                                        suffix={ddbLabel.suffix}
                                        qr={ddbLabel.qr}
                                        className="rounded-md"
                                    />
                                </div>
                            ) : (
                                <p className="font-mono text-2xl font-bold tracking-wider text-foreground">
                                    {nextDdb ?? '—'}
                                </p>
                            )}
                            <p className="text-xs text-muted-foreground">
                                Numéro indicatif, confirmé à l’enregistrement.
                            </p>
                        </section>
                    )}
                    <section
                        aria-labelledby="records-title"
                        className="surface space-y-3 p-5"
                    >
                        <h2
                            id="records-title"
                            className="text-sm font-bold text-foreground"
                        >
                            Situations
                        </h2>
                        {student.records.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Aucune situation déclarée.
                            </p>
                        ) : (
                            <ul className="divide-y">
                                {student.records.map((record, index) => (
                                    <li
                                        key={index}
                                        className="space-y-1 py-2.5 text-sm first:pt-0 last:pb-0"
                                    >
                                        <p className="font-semibold text-foreground">
                                            {record.academic_year}
                                            {record.is_reception_year && (
                                                <span className="ml-2 text-xs font-normal text-muted-foreground">
                                                    année en cours
                                                </span>
                                            )}
                                        </p>
                                        <p className="text-muted-foreground">
                                            {record.establishment} ·{' '}
                                            {record.level}
                                            {record.program &&
                                                ` · ${record.program}`}
                                        </p>
                                        <p className="flex flex-wrap gap-2 pt-1">
                                            <ToneBadge
                                                tone={
                                                    record.status === 'active'
                                                        ? 'success'
                                                        : record.status ===
                                                            'pending'
                                                          ? 'warning'
                                                          : 'neutral'
                                                }
                                            >
                                                {recordStatus[record.status] ??
                                                    record.status}
                                            </ToneBadge>
                                            <ToneBadge tone="neutral">
                                                {record.verification_label}
                                            </ToneBadge>
                                            {record.is_scholarship_holder && (
                                                <ToneBadge tone="info">
                                                    Boursier PNES
                                                </ToneBadge>
                                            )}
                                        </p>
                                    </li>
                                ))}
                            </ul>
                        )}
                        <p className="text-xs text-muted-foreground">
                            Déclarées par l’établissement.
                        </p>
                    </section>

                    {student.applications.length > 0 && (
                        <section
                            aria-labelledby="applications-title"
                            className="surface space-y-3 p-5"
                        >
                            <h2
                                id="applications-title"
                                className="text-sm font-bold text-foreground"
                            >
                                Dossiers de bourse
                            </h2>
                            <ul className="divide-y">
                                {student.applications.map((application) => (
                                    <li
                                        key={application.uuid}
                                        className="flex items-center gap-3 py-2.5 text-sm first:pt-0 last:pb-0"
                                    >
                                        <div className="min-w-0 flex-1">
                                            <Link
                                                href={`/scholarships/applications/${application.uuid}`}
                                                className="font-semibold text-primary hover:underline"
                                            >
                                                N° DDB {application.reference}
                                            </Link>
                                            <p className="text-xs text-muted-foreground">
                                                {application.academic_year} ·
                                                reçu le{' '}
                                                {formatDate(
                                                    application.received_at,
                                                )}
                                            </p>
                                        </div>
                                        <StatusBadge
                                            status={application.status}
                                            label={application.status_label}
                                        />
                                    </li>
                                ))}
                            </ul>
                        </section>
                    )}
                </aside>
            </div>
        </PageBody>
    );
}

type MissingGroup = { index: number; title: string; keys: string[] };

/**
 * Identity fields still empty: a button at the top right opening, on the right, the list by step.
 * Required fields are to complete (warning); optional ones are only listed, completed if provided.
 * A field opens its step (when the file may be completed).
 */
function MissingFields({
    required,
    optional,
    onOpenStep,
}: {
    required: MissingGroup[];
    optional: MissingGroup[];
    onOpenStep?: (index: number) => void;
}) {
    const [open, setOpen] = useState(false);
    const count = (groups: MissingGroup[]) =>
        groups.reduce((total, group) => total + group.keys.length, 0);
    const requiredCount = count(required);
    const optionalCount = count(optional);
    const list = (groups: MissingGroup[]) =>
        groups.map((group) => (
            <div key={group.index} className="space-y-1.5">
                <h4 className="text-xs font-medium text-muted-foreground">
                    {group.title}
                </h4>
                <ul className="divide-y rounded-lg border">
                    {group.keys.map((key) => (
                        <li key={key}>
                            {onOpenStep ? (
                                <button
                                    type="button"
                                    className="flex w-full items-center justify-between gap-3 px-3.5 py-2.5 text-left text-sm text-foreground hover:bg-accent/50"
                                    onClick={() => {
                                        setOpen(false);
                                        onOpenStep(group.index);
                                    }}
                                >
                                    {missingLabels[key] ?? key}
                                    <ChevronRight
                                        className="size-4 text-muted-foreground"
                                        aria-hidden="true"
                                    />
                                </button>
                            ) : (
                                <p className="px-3.5 py-2.5 text-sm text-foreground">
                                    {missingLabels[key] ?? key}
                                </p>
                            )}
                        </li>
                    ))}
                </ul>
            </div>
        ));

    return (
        <Sheet open={open} onOpenChange={setOpen}>
            <SheetTrigger asChild>
                {requiredCount > 0 ? (
                    <Button type="button" variant="outline">
                        <AlertCircle
                            className="text-warning-foreground"
                            aria-hidden="true"
                        />
                        À compléter
                        <span className="rounded-full bg-warning-soft px-1.5 text-xs font-semibold text-warning-foreground tabular-nums ring-1 ring-warning/30">
                            {requiredCount}
                        </span>
                    </Button>
                ) : (
                    <Button type="button" variant="outline">
                        <Info
                            className="text-muted-foreground"
                            aria-hidden="true"
                        />
                        Facultatif
                        <span className="rounded-full bg-muted px-1.5 text-xs font-semibold text-muted-foreground tabular-nums">
                            {optionalCount}
                        </span>
                    </Button>
                )}
            </SheetTrigger>
            <SheetContent className="w-full gap-0 overflow-y-auto sm:max-w-md">
                <SheetHeader className="border-b px-6 py-5 pr-12">
                    <SheetTitle className="text-lg">
                        {requiredCount > 0
                            ? 'Fiche incomplète'
                            : 'Informations facultatives'}
                    </SheetTitle>
                    <SheetDescription>
                        La réception reste possible ; complétez la fiche dès que
                        l’étudiant fournit l’information.
                    </SheetDescription>
                </SheetHeader>
                <div className="space-y-6 px-6 py-5">
                    {requiredCount > 0 && (
                        <section className="space-y-3">
                            <h3 className="flex items-center gap-2 text-sm font-semibold text-warning-foreground">
                                Obligatoires
                                <span className="text-xs font-normal">
                                    ({requiredCount})
                                </span>
                            </h3>
                            {list(required)}
                        </section>
                    )}
                    {optionalCount > 0 && (
                        <section className="space-y-3">
                            <h3 className="flex items-center gap-2 text-sm font-semibold text-muted-foreground">
                                Facultatives
                                <span className="text-xs font-normal">
                                    ({optionalCount}) · si l’étudiant les
                                    fournit
                                </span>
                            </h3>
                            {list(optional)}
                        </section>
                    )}
                </div>
            </SheetContent>
        </Sheet>
    );
}

/** State of the automatic save of the file, next to the step buttons. */
function SaveState({
    processing,
    dirty,
    failed,
    savedAt,
}: {
    processing: boolean;
    dirty: boolean;
    failed: boolean;
    savedAt: Date | null;
}) {
    const time = savedAt?.toLocaleTimeString('fr-FR', {
        hour: '2-digit',
        minute: '2-digit',
    });
    return (
        <p
            role="status"
            className="inline-flex items-center gap-1.5 text-xs text-muted-foreground"
        >
            {processing ? (
                <>
                    <Spinner className="size-3.5" />
                    Enregistrement…
                </>
            ) : failed ? (
                <span className="inline-flex items-center gap-1.5 text-danger-foreground">
                    <AlertCircle className="size-3.5" aria-hidden="true" />
                    Non enregistré : corrigez le champ signalé.
                </span>
            ) : dirty ? (
                'Modification en cours…'
            ) : savedAt ? (
                <>
                    <Check
                        className="size-3.5 text-success"
                        aria-hidden="true"
                    />
                    Enregistré à {time}
                </>
            ) : (
                'Enregistrement automatique'
            )}
        </p>
    );
}

ReceptionStudentPage.layout = {
    breadcrumbs: [
        { title: 'Réception des dossiers', href: '/scholarships/reception' },
    ],
};
