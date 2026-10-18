import {
    AlertCircle,
    GraduationCap,
    IdCard,
    LogOut,
    PenLine,
    Phone,
    School,
    UserRound,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useEffect, useState } from 'react';
import type { CSSProperties, FormEvent, ReactNode } from 'react';
import { createRoot } from 'react-dom/client';
import { Toaster, toast } from 'sonner';
import type { Root } from 'react-dom/client';
import { BrandMark } from '@/components/brand-mark';
import { StudentPortalSkeleton } from '@/components/loading/page-skeleton';
import { EmptyState } from '@/components/empty-state';

import { ThemeToggle } from '@/components/theme-toggle';
import { ToneBadge, toneFor } from '@/components/tone-badge';
import { Button } from '@/components/ui/button';
import { Card, CardDescription, CardHeader } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';

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
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
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
import { StudentAccess } from './access';
import { AccountMenu } from './account-menu';
import { SectionTitle } from './section-title';
import { NotificationBell } from './notification-bell';
import type { PortalNotification } from './notification-bell';
import { RequestsPage } from './requests-page';
import type { Correction, OfficeMessage } from './requests-page';
import { ConfirmationSection } from './confirmation';
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
type FieldOption = { value: string; label: string };
type FieldRow = {
    label: string;
    value: string | number | null;
    field?: string;
    hidden?: boolean;
    input?: 'date' | 'number' | 'email' | 'tel';
    /** Contact detail saved at once by the student (ADR-089), never waiting for validation. */
    direct?: boolean;
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
    /** Code bourse of the PNES base; read only, corrected by the Scolarité. */
    scholarship_code: string | null;
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
    /** Short masked values sit three to a row on a wide screen. */
    columns?: 3;
    fields: FieldRow[];
};

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
                    direct: true,
                },
                {
                    label: 'Date de naissance',
                    value: student.birth_date
                        ? formatDate(student.birth_date)
                        : null,
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
                    direct: true,
                },
                ...(student.scholarship_code
                    ? [
                          {
                              label: 'Code bourse',
                              value: student.scholarship_code,
                          },
                      ]
                    : []),
            ],
        },
        {
            title: 'Carte d’identité (CIN)',
            icon: IdCard,
            note: 'Masquée pour votre sécurité.',
            columns: 3,
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
            icon: Phone,
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
                    direct: true,
                },
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

/** A portal section: shadcn card, plain title, optional description, action and footer note. */
function PortalSection({
    id,
    icon,
    title,
    description,
    action,
    footer,
    children,
}: {
    id?: string;
    icon: LucideIcon;
    title: string;
    description?: string;
    action?: ReactNode;
    footer?: string;
    children: ReactNode;
}) {
    return (
        <Card
            id={id}
            className="scroll-mt-24 gap-0 overflow-hidden py-0"
            aria-labelledby={id ? `${id}-title` : undefined}
        >
            <CardHeader className="flex-row items-start justify-between gap-3 border-b px-5 py-4 sm:px-6">
                <div className="min-w-0 space-y-1">
                    <SectionTitle
                        icon={icon}
                        id={id ? `${id}-title` : undefined}
                    >
                        {title}
                    </SectionTitle>
                    {description && (
                        <CardDescription>{description}</CardDescription>
                    )}
                </div>
                {action}
            </CardHeader>
            {children}
            {footer && (
                <p className="border-t px-5 py-3 text-xs text-muted-foreground sm:px-6">
                    {footer}
                </p>
            )}
        </Card>
    );
}

/**
 * Fields of a section as a grid: two (or three) per row on a wide screen, one per row on a phone.
 * Hairlines come from the 1px gap over the border colour; an odd last field takes the whole row.
 */
