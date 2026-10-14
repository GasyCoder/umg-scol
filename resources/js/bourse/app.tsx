import { useEffect, useState } from 'react';
import type { FormEvent } from 'react';
import { createRoot } from 'react-dom/client';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { api } from './api';
import { ConfirmationSection } from './confirmation';
import type { ConfirmationState } from './confirmation';
import type { Session } from './api';
type Correction = {
    id: number;
    field: string;
    field_label: string;
    proposed_value: string;
    status: 'pending' | 'approved' | 'rejected' | 'cancelled';
    status_label: string;
    student_message: string | null;
    decision_reason: string | null;
    created_at: string;
    reviewed_at: string | null;
};
type FieldRow = {
    label: string;
    value: string | number | null;
    field?: string;
    hidden?: boolean;
    input?: 'date' | 'number' | 'email' | 'tel';
};
const statusVariant: Record<
    Correction['status'],
    'default' | 'secondary' | 'destructive' | 'outline'
> = {
    pending: 'default',
    approved: 'secondary',
    rejected: 'destructive',
    cancelled: 'outline',
};
type Student = {
    student_number: string | null;
    last_name: string;
    first_names: string | null;
    birth_date: string | null;
    birth_place: string | null;
    phone: string | null;
    email: string | null;
    address: string | null;
    bac_registration_number: string | null;
    bac_series: string | null;
    bac_year: number | null;
    bac_mention: string | null;
    records: {
        academic_year: string;
        establishment: string;
        level: string;
        program: string | null;
        status: string;
    }[];
};
function Portal() {
    const [session, setSession] = useState<Session | null>(null);
    const [student, setStudent] = useState<Student | null>(null);
    const [confirmation, setConfirmation] = useState<ConfirmationState | null>(
        null,
    );
    const [error, setError] = useState(
        new URLSearchParams(window.location.search).has('auth_error')
            ? 'Connexion Google impossible. Utilisez email + code ; vous pourrez ensuite associer Google depuis votre session.'
            : '',
    );
    const [message, setMessage] = useState('');
    const [busy, setBusy] = useState(false);
    const [email, setEmail] = useState('');
    const [code, setCode] = useState('');
    const [challenge, setChallenge] = useState('');
    const [type, setType] = useState('student_number');
    const [identifier, setIdentifier] = useState('');
    const [birth, setBirth] = useState('');
    const [corrections, setCorrections] = useState<Correction[]>([]);
    const [reporting, setReporting] = useState<FieldRow | null>(null);
    const [proposed, setProposed] = useState('');
    const [note, setNote] = useState('');
    const [dialogError, setDialogError] = useState('');
    async function refresh() {
        const next = await api<Session>('session');
        setSession(next);
        if (next.linked) {
            const result = await api<{
                student: Student;
                confirmation: ConfirmationState;
            }>('me');
            setStudent(result.student);
            setConfirmation(result.confirmation);
            const list = await api<{ corrections: Correction[] }>(
                'corrections',
            );
            setCorrections(list.corrections);
        } else {
            setStudent(null);
            setConfirmation(null);
            setCorrections([]);
        }
    }
    useEffect(() => {
        void refresh().catch((reason: unknown) =>
            setError(
                reason instanceof Error
                    ? reason.message
                    : 'Connexion indisponible.',
            ),
        );
    }, []);
    async function perform(action: () => Promise<void>) {
        setBusy(true);
        setError('');
        try {
            await action();
        } catch (reason) {
            setError(
                reason instanceof Error
                    ? reason.message
                    : 'Une erreur est survenue.',
            );
        } finally {
            setBusy(false);
        }
    }
    const requestCode = (event: FormEvent) => {
        event.preventDefault();
        void perform(async () => {
            const result = await api<{ challenge_id: string; message: string }>(
                'auth/otp/request',
                { email },
            );
            setChallenge(result.challenge_id);
            setMessage(result.message);
        });
    };
    const verify = (event: FormEvent) => {
        event.preventDefault();
        void perform(async () => {
            await api<Session>('auth/otp/verify', {
                challenge_id: challenge,
                code,
            });
            setCode('');
            setChallenge('');
            setEmail('');
            setMessage('');
            await refresh();
        });
    };
    const link = (event: FormEvent) => {
        event.preventDefault();
        void perform(async () => {
            const result = await api<{ linked: boolean; message: string }>(
                'link',
                { identifier_type: type, identifier, birth_date: birth },
            );
            setMessage(result.message);
            setIdentifier('');
            setBirth('');
            await refresh();
        });
    };
    const pendingFields = new Set(
        corrections
            .filter((item) => item.status === 'pending')
            .map((item) => item.field),
    );
    const openReport = (row: FieldRow) => {
        setReporting(row);
        setProposed('');
        setNote('');
        setDialogError('');
    };
    const submitCorrection = (event: FormEvent) => {
        event.preventDefault();
        if (!reporting?.field) return;
        setBusy(true);
        setDialogError('');
        api<{ message: string }>('corrections', {
            field: reporting.field,
            value: proposed,
            message: note,
        })
            .then(async (result) => {
                setReporting(null);
                setMessage(result.message);
                await refresh();
            })
            .catch((reason: unknown) =>
                setDialogError(
                    reason instanceof Error
                        ? reason.message
                        : 'La demande n’a pas pu être envoyée.',
                ),
            )
            .finally(() => setBusy(false));
    };
    const cancelCorrection = (id: number) =>
        void perform(async () => {
            const result = await api<{ message: string }>(
                `corrections/${id}/cancel`,
                {},
            );
            setMessage(result.message);
            await refresh();
        });
    return (
        <div className="min-h-screen">
            <header className="border-b">
                <div className="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-3 px-4 py-5">
                    <div>
                        <p className="font-semibold">Université de Mahajanga</p>
                        <p className="text-sm text-muted-foreground">
                            Portail étudiant
                        </p>
                    </div>
                    {session?.authenticated && (
                        <Button
                            variant="outline"
                            disabled={busy}
                            onClick={() =>
                                void perform(async () => {
                                    await api('logout', {});
                                    setStudent(null);
                                    setMessage('');
                                    await refresh();
                                })
                            }
                        >
                            Se déconnecter
                        </Button>
                    )}
                </div>
            </header>
            <main className="mx-auto max-w-5xl space-y-6 px-4 py-8">
                <h1 className="text-2xl font-semibold">
                    {student
                        ? 'Mes informations'
                        : session?.authenticated
                          ? 'Rattacher mon compte'
                          : 'Bienvenue sur le portail étudiant'}
                </h1>
                <p className="text-muted-foreground">
                    Ce portail permet aux étudiants déjà déclarés par leur
                    établissement de consulter leurs informations. La création
                    d’un compte numérique ne constitue pas une inscription
                    universitaire.
                </p>
                {error && (
                    <p
                        role="alert"
                        className="rounded-md border border-destructive p-3 text-destructive"
                    >
                        {error}
                    </p>
                )}
                {message && (
                    <p role="status" className="rounded-md border p-3">
                        {message}
                    </p>
                )}
                {!session && !error && <p>Chargement…</p>}
                {session && !session.authenticated && (
                    <section className="max-w-md space-y-5 rounded-lg border p-5">
                        <h2 className="text-lg font-semibold">
                            Connexion par email
                        </h2>
                        {!challenge ? (
                            <form onSubmit={requestCode} className="space-y-4">
                                <Label htmlFor="email">
                                    Votre adresse email
                                </Label>
                                <Input
                                    id="email"
                                    type="email"
                                    autoComplete="email"
                                    required
                                    maxLength={255}
                                    value={email}
                                    onChange={(e) => setEmail(e.target.value)}
                                />
                                <Button disabled={busy}>
                                    {busy ? 'Envoi…' : 'Recevoir un code'}
                                </Button>
                            </form>
                        ) : (
                            <form onSubmit={verify} className="space-y-4">
                                <p className="text-sm">
                                    Saisissez le code reçu par email. Il expire
                                    après dix minutes.
                                </p>
                                <Label htmlFor="code">
                                    Code à six chiffres
                                </Label>
                                <Input
                                    id="code"
                                    inputMode="numeric"
                                    autoComplete="one-time-code"
                                    pattern="[0-9]{6}"
                                    minLength={6}
                                    maxLength={6}
                                    required
                                    value={code}
                                    onChange={(e) => setCode(e.target.value)}
                                />
                                <div className="flex flex-wrap gap-3">
                                    <Button disabled={busy}>
                                        {busy
                                            ? 'Vérification…'
                                            : 'Se connecter'}
                                    </Button>
                                    <Button
                                        variant="outline"
                                        type="button"
                                        disabled={busy}
                                        onClick={() => {
                                            setChallenge('');
                                            setCode('');
                                            setMessage('');
                                        }}
                                    >
                                        Demander un nouveau code
                                    </Button>
                                </div>
                            </form>
                        )}
                        {session.google_enabled && (
                            <Button variant="outline" asChild>
                                <a href="/api/student/auth/google/redirect">
                                    Continuer avec Google
                                </a>
                            </Button>
                        )}
                    </section>
                )}
                {session?.authenticated && !session.linked && (
                    <section className="max-w-lg space-y-5 rounded-lg border p-5">
                        <p>
                            La vérification de votre email ne suffit pas à
                            prouver votre identité administrative. Fournissez
                            les informations ci-dessous. Une vérification par le
                            personnel peut être nécessaire.
                        </p>
                        <form onSubmit={link} className="space-y-4">
                            <fieldset className="flex flex-wrap gap-4">
                                <legend className="mb-2 font-medium">
                                    Identifiant officiel
                                </legend>
                                {[
                                    ['student_number', 'Matricule'],
                                    ['bac_registration_number', 'Numéro BAC'],
                                ].map(([value, label]) => (
                                    <Label
                                        key={value}
                                        className="flex items-center gap-2"
                                    >
                                        <input
                                            type="radio"
                                            name="identifier_type"
                                            value={value}
                                            checked={type === value}
                                            onChange={() => setType(value)}
                                        />
                                        {label}
                                    </Label>
                                ))}
                            </fieldset>
                            <Label htmlFor="identifier">
                                {type === 'student_number'
                                    ? 'Matricule'
                                    : 'Numéro BAC'}
                            </Label>
                            <Input
                                id="identifier"
                                maxLength={100}
                                required
                                value={identifier}
                                onChange={(e) => setIdentifier(e.target.value)}
                            />
                            <Label htmlFor="birth">Date de naissance</Label>
                            <Input
                                id="birth"
                                type="date"
                                required
                                value={birth}
                                onChange={(e) => setBirth(e.target.value)}
                            />
                            <Button disabled={busy}>
                                {busy
                                    ? 'Vérification…'
                                    : 'Demander le rattachement'}
                            </Button>
                        </form>
                    </section>
                )}
                {student && (
                    <>
                        <div className="grid gap-5 md:grid-cols-2">
                            {(
                                [
                                    {
                                        title: 'Identité',
                                        fields: [
                                            {
                                                label: 'Matricule',
                                                value: student.student_number,
                                            },
                                            {
                                                label: 'Nom',
                                                value: student.last_name,
                                                field: 'last_name',
                                            },
                                            {
                                                label: 'Prénoms',
                                                value: student.first_names,
                                                field: 'first_names',
                                            },
                                            {
                                                label: 'Date de naissance',
                                                value: student.birth_date,
                                                field: 'birth_date',
                                                input: 'date',
                                            },
                                            {
                                                label: 'Lieu de naissance',
                                                value: student.birth_place,
                                                field: 'birth_place',
                                            },
                                        ],
                                    },
                                    {
                                        title: 'Carte d’identité (CIN)',
                                        note: 'Pour votre sécurité, les données CIN ne sont pas affichées. Vous pouvez signaler une valeur à corriger.',
                                        fields: [
                                            {
                                                label: 'Numéro CIN',
                                                value: null,
                                                field: 'cin',
                                                hidden: true,
                                            },
                                            {
                                                label: 'Date de délivrance',
                                                value: null,
                                                field: 'cin_issued_at',
                                                hidden: true,
                                                input: 'date',
                                            },
                                            {
                                                label: 'Lieu de délivrance',
                                                value: null,
                                                field: 'cin_issued_place',
                                                hidden: true,
                                            },
                                        ],
                                    },
                                    {
                                        title: 'Contact',
                                        fields: [
                                            {
                                                label: 'Téléphone',
                                                value: student.phone,
                                                field: 'phone',
                                                input: 'tel',
                                            },
                                            {
                                                label: 'Email de contact',
                                                value: student.email,
                                                field: 'email',
                                                input: 'email',
                                            },
                                            {
                                                label: 'Adresse',
                                                value: student.address,
                                                field: 'address',
                                            },
                                        ],
                                    },
                                    {
                                        title: 'Baccalauréat',
                                        fields: [
                                            {
                                                label: 'Numéro BAC',
                                                value: student.bac_registration_number,
                                                field: 'bac_registration_number',
                                            },
                                            {
                                                label: 'Série',
                                                value: student.bac_series,
                                                field: 'bac_series',
                                            },
                                            {
                                                label: 'Année',
                                                value: student.bac_year,
                                                field: 'bac_year',
                                                input: 'number',
                                            },
                                            {
                                                label: 'Mention',
                                                value: student.bac_mention,
                                                field: 'bac_mention',
                                            },
                                        ],
                                    },
                                ] as {
                                    title: string;
                                    note?: string;
                                    fields: FieldRow[];
                                }[]
                            ).map((section) => (
                                <section
                                    key={section.title}
                                    className="space-y-3 rounded-lg border p-5"
                                >
                                    <h2 className="font-semibold">
                                        {section.title}
                                    </h2>
                                    {section.note && (
                                        <p className="text-sm text-muted-foreground">
                                            {section.note}
                                        </p>
                                    )}
                                    <dl className="space-y-3">
                                        {section.fields.map((row) => (
                                            <div
                                                key={row.label}
                                                className="grid grid-cols-2 items-start gap-3"
                                            >
                                                <dt className="text-muted-foreground">
                                                    {row.label}
                                                </dt>
                                                <dd className="space-y-1 break-words">
                                                    <span className="block">
                                                        {row.hidden
                                                            ? 'Non affiché'
                                                            : (row.value ??
                                                              'Non renseigné')}
                                                    </span>
                                                    {row.field &&
                                                        (pendingFields.has(
                                                            row.field,
                                                        ) ? (
                                                            <span className="text-xs text-muted-foreground">
                                                                Demande en
                                                                attente
                                                            </span>
                                                        ) : (
                                                            <button
                                                                type="button"
                                                                className="text-xs underline underline-offset-4"
                                                                onClick={() =>
                                                                    openReport(
                                                                        row,
                                                                    )
                                                                }
                                                            >
                                                                Signaler une
                                                                erreur
                                                            </button>
                                                        ))}
                                                </dd>
                                            </div>
                                        ))}
                                    </dl>
                                </section>
                            ))}
                        </div>
                        <section className="space-y-4">
                            <h2 className="text-lg font-semibold">
                                Situation universitaire
                            </h2>
                            {student.records.length === 0 ? (
                                <p>Aucune déclaration annuelle disponible.</p>
                            ) : (
                                student.records.map((record, index) => (
                                    <article
                                        key={index}
                                        className="flex flex-wrap items-center justify-between gap-3 rounded-lg border p-4"
                                    >
                                        <div>
                                            <p className="font-medium">
                                                {record.academic_year} —{' '}
                                                {record.establishment}
                                            </p>
                                            <p>
                                                {record.level} —{' '}
                                                {record.program ??
                                                    'Parcours non renseigné'}
                                            </p>
                                        </div>
                                        <Badge variant="secondary">
                                            {record.status === 'active'
                                                ? 'Déclaration active'
                                                : 'Déclaration inactive'}
                                        </Badge>
                                    </article>
                                ))
                            )}
                        </section>
                        <p className="text-sm text-muted-foreground">
                            Pour une erreur concernant votre niveau, parcours ou
                            établissement, contactez votre établissement.
                        </p>
                        {confirmation && (
                            <ConfirmationSection
                                state={confirmation}
                                onDone={async (result) => {
                                    setMessage(result);
                                    await refresh();
                                }}
                            />
                        )}
                        <section id="mes-demandes" className="space-y-4">
                            <h2 className="text-lg font-semibold">
                                Mes demandes de correction
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                Vos informations officielles ne changent
                                qu’après validation par le personnel autorisé.
                            </p>
                            {corrections.length === 0 ? (
                                <p>Aucune demande pour le moment.</p>
                            ) : (
                                corrections.map((item) => (
                                    <article
                                        key={item.id}
                                        className="space-y-2 rounded-lg border p-4"
                                    >
                                        <div className="flex flex-wrap items-center justify-between gap-3">
                                            <p className="font-medium">
                                                {item.field_label}
                                            </p>
                                            <Badge
                                                variant={
                                                    statusVariant[item.status]
                                                }
                                            >
                                                {item.status_label}
                                            </Badge>
                                        </div>
                                        <p className="break-words">
                                            Valeur proposée :{' '}
                                            {item.proposed_value}
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            Envoyée le{' '}
                                            {new Date(
                                                item.created_at,
                                            ).toLocaleDateString('fr-FR')}
                                            {item.reviewed_at &&
                                                ` · décision le ${new Date(item.reviewed_at).toLocaleDateString('fr-FR')}`}
                                        </p>
                                        {item.decision_reason && (
                                            <p className="text-sm">
                                                Motif : {item.decision_reason}
                                            </p>
                                        )}
                                        {item.status === 'pending' && (
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                disabled={busy}
                                                onClick={() =>
                                                    cancelCorrection(item.id)
                                                }
                                            >
                                                Annuler la demande
                                            </Button>
                                        )}
                                    </article>
                                ))
                            )}
                        </section>
                        <Dialog
                            open={reporting !== null}
                            onOpenChange={(open) => {
                                if (!open && !busy) setReporting(null);
                            }}
                        >
                            <DialogContent>
                                <form
                                    onSubmit={submitCorrection}
                                    className="space-y-4"
                                >
                                    <DialogHeader>
                                        <DialogTitle>
                                            Signaler une erreur :{' '}
                                            {reporting?.label}
                                        </DialogTitle>
                                        <DialogDescription>
                                            Votre demande sera examinée par le
                                            personnel. La fiche officielle ne
                                            change qu’après approbation.
                                        </DialogDescription>
                                    </DialogHeader>
                                    {reporting && !reporting.hidden && (
                                        <p className="text-sm">
                                            Valeur actuelle :{' '}
                                            {reporting.value ??
                                                'Non renseignée'}
                                        </p>
                                    )}
                                    <div className="space-y-2">
                                        <Label htmlFor="proposed">
                                            Valeur correcte
                                        </Label>
                                        <Input
                                            id="proposed"
                                            type={reporting?.input ?? 'text'}
                                            required
                                            maxLength={2000}
                                            value={proposed}
                                            onChange={(e) =>
                                                setProposed(e.target.value)
                                            }
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="note">
                                            Message (facultatif)
                                        </Label>
                                        <textarea
                                            id="note"
                                            className="min-h-20 w-full rounded-md border bg-transparent p-2 text-sm"
                                            maxLength={500}
                                            value={note}
                                            onChange={(e) =>
                                                setNote(e.target.value)
                                            }
                                        />
                                    </div>
                                    {dialogError && (
                                        <p
                                            role="alert"
                                            className="text-sm text-destructive"
                                        >
                                            {dialogError}
                                        </p>
                                    )}
                                    <DialogFooter>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            disabled={busy}
                                            onClick={() => setReporting(null)}
                                        >
                                            Annuler
                                        </Button>
                                        <Button disabled={busy}>
                                            {busy
                                                ? 'Envoi…'
                                                : 'Envoyer la demande'}
                                        </Button>
                                    </DialogFooter>
                                </form>
                            </DialogContent>
                        </Dialog>
                    </>
                )}
                {session?.authenticated && session.google_enabled && (
                    <Button variant="outline" asChild>
                        <a href="/api/student/auth/google/redirect">
                            Associer ou utiliser Google
                        </a>
                    </Button>
                )}
            </main>
        </div>
    );
}
const element = document.getElementById('bourse-root');
if (element) createRoot(element).render(<Portal />);
