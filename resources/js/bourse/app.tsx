import {
    AlertCircle,
    ArrowLeft,
    BadgeCheck,
    CalendarCheck,
    CheckCircle2,
    Contact,
    EyeOff,
    FilePenLine,
    Flag,
    GraduationCap,
    IdCard,
    Info,
    Lock,
    LogOut,
    Mail,
    School,
    Send,
    UserRound,
    XCircle,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useEffect, useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import { REGEXP_ONLY_DIGITS } from 'input-otp';
import { createRoot } from 'react-dom/client';
import { Toaster, toast } from 'sonner';
import type { Root } from 'react-dom/client';
import { BrandMark } from '@/components/brand-mark';
import { EmptyState } from '@/components/empty-state';
import {
    ScholarshipIllustration,
    StudentsIllustration,
} from '@/components/illustrations';
import { ThemeToggle } from '@/components/theme-toggle';
import { ToneBadge, toneFor } from '@/components/tone-badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { initializeTheme } from '@/hooks/use-appearance';
import { cn } from '@/lib/utils';
import { api } from './api';
import { ConfirmationBadge, ConfirmationSection } from './confirmation';
import type { ConfirmationState } from './confirmation';
import type { Session } from './api';

/** Platform identity injected by bourse.blade.php (settings → Plateforme on the internal portal). */
type PortalBranding = {
    institution_name: string;
    bourse_name: string;
    support_email: string | null;
    logo_url: string | null;
};
const branding: PortalBranding = (() => {
    const fallback = {
        institution_name: 'Université de Mahajanga',
        bourse_name: 'UMG Bourse',
        support_email: null,
        logo_url: null,
    };
    try {
        const raw =
            typeof document === 'undefined'
                ? null
                : document.getElementById('bourse-root')?.dataset.branding;
        return raw
            ? { ...fallback, ...(JSON.parse(raw) as Partial<PortalBranding>) }
            : fallback;
    } catch {
        return fallback;
    }
})();
type Correction = {
    uuid: string;
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
type FieldOption = { value: string; label: string };
type FieldRow = {
    label: string;
    value: string | number | null;
    field?: string;
    hidden?: boolean;
    input?: 'date' | 'number' | 'email' | 'tel';
    /** Closed list of the establishment file: the proposed value is picked, not typed. */
    options?: FieldOption[];
};
type Student = {
    last_name: string;
    first_names: string | null;
    sex: 'M' | 'F' | null;
    birth_date: string | null;
    mother_name: string | null;
    nationality: string | null;
    phone: string | null;
    email: string | null;
    address: string | null;
    bac_series: string | null;
    bac_year: number | null;
    records: {
        academic_year: string;
        establishment: string;
        level: string;
        mention: string | null;
        program: string | null;
        repeat_code: string | null;
        status: string;
    }[];
};
type InfoSection = {
    title: string;
    icon: LucideIcon;
    note?: string;
    fields: FieldRow[];
};

/** Legal reminder shown on every screen: the portal is not a registration service. */
const PORTAL_NOTICE =
    'Ce portail permet aux étudiants déjà déclarés par leur établissement de consulter leurs informations. La création d’un compte numérique ne constitue pas une inscription universitaire.';

/** Same closed lists as StudentReferenceLists and the StudentSex enum (backend). */
const SEX_OPTIONS: FieldOption[] = [
    { value: 'M', label: 'Masculin' },
    { value: 'F', label: 'Féminin' },
];
const listOptions = (values: string[]): FieldOption[] =>
    values.map((value) => ({ value, label: value }));
const NATIONALITY_OPTIONS = listOptions([
    'Malagasy',
    'Comorien',
    'Africain',
    'Européen',
    'Asiatique',
    'Mauricien',
    'Autres',
]);
const BAC_SERIES_OPTIONS = listOptions([
    'A1',
    'A2',
    'C',
    'D',
    'L',
    'S',
    'OSE',
    'Tech. Génie civil',
    'Tech. Industriel',
    'Tech. Tertiaire',
    'Tech. Agricole',
    'Technologique',
    'Autres',
]);
/** Fields whose stored code is shown through a label (sex M/F). */
const FIELD_OPTIONS: Record<string, FieldOption[]> = { sex: SEX_OPTIONS };

type IdentifierType = 'cin' | 'phone' | 'email' | 'last_name';
/** Ways to name one's record when linking the account (with the birth date). */
const identifierOptions: Record<
    IdentifierType,
    {
        choice: string;
        label: string;
        placeholder: string;
        hint: string;
        type: string;
        inputMode: 'numeric' | 'tel' | 'email' | 'text';
    }
> = {
    cin: {
        choice: 'Numéro CIN',
        placeholder: 'Ex. : 413 012 049 888',
        label: 'Votre numéro CIN',
        hint: 'Le numéro de votre carte d’identité nationale, tel que déclaré par votre établissement.',
        type: 'text',
        inputMode: 'numeric',
    },
    phone: {
        choice: 'Numéro de téléphone',
        placeholder: 'Ex. : 034 12 345 67',
        label: 'Votre numéro de téléphone',
        hint: 'Le numéro donné à votre établissement lors de votre inscription.',
        type: 'tel',
        inputMode: 'tel',
    },
    email: {
        choice: 'Adresse mail',
        placeholder: 'Ex. : prenom.nom@gmail.com',
        label: 'Votre adresse mail',
        hint: 'L’adresse mail donnée à votre établissement (elle peut différer de celle de ce compte).',
        type: 'email',
        inputMode: 'email',
    },
    last_name: {
        choice: 'Nom de famille',
        placeholder: 'Ex. : RAKOTO',
        label: 'Votre nom de famille',
        hint: 'Tel que déclaré par votre établissement.',
        type: 'text',
        inputMode: 'text',
    },
};
function optionLabel(field: string, value: string): string {
    return (
        FIELD_OPTIONS[field]?.find((option) => option.value === value)?.label ??
        value
    );
}

function infoSections(student: Student): InfoSection[] {
    return [
        {
            title: 'Identité',
            icon: UserRound,
            fields: [
                { label: 'Nom', value: student.last_name, field: 'last_name' },
                {
                    label: 'Prénoms',
                    value: student.first_names,
                    field: 'first_names',
                },
                {
                    label: 'Sexe',
                    value: student.sex && optionLabel('sex', student.sex),
                    field: 'sex',
                    options: SEX_OPTIONS,
                },
                {
                    label: 'Date de naissance',
                    value: student.birth_date,
                    field: 'birth_date',
                    input: 'date',
                },
                {
                    label: 'Nom et prénoms de la mère',
                    value: student.mother_name,
                    field: 'mother_name',
                },
                {
                    label: 'Nationalité',
                    value: student.nationality,
                    field: 'nationality',
                    options: NATIONALITY_OPTIONS,
                },
            ],
        },
        {
            title: 'Carte d’identité (CIN)',
            icon: IdCard,
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
            icon: Contact,
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
                { label: 'Adresse', value: student.address, field: 'address' },
            ],
        },
        {
            title: 'Baccalauréat',
            icon: GraduationCap,
            fields: [
                {
                    label: 'Série',
                    value: student.bac_series,
                    field: 'bac_series',
                    options: BAC_SERIES_OPTIONS,
                },
                {
                    label: 'Année',
                    value: student.bac_year,
                    field: 'bac_year',
                    input: 'number',
                },
            ],
        },
    ];
}

function formatDate(value: string): string {
    return new Date(value).toLocaleDateString('fr-FR');
}

const noticeTones = {
    danger: 'border-danger/30 bg-danger-soft text-danger-foreground',
    success: 'border-success/30 bg-success-soft text-success-foreground',
    info: 'border-info/30 bg-info-soft text-info-foreground',
};

/** Inline message on a soft tone surface, with an icon (never colour alone). */
function Notice({
    tone,
    icon: Icon,
    role,
    children,
    className,
}: {
    tone: keyof typeof noticeTones;
    icon: LucideIcon;
    role?: 'alert' | 'status';
    children: ReactNode;
    className?: string;
}) {
    return (
        <div
            role={role}
            className={cn(
                'flex items-start gap-3 rounded-xl border px-4 py-3 text-sm',
                noticeTones[tone],
                className,
            )}
        >
            <Icon className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
            <div className="min-w-0 flex-1">{children}</div>
        </div>
    );
}

/** Card header used by every portal section: icon tile, title, optional description and meta. */
function SectionHeader({
    id,
    icon: Icon,
    title,
    description,
    meta,
}: {
    id: string;
    icon: LucideIcon;
    title: string;
    description?: string;
    meta?: ReactNode;
}) {
    return (
        <header className="flex flex-wrap items-start gap-x-3 gap-y-2 border-b bg-muted/30 px-5 py-4 sm:px-6">
            <span
                className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-accent text-primary"
                aria-hidden="true"
            >
                <Icon className="size-[18px]" />
            </span>
            <div className="min-w-0 flex-1">
                <h2
                    id={id}
                    className="font-heading text-base leading-9 font-bold text-foreground"
                >
                    {title}
                </h2>
                {description && (
                    <p className="-mt-1 text-sm text-muted-foreground">
                        {description}
                    </p>
                )}
            </div>
            {meta && (
                <div className="order-last basis-full pl-12 sm:order-none sm:basis-auto sm:pt-1.5 sm:pl-0">
                    {meta}
                </div>
            )}
        </header>
    );
}

const fieldClass = 'h-11 bg-card';

function Portal() {
    const [session, setSession] = useState<Session | null>(null);
    const [student, setStudent] = useState<Student | null>(null);
    const [confirmation, setConfirmation] = useState<ConfirmationState | null>(
        null,
    );
    const [busy, setBusy] = useState(false);
    // The first load failed: no spinner forever, a way to try again.
    const [loadFailed, setLoadFailed] = useState(false);
    const [email, setEmail] = useState('');
    const [code, setCode] = useState('');
    const [challenge, setChallenge] = useState('');
    // Seconds before another code can be asked for the same address.
    const [resendIn, setResendIn] = useState(0);
    useEffect(() => {
        if (resendIn <= 0) return;
        const timer = window.setTimeout(() => setResendIn(resendIn - 1), 1000);
        return () => window.clearTimeout(timer);
    }, [resendIn]);
    // Not every student has a CIN: phone, email or last name declared by the establishment work too.
    const [identifierType, setIdentifierType] = useState<IdentifierType>('cin');
    const [identifier, setIdentifier] = useState('');
    // Asked with the last name only, which alone is too easy to know.
    const [motherName, setMotherName] = useState('');
    // Step of the "find my record" wizard: birth date, which information, the value.
    const [linkStep, setLinkStep] = useState<0 | 1 | 2>(0);
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
        // Errors and confirmations of the portal are toasts, at the bottom of the screen.
        if (new URLSearchParams(window.location.search).has('auth_error')) {
            toast.error(
                'Connexion Google impossible. Utilisez email + code ; vous pourrez ensuite associer Google depuis votre session.',
            );
        }
        void refresh().catch((reason: unknown) => {
            setLoadFailed(true);
            toast.error(
                reason instanceof Error
                    ? reason.message
                    : 'Connexion indisponible.',
            );
        });
    }, []);
    async function perform(action: () => Promise<void>) {
        setBusy(true);
        try {
            await action();
        } catch (reason) {
            toast.error(
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
            toast.success(result.message);
            setCode('');
            setResendIn(60);
        });
    };
    const submitCode = (value: string) =>
        perform(async () => {
            try {
                await api<Session>('auth/otp/verify', {
                    challenge_id: challenge,
                    code: value,
                });
            } catch (reason) {
                // Wrong or expired code: empty boxes, ready to type again.
                setCode('');
                throw reason;
            }
            setCode('');
            setChallenge('');
            setEmail('');
            await refresh();
        });
    const verify = (event: FormEvent) => {
        event.preventDefault();
        void submitCode(code);
    };
    const today = new Date().toISOString().slice(0, 10);
    const birthValid = birth !== '' && birth <= today && birth >= '1900-01-01';
    // Enter key and buttons move through the wizard; only the last step sends the request.
    const link = (event: FormEvent) => {
        event.preventDefault();
        if (linkStep === 0) {
            if (birthValid) setLinkStep(1);
            return;
        }
        if (linkStep === 1) {
            setLinkStep(2);
            return;
        }
        void perform(async () => {
            const result = await api<{ linked: boolean; message: string }>(
                'link',
                {
                    identifier_type: identifierType,
                    identifier,
                    birth_date: birth,
                    ...(identifierType === 'last_name'
                        ? { mother_name: motherName }
                        : {}),
                },
            );
            toast.success(result.message);
            setIdentifier('');
            setMotherName('');
            setBirth('');
            setLinkStep(0);
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
                toast.success(result.message);
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
    const cancelCorrection = (uuid: string) =>
        void perform(async () => {
            const result = await api<{ message: string }>(
                `corrections/${uuid}/cancel`,
                {},
            );
            toast.success(result.message);
            await refresh();
        });
    const firstName = student
        ? (student.first_names ?? student.last_name).split(' ')[0]
        : '';

    return (
        <div className="flex min-h-dvh flex-col">
            <header className="sticky top-0 z-40 border-b bg-card/90 backdrop-blur supports-[backdrop-filter]:bg-card/75">
                <div className="mx-auto flex h-16 max-w-screen-2xl items-center justify-between gap-3 px-4 sm:px-6">
                    <a
                        href="/"
                        className="flex min-w-0 items-center gap-3 rounded-lg focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    >
                        <BrandMark
                            logoUrl={branding.logo_url}
                            className={cn(
                                'size-10 shadow-sm',
                                branding.logo_url
                                    ? 'border'
                                    : 'bg-sidebar ring-sidebar-border',
                            )}
                        />
                        <span className="min-w-0 leading-tight">
                            <span className="block font-heading text-base font-extrabold tracking-tight text-foreground">
                                {branding.bourse_name}
                            </span>
                            <span className="block truncate text-xs text-muted-foreground">
                                Portail étudiant · {branding.institution_name}
                            </span>
                        </span>
                    </a>
                    <div className="flex shrink-0 items-center gap-1.5">
                        <ThemeToggle />
                        {session?.authenticated && (
                            <Button
                                variant="outline"
                                className="h-10 rounded-full px-3 sm:px-4"
                                disabled={busy}
                                aria-label="Se déconnecter"
                                onClick={() =>
                                    void perform(async () => {
                                        await api('logout', {});
                                        setStudent(null);
                                        await refresh();
                                    })
                                }
                            >
                                <LogOut aria-hidden="true" />
                                <span className="hidden sm:inline">
                                    Se déconnecter
                                </span>
                            </Button>
                        )}
                    </div>
                </div>
            </header>

            <main className="mx-auto w-full max-w-screen-2xl flex-1 px-4 pt-6 pb-16 sm:px-6 sm:pt-10">
                {!session && (
                    <div className="mx-auto max-w-lg space-y-4">
                        {loadFailed ? (
                            <div className="surface flex flex-col items-center gap-3 px-6 py-12 text-sm text-muted-foreground">
                                Le portail est momentanément indisponible.
                                <Button
                                    variant="outline"
                                    onClick={() => window.location.reload()}
                                >
                                    Réessayer
                                </Button>
                            </div>
                        ) : (
                            <div
                                role="status"
                                className="surface flex flex-col items-center gap-3 px-6 py-12 text-sm text-muted-foreground"
                            >
                                <Spinner className="size-6 text-primary" />
                                Chargement…
                            </div>
                        )}
                    </div>
                )}

                {session && !session.authenticated && (
                    <div className="mx-auto max-w-lg space-y-4">
                        <div className="surface overflow-hidden rounded-2xl">
                            <div className="relative border-b bg-muted/40 px-6 pt-6 pb-5 text-center">
                                <div
                                    className="bg-grid absolute inset-0 text-primary opacity-60"
                                    aria-hidden="true"
                                />
                                <ScholarshipIllustration className="relative mx-auto h-28 w-auto" />
                                <h1 className="relative mt-2 font-heading text-2xl font-extrabold tracking-tight text-foreground">
                                    Bienvenue sur le portail étudiant
                                </h1>
                                <p className="relative mt-1 text-sm text-muted-foreground">
                                    Consultez vos informations et signalez une
                                    erreur en quelques minutes.
                                </p>
                            </div>

                            <div className="space-y-5 p-6">
                                <ol
                                    className="flex items-center gap-2 text-xs font-semibold"
                                    aria-label="Étapes de connexion"
                                >
                                    {['Email', 'Code reçu'].map(
                                        (label, index) => {
                                            const current =
                                                index === (challenge ? 1 : 0);
                                            const done =
                                                index === 0 && !!challenge;

                                            return (
                                                <li
                                                    key={label}
                                                    aria-current={
                                                        current
                                                            ? 'step'
                                                            : undefined
                                                    }
                                                    className={cn(
                                                        'flex flex-1 items-center gap-2 rounded-full px-1 py-1 pr-3',
                                                        current
                                                            ? 'bg-accent text-accent-foreground'
                                                            : 'text-muted-foreground',
                                                    )}
                                                >
                                                    <span
                                                        className={cn(
                                                            'flex size-6 shrink-0 items-center justify-center rounded-full',
                                                            current || done
                                                                ? 'bg-primary text-primary-foreground'
                                                                : 'bg-muted',
                                                        )}
                                                        aria-hidden="true"
                                                    >
                                                        {done ? (
                                                            <CheckCircle2 className="size-3.5" />
                                                        ) : (
                                                            index + 1
                                                        )}
                                                    </span>
                                                    {label}
                                                </li>
                                            );
                                        },
                                    )}
                                </ol>

                                <h2 className="sr-only">Connexion par email</h2>
                                {!challenge ? (
                                    <form
                                        onSubmit={requestCode}
                                        className="space-y-4"
                                    >
                                        <div className="space-y-2">
                                            <Label htmlFor="email">
                                                Votre adresse email
                                            </Label>
                                            <div className="relative">
                                                <Mail
                                                    className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                                    aria-hidden="true"
                                                />
                                                <Input
                                                    id="email"
                                                    type="email"
                                                    autoComplete="email"
                                                    required
                                                    maxLength={255}
                                                    placeholder="vous@exemple.mg"
                                                    className={cn(
                                                        fieldClass,
                                                        'pl-9',
                                                    )}
                                                    value={email}
                                                    onChange={(e) =>
                                                        setEmail(e.target.value)
                                                    }
                                                />
                                            </div>
                                            <p className="text-xs text-muted-foreground">
                                                Nous vous envoyons un code de
                                                connexion à usage unique.
                                            </p>
                                        </div>
                                        <Button
                                            disabled={busy}
                                            className="h-11 w-full text-base font-semibold"
                                        >
                                            {busy ? (
                                                <Spinner />
                                            ) : (
                                                <Send aria-hidden="true" />
                                            )}
                                            {busy
                                                ? 'Envoi du code…'
                                                : 'Recevoir un code'}
                                        </Button>
                                    </form>
                                ) : (
                                    <form
                                        onSubmit={verify}
                                        className="space-y-4"
                                    >
                                        <p className="text-sm text-muted-foreground">
                                            Saisissez le code reçu par email
                                            {email && (
                                                <>
                                                    {' '}
                                                    à{' '}
                                                    <span className="font-semibold break-words text-foreground">
                                                        {email}
                                                    </span>
                                                </>
                                            )}
                                            . Il expire après dix minutes.
                                        </p>
                                        <div className="space-y-2">
                                            <Label htmlFor="code">
                                                Code à six chiffres
                                            </Label>
                                            <InputOTP
                                                id="code"
                                                maxLength={6}
                                                pattern={REGEXP_ONLY_DIGITS}
                                                inputMode="numeric"
                                                autoComplete="one-time-code"
                                                autoFocus
                                                disabled={busy}
                                                value={code}
                                                onChange={setCode}
                                                // Six digits typed or pasted: sign in at once.
                                                onComplete={(value: string) =>
                                                    void submitCode(value)
                                                }
                                                containerClassName="justify-center"
                                            >
                                                <InputOTPGroup className="gap-2 sm:gap-3">
                                                    {[0, 1, 2, 3, 4, 5].map(
                                                        (index) => (
                                                            <InputOTPSlot
                                                                key={index}
                                                                index={index}
                                                                className="size-12 rounded-xl border-2 border-input bg-card font-heading text-2xl font-bold text-foreground shadow-none first:rounded-xl first:border-l-2 last:rounded-xl data-[active=true]:border-primary data-[active=true]:ring-2 data-[active=true]:ring-primary/30 sm:size-14"
                                                            />
                                                        ),
                                                    )}
                                                </InputOTPGroup>
                                            </InputOTP>
                                            <p className="text-center text-xs text-muted-foreground">
                                                Vous pouvez coller le code reçu
                                                par email.
                                            </p>
                                        </div>
                                        <Button
                                            disabled={busy}
                                            className="h-11 w-full text-base font-semibold"
                                        >
                                            {busy && <Spinner />}
                                            {busy
                                                ? 'Vérification…'
                                                : 'Se connecter'}
                                        </Button>
                                        <div className="flex flex-col items-center gap-1 sm:flex-row sm:justify-between">
                                            <Button
                                                variant="ghost"
                                                type="button"
                                                className="h-10 text-muted-foreground"
                                                disabled={busy}
                                                onClick={() => {
                                                    setChallenge('');
                                                    setCode('');
                                                }}
                                            >
                                                <ArrowLeft aria-hidden="true" />
                                                Changer d’adresse
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                type="button"
                                                className="h-10 text-primary"
                                                disabled={busy || resendIn > 0}
                                                onClick={(event) =>
                                                    requestCode(event)
                                                }
                                            >
                                                <Send aria-hidden="true" />
                                                {resendIn > 0
                                                    ? `Renvoyer le code (${resendIn} s)`
                                                    : 'Renvoyer le code'}
                                            </Button>
                                        </div>
                                    </form>
                                )}

                                {session.google_enabled && (
                                    <>
                                        <div className="flex items-center gap-3 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                            <span
                                                className="h-px flex-1 bg-border"
                                                aria-hidden="true"
                                            />
                                            ou
                                            <span
                                                className="h-px flex-1 bg-border"
                                                aria-hidden="true"
                                            />
                                        </div>
                                        <Button
                                            variant="outline"
                                            className="h-11 w-full font-semibold"
                                            asChild
                                        >
                                            <a href="/api/student/auth/google/redirect">
                                                Continuer avec Google
                                            </a>
                                        </Button>
                                    </>
                                )}
                            </div>
                        </div>
                        <p className="flex items-start gap-2 px-1 text-xs leading-relaxed text-muted-foreground">
                            <Info
                                className="mt-0.5 size-3.5 shrink-0"
                                aria-hidden="true"
                            />
                            {PORTAL_NOTICE}
                        </p>
                    </div>
                )}

                {session?.authenticated && !session.linked && (
                    <div className="mx-auto max-w-xl space-y-4">
                        <Card className="gap-0 py-0 shadow-card">
                            <CardHeader className="gap-1 border-b py-5">
                                <CardTitle className="font-heading text-lg font-bold">
                                    Retrouver ma fiche étudiante
                                </CardTitle>
                                <CardDescription className="leading-relaxed">
                                    Votre établissement a transmis votre fiche à
                                    l’université. Indiquez votre date de
                                    naissance et l’un des renseignements donnés
                                    à l’inscription.
                                </CardDescription>
                            </CardHeader>
                            <form onSubmit={link}>
                                <CardContent className="space-y-5 py-6">
                                    <ol
                                        className="flex items-center gap-2 text-xs font-semibold"
                                        aria-label="Étapes"
                                    >
                                        {[
                                            'Naissance',
                                            'Renseignement',
                                            'Saisie',
                                        ].map((label, index) => {
                                            const current = index === linkStep;
                                            const done = index < linkStep;
                                            return (
                                                <li
                                                    key={label}
                                                    aria-current={
                                                        current
                                                            ? 'step'
                                                            : undefined
                                                    }
                                                    className={cn(
                                                        'flex min-w-0 flex-1 items-center gap-2 rounded-full px-1 py-1 pr-3',
                                                        current
                                                            ? 'bg-accent text-accent-foreground'
                                                            : 'text-muted-foreground',
                                                    )}
                                                >
                                                    <span
                                                        className={cn(
                                                            'flex size-6 shrink-0 items-center justify-center rounded-full',
                                                            current || done
                                                                ? 'bg-primary text-primary-foreground'
                                                                : 'bg-muted',
                                                        )}
                                                        aria-hidden="true"
                                                    >
                                                        {done ? (
                                                            <CheckCircle2 className="size-3.5" />
                                                        ) : (
                                                            index + 1
                                                        )}
                                                    </span>
                                                    <span className="truncate">
                                                        {label}
                                                    </span>
                                                </li>
                                            );
                                        })}
                                    </ol>

                                    {linkStep === 0 && (
                                        <div className="grid gap-2">
                                            <Label htmlFor="birth">
                                                Quelle est votre date de
                                                naissance ?
                                            </Label>
                                            <Input
                                                id="birth"
                                                type="date"
                                                required
                                                autoFocus
                                                max={today}
                                                min="1900-01-01"
                                                className={fieldClass}
                                                value={birth}
                                                onChange={(e) =>
                                                    setBirth(e.target.value)
                                                }
                                            />
                                            <p className="text-xs text-muted-foreground">
                                                Telle qu’elle figure sur votre
                                                acte de naissance.
                                            </p>
                                        </div>
                                    )}

                                    {linkStep === 1 && (
                                        <fieldset className="grid gap-2">
                                            <legend className="mb-2 text-sm font-medium text-foreground">
                                                Avec quel renseignement
                                                voulez-vous être reconnu ?
                                                <span className="block text-xs font-normal text-muted-foreground">
                                                    Un seul suffit : choisissez
                                                    celui que vous avez donné à
                                                    votre établissement.
                                                </span>
                                            </legend>
                                            {(
                                                Object.keys(
                                                    identifierOptions,
                                                ) as IdentifierType[]
                                            ).map((type) => (
                                                <label
                                                    key={type}
                                                    className={cn(
                                                        'flex cursor-pointer items-center gap-3 rounded-lg border px-3 py-3 text-sm transition-colors has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-ring',
                                                        identifierType === type
                                                            ? 'border-primary bg-accent'
                                                            : 'hover:bg-muted/60',
                                                    )}
                                                >
                                                    <input
                                                        type="radio"
                                                        name="identifier_type"
                                                        value={type}
                                                        checked={
                                                            identifierType ===
                                                            type
                                                        }
                                                        onChange={() => {
                                                            setIdentifierType(
                                                                type,
                                                            );
                                                            setIdentifier('');
                                                        }}
                                                        className="size-4 shrink-0 accent-primary"
                                                    />
                                                    <span className="min-w-0">
                                                        <span className="block font-medium text-foreground">
                                                            {
                                                                identifierOptions[
                                                                    type
                                                                ].choice
                                                            }
                                                        </span>
                                                        {type ===
                                                            'last_name' && (
                                                            <span className="block text-xs text-muted-foreground">
                                                                Avec le nom de
                                                                votre mère
                                                            </span>
                                                        )}
                                                    </span>
                                                </label>
                                            ))}
                                        </fieldset>
                                    )}

                                    {linkStep === 2 && (
                                        <div className="grid gap-4">
                                            <p className="rounded-lg bg-muted/50 px-3 py-2 text-xs text-muted-foreground">
                                                Né(e) le{' '}
                                                <span className="font-semibold text-foreground">
                                                    {birth
                                                        .split('-')
                                                        .reverse()
                                                        .join('/')}
                                                </span>{' '}
                                                · par{' '}
                                                <span className="font-semibold text-foreground">
                                                    {identifierOptions[
                                                        identifierType
                                                    ].choice.toLowerCase()}
                                                </span>
                                            </p>
                                            <div className="grid gap-2">
                                                <Label htmlFor="identifier">
                                                    {
                                                        identifierOptions[
                                                            identifierType
                                                        ].label
                                                    }
                                                </Label>
                                                <Input
                                                    id="identifier"
                                                    autoFocus
                                                    placeholder={
                                                        identifierOptions[
                                                            identifierType
                                                        ].placeholder
                                                    }
                                                    type={
                                                        identifierOptions[
                                                            identifierType
                                                        ].type
                                                    }
                                                    inputMode={
                                                        identifierOptions[
                                                            identifierType
                                                        ].inputMode
                                                    }
                                                    autoComplete="off"
                                                    maxLength={150}
                                                    required
                                                    className={fieldClass}
                                                    value={identifier}
                                                    onChange={(e) =>
                                                        setIdentifier(
                                                            e.target.value,
                                                        )
                                                    }
                                                />
                                                <p className="text-xs text-muted-foreground">
                                                    {
                                                        identifierOptions[
                                                            identifierType
                                                        ].hint
                                                    }
                                                </p>
                                            </div>
                                            {identifierType === 'last_name' && (
                                                <div className="grid gap-2">
                                                    <Label htmlFor="mother_name">
                                                        Nom et prénoms de votre
                                                        mère
                                                    </Label>
                                                    <Input
                                                        id="mother_name"
                                                        placeholder="Ex. : RASOA Marie"
                                                        autoComplete="off"
                                                        maxLength={255}
                                                        required
                                                        className={fieldClass}
                                                        value={motherName}
                                                        onChange={(e) =>
                                                            setMotherName(
                                                                e.target.value,
                                                            )
                                                        }
                                                    />
                                                    <p className="text-xs text-muted-foreground">
                                                        Tels qu’ils figurent
                                                        dans le dossier de votre
                                                        établissement.
                                                    </p>
                                                </div>
                                            )}
                                        </div>
                                    )}
                                </CardContent>
                                <CardFooter className="flex-col items-stretch gap-3 border-t bg-muted/30 py-5">
                                    <div className="flex gap-2">
                                        {linkStep > 0 && (
                                            <Button
                                                type="button"
                                                variant="outline"
                                                className="h-10"
                                                disabled={busy}
                                                onClick={() =>
                                                    setLinkStep(
                                                        (linkStep - 1) as 0 | 1,
                                                    )
                                                }
                                            >
                                                <ArrowLeft aria-hidden="true" />
                                                Précédent
                                            </Button>
                                        )}
                                        <Button
                                            disabled={
                                                busy ||
                                                (linkStep === 0 &&
                                                    !birthValid) ||
                                                (linkStep === 2 &&
                                                    (identifier.trim() === '' ||
                                                        (identifierType ===
                                                            'last_name' &&
                                                            motherName.trim() ===
                                                                '')))
                                            }
                                            className="h-10 flex-1 font-semibold"
                                        >
                                            {busy && <Spinner />}
                                            {linkStep < 2
                                                ? 'Suivant'
                                                : busy
                                                  ? 'Recherche…'
                                                  : 'Retrouver ma fiche'}
                                        </Button>
                                    </div>
                                    {linkStep === 2 && (
                                        <p className="text-center text-xs leading-relaxed text-muted-foreground">
                                            Vos réponses sont comparées à la
                                            fiche transmise par votre
                                            établissement. Si elles
                                            correspondent, votre fiche s’ouvre
                                            aussitôt.
                                        </p>
                                    )}
                                </CardFooter>
                            </form>
                        </Card>
                        <p className="px-1 text-xs leading-relaxed text-muted-foreground">
                            {PORTAL_NOTICE}
                        </p>
                        {session.google_enabled && (
                            <Button
                                variant="outline"
                                className="h-10 w-full"
                                asChild
                            >
                                <a href="/api/student/auth/google/redirect">
                                    Associer ou utiliser Google
                                </a>
                            </Button>
                        )}
                    </div>
                )}

                {student && (
                    <div className="space-y-6">
                        <section className="surface relative overflow-hidden rounded-2xl">
                            <div
                                className="bg-grid absolute inset-0 text-primary opacity-50"
                                aria-hidden="true"
                            />
                            <div
                                className="absolute -top-24 -right-16 size-72 rounded-full bg-primary/10 blur-3xl"
                                aria-hidden="true"
                            />
                            <div className="relative flex items-center gap-6 p-5 sm:p-8">
                                <div className="min-w-0 flex-1 space-y-3">
                                    <p className="text-sm font-semibold text-primary">
                                        Bonjour {firstName}
                                    </p>
                                    <h1 className="font-heading text-2xl font-extrabold tracking-tight text-foreground sm:text-3xl">
                                        Mes informations
                                    </h1>
                                    <p className="max-w-xl text-sm text-muted-foreground">
                                        {PORTAL_NOTICE}
                                    </p>
                                    {confirmation && (
                                        <div className="flex flex-wrap items-center gap-2 pt-1">
                                            <ConfirmationBadge
                                                status={confirmation.status}
                                            />
                                        </div>
                                    )}
                                </div>
                                <StudentsIllustration className="hidden h-36 w-auto shrink-0 sm:block md:h-40" />
                            </div>
                            <nav
                                aria-label="Sections du portail"
                                className="relative flex gap-1 overflow-x-auto border-t bg-card/70 px-3 py-2"
                            >
                                {(
                                    [
                                        ['#infos', 'Informations', UserRound],
                                        ['#situation', 'Situation', School],
                                        [
                                            '#verification',
                                            'Vérification',
                                            BadgeCheck,
                                        ],
                                        [
                                            '#mes-demandes',
                                            'Mes demandes',
                                            FilePenLine,
                                        ],
                                    ] as [string, string, LucideIcon][]
                                ).map(([href, label, Icon]) => (
                                    <a
                                        key={href}
                                        href={href}
                                        className="inline-flex shrink-0 items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                    >
                                        <Icon
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        {label}
                                    </a>
                                ))}
                            </nav>
                        </section>

                        <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_24rem]">
                            <div className="min-w-0 space-y-6">
                                <div
                                    id="infos"
                                    className="grid scroll-mt-24 gap-4 md:grid-cols-2 md:gap-5"
                                >
                                    {infoSections(student).map(
                                        (section, index) => (
                                            <section
                                                key={section.title}
                                                aria-labelledby={`info-${index}`}
                                                className="surface overflow-hidden"
                                            >
                                                <SectionHeader
                                                    id={`info-${index}`}
                                                    icon={section.icon}
                                                    title={section.title}
                                                />
                                                {section.note && (
                                                    <p className="flex items-start gap-2 border-b bg-info-soft/60 px-5 py-2.5 text-xs text-info-foreground sm:px-6">
                                                        <Lock
                                                            className="mt-0.5 size-3.5 shrink-0"
                                                            aria-hidden="true"
                                                        />
                                                        {section.note}
                                                    </p>
                                                )}
                                                <dl className="divide-y">
                                                    {section.fields.map(
                                                        (row) => (
                                                            <div
                                                                key={row.label}
                                                                className="px-5 py-3 sm:px-6"
                                                            >
                                                                <dt className="text-xs font-medium text-muted-foreground">
                                                                    {row.label}
                                                                </dt>
                                                                <dd className="mt-0.5 flex min-h-7 items-center justify-between gap-3">
                                                                    <span className="min-w-0 break-words">
                                                                        {row.hidden ? (
                                                                            <span className="inline-flex items-center gap-1.5 text-sm text-muted-foreground">
                                                                                <EyeOff
                                                                                    className="size-3.5"
                                                                                    aria-hidden="true"
                                                                                />
                                                                                Non
                                                                                affiché
                                                                            </span>
                                                                        ) : row.value ===
                                                                              null ||
                                                                          row.value ===
                                                                              '' ? (
                                                                            <span className="text-sm text-muted-foreground italic">
                                                                                Non
                                                                                renseigné
                                                                            </span>
                                                                        ) : (
                                                                            <span className="font-medium text-foreground">
                                                                                {
                                                                                    row.value
                                                                                }
                                                                            </span>
                                                                        )}
                                                                    </span>
                                                                    {row.field &&
                                                                        (pendingFields.has(
                                                                            row.field,
                                                                        ) ? (
                                                                            <ToneBadge
                                                                                tone="warning"
                                                                                className="shrink-0"
                                                                            >
                                                                                Demande
                                                                                en
                                                                                attente
                                                                            </ToneBadge>
                                                                        ) : (
                                                                            <button
                                                                                type="button"
                                                                                aria-label={`Signaler une erreur : ${row.label}`}
                                                                                className="-my-1 -mr-2 inline-flex min-h-9 shrink-0 items-center gap-1.5 rounded-lg px-2.5 text-xs font-semibold text-primary transition-colors hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                                                                onClick={() =>
                                                                                    openReport(
                                                                                        row,
                                                                                    )
                                                                                }
                                                                            >
                                                                                <Flag
                                                                                    className="size-3.5"
                                                                                    aria-hidden="true"
                                                                                />
                                                                                Signaler
                                                                                une
                                                                                erreur
                                                                            </button>
                                                                        ))}
                                                                </dd>
                                                            </div>
                                                        ),
                                                    )}
                                                </dl>
                                            </section>
                                        ),
                                    )}
                                </div>

                                <section
                                    id="situation"
                                    aria-labelledby="situation-title"
                                    className="surface scroll-mt-24 overflow-hidden"
                                >
                                    <SectionHeader
                                        id="situation-title"
                                        icon={School}
                                        title="Situation universitaire"
                                    />
                                    {student.records.length === 0 ? (
                                        <EmptyState
                                            compact
                                            illustration="students"
                                            title="Aucune situation annuelle disponible."
                                        />
                                    ) : (
                                        <ul className="divide-y">
                                            {student.records.map(
                                                (record, index) => (
                                                    <li
                                                        key={index}
                                                        className="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6"
                                                    >
                                                        <div className="flex min-w-0 items-start gap-3">
                                                            <span className="shrink-0 rounded-lg bg-accent px-2.5 py-1 font-heading text-xs font-bold text-accent-foreground">
                                                                {
                                                                    record.academic_year
                                                                }
                                                            </span>
                                                            <div className="min-w-0">
                                                                <p className="font-semibold text-foreground">
                                                                    {
                                                                        record.establishment
                                                                    }
                                                                </p>
                                                                <p className="text-sm text-muted-foreground">
                                                                    {[
                                                                        record.level,
                                                                        record.mention,
                                                                        record.program ??
                                                                            'Parcours non renseigné',
                                                                    ]
                                                                        .filter(
                                                                            Boolean,
                                                                        )
                                                                        .join(
                                                                            ', ',
                                                                        )}
                                                                </p>
                                                                {record.repeat_code && (
                                                                    <p className="text-xs text-muted-foreground">
                                                                        Code
                                                                        redoublement
                                                                        :{' '}
                                                                        {
                                                                            record.repeat_code
                                                                        }
                                                                    </p>
                                                                )}
                                                            </div>
                                                        </div>
                                                        <ToneBadge
                                                            tone={toneFor(
                                                                record.status,
                                                            )}
                                                            className="self-start sm:self-auto"
                                                        >
                                                            {record.status ===
                                                            'active'
                                                                ? 'Situation active'
                                                                : 'Situation inactive'}
                                                        </ToneBadge>
                                                    </li>
                                                ),
                                            )}
                                        </ul>
                                    )}
                                    <p className="flex items-start gap-2 border-t bg-muted/30 px-5 py-3 text-xs text-muted-foreground sm:px-6">
                                        <Info
                                            className="mt-0.5 size-3.5 shrink-0"
                                            aria-hidden="true"
                                        />
                                        Pour une erreur concernant votre niveau,
                                        mention, parcours ou établissement,
                                        contactez votre établissement.
                                    </p>
                                </section>

                                <section
                                    id="mes-demandes"
                                    aria-labelledby="corrections-title"
                                    className="surface scroll-mt-24 overflow-hidden"
                                >
                                    <SectionHeader
                                        id="corrections-title"
                                        icon={FilePenLine}
                                        title="Mes demandes de correction"
                                        description="Vos informations officielles ne changent qu’après validation par le personnel autorisé."
                                        meta={
                                            corrections.length > 0 && (
                                                <ToneBadge tone="neutral">
                                                    {corrections.length}
                                                </ToneBadge>
                                            )
                                        }
                                    />
                                    {corrections.length === 0 ? (
                                        <EmptyState
                                            compact
                                            illustration="inbox"
                                            title="Aucune demande pour le moment."
                                            description="Utilisez « Signaler une erreur » à côté d’une information inexacte."
                                        />
                                    ) : (
                                        <ul className="divide-y">
                                            {corrections.map((item) => (
                                                <li key={item.uuid}>
                                                    <article className="space-y-3 px-5 py-4 sm:px-6">
                                                        <div className="flex flex-wrap items-center justify-between gap-2">
                                                            <h3 className="font-semibold text-foreground">
                                                                {
                                                                    item.field_label
                                                                }
                                                            </h3>
                                                            <ToneBadge
                                                                tone={toneFor(
                                                                    item.status,
                                                                )}
                                                            >
                                                                {
                                                                    item.status_label
                                                                }
                                                            </ToneBadge>
                                                        </div>
                                                        <p className="rounded-lg bg-muted px-3 py-2 text-sm break-words">
                                                            <span className="text-muted-foreground">
                                                                Valeur proposée
                                                                :
                                                            </span>{' '}
                                                            <span className="font-medium text-foreground">
                                                                {optionLabel(
                                                                    item.field,
                                                                    item.proposed_value,
                                                                )}
                                                            </span>
                                                        </p>
                                                        <p className="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted-foreground">
                                                            <span className="inline-flex items-center gap-1.5">
                                                                <Send
                                                                    className="size-3.5"
                                                                    aria-hidden="true"
                                                                />
                                                                Envoyée le{' '}
                                                                {formatDate(
                                                                    item.created_at,
                                                                )}
                                                            </span>
                                                            {item.reviewed_at && (
                                                                <span className="inline-flex items-center gap-1.5">
                                                                    <CalendarCheck
                                                                        className="size-3.5"
                                                                        aria-hidden="true"
                                                                    />
                                                                    Décision le{' '}
                                                                    {formatDate(
                                                                        item.reviewed_at,
                                                                    )}
                                                                </span>
                                                            )}
                                                        </p>
                                                        {item.decision_reason && (
                                                            <p className="border-l-2 border-border pl-3 text-sm text-foreground">
                                                                <span className="font-medium">
                                                                    Motif :
                                                                </span>{' '}
                                                                {
                                                                    item.decision_reason
                                                                }
                                                            </p>
                                                        )}
                                                        {item.status ===
                                                            'pending' && (
                                                            <Button
                                                                variant="outline"
                                                                size="sm"
                                                                disabled={busy}
                                                                onClick={() =>
                                                                    cancelCorrection(
                                                                        item.uuid,
                                                                    )
                                                                }
                                                            >
                                                                <XCircle aria-hidden="true" />
                                                                Annuler la
                                                                demande
                                                            </Button>
                                                        )}
                                                    </article>
                                                </li>
                                            ))}
                                        </ul>
                                    )}
                                </section>

                                {session?.google_enabled && (
                                    <div className="surface flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                                        <p className="text-sm text-muted-foreground">
                                            Connexion simplifiée avec votre
                                            compte Google.
                                        </p>
                                        <Button
                                            variant="outline"
                                            className="shrink-0"
                                            asChild
                                        >
                                            <a href="/api/student/auth/google/redirect">
                                                Associer ou utiliser Google
                                            </a>
                                        </Button>
                                    </div>
                                )}

                                <Dialog
                                    open={reporting !== null}
                                    onOpenChange={(open) => {
                                        if (!open && !busy) setReporting(null);
                                    }}
                                >
                                    <DialogContent className="sm:max-w-md">
                                        <form
                                            onSubmit={submitCorrection}
                                            className="space-y-4"
                                        >
                                            <DialogHeader>
                                                <span
                                                    className="mx-auto mb-2 flex size-11 items-center justify-center rounded-xl bg-accent text-primary sm:mx-0"
                                                    aria-hidden="true"
                                                >
                                                    <Flag className="size-5" />
                                                </span>
                                                <DialogTitle>
                                                    Signaler une erreur :{' '}
                                                    {reporting?.label}
                                                </DialogTitle>
                                                <DialogDescription>
                                                    Votre demande sera examinée
                                                    par le personnel. La fiche
                                                    officielle ne change
                                                    qu’après approbation.
                                                </DialogDescription>
                                            </DialogHeader>
                                            {reporting && !reporting.hidden && (
                                                <p className="rounded-lg bg-muted px-3 py-2 text-sm">
                                                    <span className="text-muted-foreground">
                                                        Valeur actuelle :
                                                    </span>{' '}
                                                    <span className="font-medium break-words">
                                                        {reporting.value ??
                                                            'Non renseignée'}
                                                    </span>
                                                </p>
                                            )}
                                            <div className="space-y-2">
                                                <Label htmlFor="proposed">
                                                    Valeur correcte
                                                </Label>
                                                {reporting?.options ? (
                                                    <Select
                                                        required
                                                        value={proposed}
                                                        onValueChange={
                                                            setProposed
                                                        }
                                                    >
                                                        <SelectTrigger
                                                            id="proposed"
                                                            className="h-11 w-full bg-card"
                                                        >
                                                            <SelectValue placeholder="Choisir une valeur" />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            {reporting.options.map(
                                                                (option) => (
                                                                    <SelectItem
                                                                        key={
                                                                            option.value
                                                                        }
                                                                        value={
                                                                            option.value
                                                                        }
                                                                    >
                                                                        {
                                                                            option.label
                                                                        }
                                                                    </SelectItem>
                                                                ),
                                                            )}
                                                        </SelectContent>
                                                    </Select>
                                                ) : (
                                                    <Input
                                                        id="proposed"
                                                        type={
                                                            reporting?.input ??
                                                            'text'
                                                        }
                                                        required
                                                        maxLength={2000}
                                                        className={fieldClass}
                                                        value={proposed}
                                                        onChange={(e) =>
                                                            setProposed(
                                                                e.target.value,
                                                            )
                                                        }
                                                    />
                                                )}
                                            </div>
                                            <div className="space-y-2">
                                                <div className="flex items-baseline justify-between gap-2">
                                                    <Label htmlFor="note">
                                                        Message (facultatif)
                                                    </Label>
                                                    <span
                                                        className="text-xs text-muted-foreground tabular-nums"
                                                        aria-hidden="true"
                                                    >
                                                        {note.length}/500
                                                    </span>
                                                </div>
                                                <textarea
                                                    id="note"
                                                    className="min-h-24 w-full rounded-md border border-input bg-card px-3 py-2 text-base shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm"
                                                    maxLength={500}
                                                    placeholder="Précisez si besoin (document justificatif, contexte…)"
                                                    value={note}
                                                    onChange={(e) =>
                                                        setNote(e.target.value)
                                                    }
                                                />
                                            </div>
                                            {dialogError && (
                                                <Notice
                                                    tone="danger"
                                                    icon={AlertCircle}
                                                    role="alert"
                                                >
                                                    {dialogError}
                                                </Notice>
                                            )}
                                            <DialogFooter className="gap-2">
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    disabled={busy}
                                                    onClick={() =>
                                                        setReporting(null)
                                                    }
                                                >
                                                    Annuler
                                                </Button>
                                                <Button disabled={busy}>
                                                    {busy ? (
                                                        <Spinner />
                                                    ) : (
                                                        <Send aria-hidden="true" />
                                                    )}
                                                    {busy
                                                        ? 'Envoi…'
                                                        : 'Envoyer la demande'}
                                                </Button>
                                            </DialogFooter>
                                        </form>
                                    </DialogContent>
                                </Dialog>
                            </div>
                            {/* Verification stays in view while the student reads the information. */}
                            <aside className="space-y-4 lg:sticky lg:top-24">
                                {confirmation && (
                                    <ConfirmationSection
                                        compact
                                        state={confirmation}
                                        onDone={async (result) => {
                                            toast.success(result);
                                            await refresh();
                                        }}
                                    />
                                )}
                            </aside>
                        </div>
                    </div>
                )}
            </main>

            <footer className="border-t">
                <div className="mx-auto flex max-w-screen-2xl flex-col gap-1 px-4 py-6 text-xs text-muted-foreground sm:flex-row sm:items-center sm:justify-between sm:px-6">
                    <p>
                        © {new Date().getFullYear()} {branding.institution_name}{' '}
                        · {branding.bourse_name}
                    </p>
                    <p>
                        {branding.support_email ? (
                            <>
                                Une question ?{' '}
                                <a
                                    href={`mailto:${branding.support_email}`}
                                    className="font-medium text-primary underline-offset-4 hover:underline"
                                >
                                    {branding.support_email}
                                </a>
                            </>
                        ) : (
                            'Bourses nationales · Vérification des informations'
                        )}
                    </p>
                </div>
            </footer>
        </div>
    );
}

// Light / dark / system, shared with the internal portal (cookie + localStorage).
initializeTheme();

const element = document.getElementById('bourse-root');
if (element) {
    // Dev HMR may evaluate this entry again: reuse the existing root instead of creating a second one.
    const host = element as HTMLElement & { bourseRoot?: Root };
    host.bourseRoot ??= createRoot(element);
    // Success messages are toasts, centred at the bottom of the screen.
    host.bourseRoot.render(
        <>
            <Portal />
            <Toaster
                position="bottom-center"
                richColors
                closeButton
                theme={
                    document.documentElement.classList.contains('dark')
                        ? 'dark'
                        : 'light'
                }
                toastOptions={{ className: 'font-sans text-sm' }}
            />
        </>,
    );
}