function FieldGrid({
    section,
    pendingFields,
    onReport,
}: {
    section: InfoSection;
    pendingFields: Set<string>;
    onReport: (row: FieldRow) => void;
}) {
    const columns = section.columns ?? 2;
    const lastSpans = columns === 2 && section.fields.length % 2 === 1;

    return (
        <dl
            className={cn(
                'grid gap-px bg-border',
                columns === 3 ? 'sm:grid-cols-3' : 'sm:grid-cols-2',
            )}
        >
            {section.fields.map((row, index) => {
                const empty =
                    row.hidden || row.value === null || row.value === '';

                return (
                    <div
                        key={row.label}
                        className={cn(
                            'bg-card px-5 py-3.5 sm:px-6',
                            lastSpans &&
                                index === section.fields.length - 1 &&
                                'sm:col-span-2',
                        )}
                    >
                        <dt className="text-xs text-muted-foreground">
                            {row.label}
                        </dt>
                        <dd className="mt-0.5 flex min-h-8 items-center justify-between gap-3">
                            <span
                                className={cn(
                                    'min-w-0 text-sm break-words',
                                    empty
                                        ? 'text-muted-foreground'
                                        : 'font-medium text-foreground',
                                )}
                            >
                                {row.hidden
                                    ? 'Masqué'
                                    : empty
                                      ? 'Non renseigné'
                                      : row.value}
                            </span>
                            {/* A direct field keeps its pencil: saving it cancels a request left from before. */}
                            {row.field &&
                                (pendingFields.has(row.field) && !row.direct ? (
                                    <ToneBadge
                                        tone="warning"
                                        className="shrink-0"
                                    >
                                        En attente
                                    </ToneBadge>
                                ) : (
                                    <ReportButton
                                        label={row.label}
                                        direct={row.direct === true}
                                        onClick={() => onReport(row)}
                                    />
                                ))}
                        </dd>
                    </div>
                );
            })}
        </dl>
    );
}

/** Icon-only edit action, the label in the tooltip and for screen readers. */
function ReportButton({
    label,
    direct,
    onClick,
}: {
    label: string;
    /** Phone or address: changed at once instead of reported. */
    direct: boolean;
    onClick: () => void;
}) {
    const action = direct ? 'Modifier' : 'Signaler une erreur';
    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="-my-1 -mr-2 size-9 shrink-0 text-muted-foreground hover:text-primary"
                    aria-label={`${action} : ${label}`}
                    onClick={onClick}
                >
                    <PenLine className="size-4" aria-hidden="true" />
                </Button>
            </TooltipTrigger>
            <TooltipContent>{action}</TooltipContent>
        </Tooltip>
    );
}

