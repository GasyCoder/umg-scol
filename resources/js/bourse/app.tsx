import { useEffect, useState } from 'react';
import type { FormEvent } from 'react';
import { createRoot } from 'react-dom/client';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { api } from './api';
import type { Session } from './api';
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
    async function refresh() {
        const next = await api<Session>('session');
        setSession(next);
        if (next.linked) {
            const result = await api<{ student: Student }>('me');
            setStudent(result.student);
        } else setStudent(null);
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
                            {[
                                {
                                    title: 'Identité',
                                    fields: [
                                        ['Matricule', student.student_number],
                                        ['Nom', student.last_name],
                                        ['Prénoms', student.first_names],
                                        ['Naissance', student.birth_date],
                                        [
                                            'Lieu de naissance',
                                            student.birth_place,
                                        ],
                                    ],
                                },
                                {
                                    title: 'Contact',
                                    fields: [
                                        ['Téléphone', student.phone],
                                        ['Email', student.email],
                                        ['Adresse', student.address],
                                    ],
                                },
                                {
                                    title: 'Baccalauréat',
                                    fields: [
                                        [
                                            'Numéro BAC',
                                            student.bac_registration_number,
                                        ],
                                        ['Série', student.bac_series],
                                        ['Année', student.bac_year],
                                        ['Mention', student.bac_mention],
                                    ],
                                },
                            ].map((section) => (
                                <section
                                    key={section.title}
                                    className="space-y-3 rounded-lg border p-5"
                                >
                                    <h2 className="font-semibold">
                                        {section.title}
                                    </h2>
                                    <dl className="space-y-2">
                                        {section.fields.map(
                                            ([label, value]) => (
                                                <div
                                                    key={label}
                                                    className="grid grid-cols-2 gap-3"
                                                >
                                                    <dt className="text-muted-foreground">
                                                        {label}
                                                    </dt>
                                                    <dd className="break-words">
                                                        {value ??
                                                            'Non renseigné'}
                                                    </dd>
                                                </div>
                                            ),
                                        )}
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
                            Les informations officielles sont consultables en
                            lecture seule.
                        </p>
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