function Portal() {
    const [session, setSession] = useState<Session | null>(null);
    const [student, setStudent] = useState<Student | null>(null);
    const [confirmation, setConfirmation] = useState<ConfirmationState | null>(
        null,
    );
    const [busy, setBusy] = useState(false);
    // The first load failed: no spinner forever, a way to try again.
    const [loadFailed, setLoadFailed] = useState(false);
    const [corrections, setCorrections] = useState<Correction[]>([]);
    const [notifications, setNotifications] = useState<{
        items: PortalNotification[];
        unread: number;
    }>({ items: [], unread: 0 });
    const [messages, setMessages] = useState<OfficeMessage[] | null>(null);
    // Two screens of one page: the record (/) and « Mes demandes » (/demandes).
    const [path, setPath] = useState(() => window.location.pathname);
    useEffect(() => {
        const onPop = () => setPath(window.location.pathname);
        window.addEventListener('popstate', onPop);
        return () => window.removeEventListener('popstate', onPop);
    }, []);
    const onRequestsPage = path.replace(/\/$/, '') === '/demandes';
    useEffect(() => {
        if (!onRequestsPage || !session?.linked) return;
        setMessages(null);
        api<{ messages: OfficeMessage[] }>('messages')
            .then((result) => setMessages(result.messages))
            .catch(() => setMessages([]));
    }, [onRequestsPage, session?.linked]);
    const navigate = (to: string) => {
        if (to !== window.location.pathname)
            window.history.pushState({}, '', to);
        setPath(to);
        window.scrollTo({ top: 0 });
    };
    const markSeen = () => {
        setNotifications((current) => ({
            items: current.items,
            unread: 0,
        }));
        void api('notifications/seen', {}).catch(() => undefined);
    };
    const [reporting, setReporting] = useState<FieldRow | null>(null);
    const [proposed, setProposed] = useState('');
    const [note, setNote] = useState('');
    const [dialogError, setDialogError] = useState('');
    async function refresh() {
        setLoadFailed(false);
        const next = await api<Session>('session');
        setSession(next);
        if (next.linked) {
            try {
                const [result, list, feed] = await Promise.all([
                    api<{ student: Student; confirmation: ConfirmationState }>(
                        'me',
                    ),
                    api<{ corrections: Correction[] }>('corrections'),
                    api<{ items: PortalNotification[]; unread: number }>(
                        'notifications',
                    ),
                ]);
                setStudent(result.student);
                setConfirmation(result.confirmation);
                setCorrections(list.corrections);
                setNotifications(feed);
            } catch (reason) {
                setStudent(null);
                setLoadFailed(true);
                throw reason;
            }
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
    const logout = () =>
        void perform(async () => {
            await api('logout', {});
            setStudent(null);
            await refresh();
            toast.success('Vous êtes déconnecté.');
        });
    const fullName = student
        ? [student.first_names?.split(' ')[0], student.last_name]
              .filter(Boolean)
              .join(' ')
        : null;
    // Current declaration first: the active one, otherwise the first listed.
    const current =
        student?.records.find((record) => record.status === 'active') ??
        student?.records[0];
    // Header, contenu et footer partagent la même largeur : étroite pour la connexion, large pour la fiche.
    const container = session?.linked ? 'max-w-6xl' : 'max-w-3xl';

    return (
        <div
            className={cn(
                'flex min-h-dvh flex-col',
                !session?.linked && 'bg-card sm:bg-background',
            )}
        >
            <header className="sticky top-0 z-40 border-b bg-card/85 backdrop-blur supports-[backdrop-filter]:bg-card/70">
                <div
                    className={cn(
                        'mx-auto flex h-16 items-center justify-between gap-3 px-4 sm:px-6',
                        container,
                    )}
                >
                    <a
                        href="/"
                        className="flex min-w-0 items-center gap-3 rounded-lg focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    >
                        <BrandMark
                            logoUrl={branding.logo_url}
                            className={cn(
                                'size-9 ring-0',
                                branding.logo_url
                                    ? 'bg-transparent p-0'
                                    : 'bg-sidebar',
                            )}
                        />
                        <span className="min-w-0 leading-tight">
                            <span className="block font-heading text-[15px] font-bold tracking-tight text-foreground">
                                {branding.bourse_name}
                            </span>
                            <span className="block truncate text-xs text-muted-foreground">
                                Portail étudiant
                            </span>
                        </span>
                    </a>
                    {session?.authenticated && fullName ? (
                        <div className="flex items-center gap-3">
                            <NotificationBell
                                items={notifications.items}
                                unread={notifications.unread}
                                onOpen={markSeen}
                                onViewAll={() => navigate('/demandes')}
                            />
                            <AccountMenu
                                name={fullName}
                                lastName={student?.last_name ?? fullName}
                                email={session.email}
                                busy={busy}
                                onRequests={() => navigate('/demandes')}
                                onLogout={logout}
                            />
                        </div>
                    ) : (
                        <div className="flex items-center gap-1">
                            <ThemeToggle className="border-0 bg-transparent shadow-none" />
                            {/* Identity not verified yet: no account to show, only a way out. */}
                            {session?.authenticated && (
                                <Button
                                    variant="ghost"
                                    className="h-10 gap-2 px-3 text-muted-foreground hover:text-foreground"
                                    disabled={busy}
                                    onClick={logout}
                                >
                                    <LogOut aria-hidden="true" />
                                    Quitter
                                </Button>
                            )}
                        </div>
                    )}
                </div>
            </header>

            <main
                className={cn(
                    'mx-auto w-full flex-1 px-4 py-6 sm:px-6',
                    container,
                    // Sign-in steps sit in the middle of the screen; the record reads from the top.
                    session?.linked
                        ? 'sm:py-10'
                        : 'flex flex-col justify-center sm:py-10',
                )}
            >
                {!session && (
                    <div className="mx-auto w-full max-w-lg">
                        {loadFailed ? (
                            <Card className="items-center gap-3 px-6 py-10 text-center text-sm text-muted-foreground">
                                Le portail est momentanément indisponible.
                                <Button
                                    variant="outline"
                                    onClick={() => window.location.reload()}
                                >
                                    Réessayer
                                </Button>
                            </Card>
                        ) : (
                            <div
                                role="status"
                                className="flex items-center justify-center gap-3 py-20 text-sm text-muted-foreground"
                            >
                                <Spinner />
                                Chargement…
                            </div>
                        )}
                    </div>
                )}

                {session && !session.linked && (
                    <StudentAccess
                        key={session.authenticated ? 'identity' : 'email'}
                        session={session}
                        onComplete={refresh}
                        supportEmail={branding.support_email}
                    />
                )}

                {session?.authenticated &&
                    session.linked &&
                    !student &&
                    (loadFailed ? (
                        <Card role="alert" className="gap-4 p-6 text-sm">
                            <p>Vos informations n’ont pas pu être chargées.</p>
                            <Button
                                variant="outline"
                                className="self-start"
                                onClick={() => window.location.reload()}
                            >
                                Réessayer
                            </Button>
                        </Card>
                    ) : (
                        <StudentPortalSkeleton profile />
                    ))}

                {student && onRequestsPage && (
                    <RequestsPage
                        corrections={corrections}
                        messages={messages}
                        busy={busy}
                        onBack={() => navigate('/')}
                        onCancel={cancelCorrection}
                        formatValue={optionLabel}
                    />
                )}

                {student && !onRequestsPage && (
                    <div className="space-y-8">
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                            <div className="min-w-0">
                                <h1 className="font-heading text-2xl font-bold tracking-tight sm:text-3xl">
                                    Bonjour {firstName}
                                </h1>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    {current
                                        ? `${current.establishment} · ${current.level} · ${current.academic_year}`
                                        : 'Votre fiche étudiante'}
                                </p>
                            </div>
                        </div>

                        <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
                            {/* First on a phone: the one action of the page and the current situation. Beside the record on a wide screen. */}
                            <aside className="order-first space-y-6 lg:order-none lg:col-start-2 lg:row-start-1">
                                {confirmation && (
                                    <ConfirmationSection
                                        compact
                                        state={confirmation}
                                        onDone={async (result) => {
                                            if (result) toast.success(result);
                                            await refresh();
                                        }}
                                    />
                                )}
                                <PortalSection
                                    id="situation"
                                    icon={School}
                                    title="Situation universitaire"
                                    footer="Une erreur sur le niveau, la mention, le parcours ou l’établissement ? Contactez votre établissement."
                                >
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
                                                        className="flex items-start justify-between gap-4 px-5 py-4 sm:px-6"
                                                    >
                                                        <div className="min-w-0 space-y-0.5">
                                                            <p className="text-xs text-muted-foreground tabular-nums">
                                                                {
                                                                    record.academic_year
                                                                }
                                                            </p>
                                                            <p className="font-medium text-foreground">
                                                                {
                                                                    record.establishment
                                                                }
                                                            </p>
                                                            <p className="text-sm text-muted-foreground">
                                                                {[
                                                                    record.level,
                                                                    record.mention,
                                                                    record.program,
                                                                ]
                                                                    .filter(
                                                                        Boolean,
                                                                    )
                                                                    .join(
                                                                        ' · ',
                                                                    )}
                                                            </p>
                                                            {record.repeat_code && (
                                                                <p className="text-xs text-muted-foreground">
                                                                    Redoublement
                                                                    :{' '}
                                                                    {
                                                                        record.repeat_code
                                                                    }
                                                                </p>
                                                            )}
                                                        </div>
                                                        <ToneBadge
                                                            tone={toneFor(
                                                                record.status,
                                                            )}
                                                            className="shrink-0"
                                                        >
                                                            {record.status ===
                                                            'active'
                                                                ? 'Active'
                                                                : 'Inactive'}
                                                        </ToneBadge>
                                                    </li>
                                                ),
                                            )}
                                        </ul>
                                    )}
                                </PortalSection>
                            </aside>

                            <div className="min-w-0 space-y-6 lg:col-start-1 lg:row-start-1">
                                <div
                                    id="infos"
                                    className="scroll-mt-24 space-y-6"
                                >
                                    {infoSections(student).map((section) => (
                                        <PortalSection
                                            key={section.title}
                                            icon={section.icon}
                                            title={section.title}
                                            description={section.note}
                                        >
                                            <FieldGrid
                                                section={section}
                                                pendingFields={pendingFields}
                                                onReport={openReport}
                                            />
                                        </PortalSection>
                                    ))}
                                </div>

                                {session?.google_enabled && (
                                    <Card className="flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
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
                                                Associer Google
                                            </a>
                                        </Button>
                                    </Card>
                                )}
                            </div>
                        </div>

                        <Dialog
                            open={reporting !== null}
                            onOpenChange={(open) => {
                                if (!open && !busy) setReporting(null);
                            }}
                        >
                            <DialogContent className="sm:max-w-md">
                                <form
                                    onSubmit={submitCorrection}
                                    className="space-y-5"
                                >
                                    <DialogHeader>
                                        <DialogTitle>
                                            {reporting?.direct
                                                ? `Modifier : ${reporting.label.toLowerCase()}`
                                                : 'Signaler une erreur'}
                                        </DialogTitle>
                                        <DialogDescription>
                                            {reporting?.direct
                                                ? 'Enregistré tout de suite sur votre fiche.'
                                                : `${reporting?.label ?? ''} · la scolarité validera votre demande.`}
                                        </DialogDescription>
                                    </DialogHeader>
                                    {reporting && !reporting.hidden && (
                                        <div className="space-y-1">
                                            <p className="text-xs text-muted-foreground">
                                                Valeur actuelle
                                            </p>
                                            <p className="text-sm font-medium break-words">
                                                {reporting.value ??
                                                    'Non renseignée'}
                                            </p>
                                        </div>
                                    )}
                                    <div className="space-y-2">
                                        <Label htmlFor="proposed">
                                            {reporting?.direct
                                                ? 'Nouvelle valeur'
                                                : 'Valeur correcte'}
                                        </Label>
                                        {reporting?.options ? (
                                            <Select
                                                required
                                                value={proposed}
                                                onValueChange={setProposed}
                                            >
                                                <SelectTrigger
                                                    id="proposed"
                                                    className="w-full text-base data-[size=default]:h-11"
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
                                                                {option.label}
                                                            </SelectItem>
                                                        ),
                                                    )}
                                                </SelectContent>
                                            </Select>
                                        ) : (
                                            <Input
                                                id="proposed"
                                                type={
                                                    reporting?.input ?? 'text'
                                                }
                                                required
                                                maxLength={2000}
                                                className="h-11 text-base"
                                                value={proposed}
                                                onChange={(e) =>
                                                    setProposed(e.target.value)
                                                }
                                            />
                                        )}
                                    </div>
                                    {/* Saved at once: nobody reads a message. */}
                                    {!reporting?.direct && (
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
                                            <Textarea
                                                id="note"
                                                className="min-h-24 text-base md:text-sm"
                                                maxLength={500}
                                                placeholder="Précisez si besoin (justificatif, contexte…)"
                                                value={note}
                                                onChange={(e) =>
                                                    setNote(e.target.value)
                                                }
                                            />
                                        </div>
                                    )}
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
                                            onClick={() => setReporting(null)}
                                        >
                                            Annuler
                                        </Button>
                                        <Button disabled={busy}>
                                            {busy && <Spinner />}
                                            {busy
                                                ? 'Envoi…'
                                                : reporting?.direct
                                                  ? 'Enregistrer'
                                                  : 'Envoyer la demande'}
                                        </Button>
                                    </DialogFooter>
                                </form>
                            </DialogContent>
                        </Dialog>
                    </div>
                )}
            </main>

            <footer className="border-t">
                <div
                    className={cn(
                        'mx-auto flex flex-col items-center gap-1 px-4 py-4 pb-[max(1rem,env(safe-area-inset-bottom))] text-center text-xs text-muted-foreground sm:flex-row sm:justify-between sm:px-6 sm:text-left',
                        container,
                    )}
                >
                    <p>
                        © {new Date().getFullYear()} {branding.institution_name}{' '}
                        · {branding.bourse_name}
                    </p>
                    <p>Conçu par Service TIC</p>
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
        <TooltipProvider delayDuration={300}>
            <Portal />
            <Toaster
                position="bottom-center"
                // Above the footer and the phone's home bar, so that nothing stays hidden on a small screen.
                offset={{ bottom: 32 }}
                mobileOffset={{
                    bottom: 'calc(env(safe-area-inset-bottom) + 80px)',
                    left: 16,
                    right: 16,
                }}
                richColors
                closeButton
                theme={
                    document.documentElement.classList.contains('dark')
                        ? 'dark'
                        : 'light'
                }
                toastOptions={{ className: 'font-sans text-sm' }}
                // shadcn/ui tokens, as in components/ui/sonner.
                style={
                    {
                        '--normal-bg': 'var(--popover)',
                        '--normal-text': 'var(--popover-foreground)',
                        '--normal-border': 'var(--border)',
                    } as CSSProperties
                }
            />
        </TooltipProvider>,
    );
}
