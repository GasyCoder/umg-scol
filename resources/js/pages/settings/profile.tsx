import { Form, Head, Link, usePage } from '@inertiajs/react';
import {
    AtSign,
    BadgeCheck,
    Building2,
    CalendarDays,
    Check,
    ChevronRight,
    Globe2,
    KeyRound,
    MailWarning,
    Palette,
    Save,
    ShieldCheck,
    ShieldOff,
    UserRound,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/delete-user';
import { ToneBadge } from '@/components/tone-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useInitials } from '@/hooks/use-initials';
import { SettingsSection } from '@/layouts/settings/layout';
import { cn } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { edit } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import { send } from '@/routes/verification';

type Account = {
    establishment: { code: string; name: string } | null;
    two_factor_enabled: boolean;
    created_at: string | null;
};

const formatMonth = (value: string | null) =>
    value
        ? new Intl.DateTimeFormat('fr-FR', {
              month: 'long',
              year: 'numeric',
          }).format(new Date(value))
        : '—';

export default function Profile({
    mustVerifyEmail,
    status,
    account,
}: {
    mustVerifyEmail: boolean;
    status?: string;
    account: Account;
}) {
    const { auth } = usePage().props;
    const getInitials = useInitials();
    const verified = auth.user.email_verified_at !== null;

    return (
        <>
            <Head title="Mon profil" />
            <h1 className="sr-only">Mon profil</h1>

            <section className="surface overflow-hidden">
                <div className="relative h-28 bg-sidebar sm:h-32 dark:bg-linear-to-br dark:from-accent dark:to-sidebar">
                    <div
                        className="bg-grid absolute inset-0 text-white opacity-30"
                        aria-hidden="true"
                    />
                    <div
                        className="absolute -top-16 right-10 size-48 rounded-full bg-primary/40 blur-3xl"
                        aria-hidden="true"
                    />
                </div>
                <div className="relative px-5 pb-5 sm:px-6">
                    <div className="-mt-12 flex flex-col gap-4 sm:-mt-14 sm:flex-row sm:items-end sm:justify-between">
                        <div className="flex items-end gap-4">
                            <span
                                className="flex size-24 shrink-0 items-center justify-center rounded-2xl bg-primary font-heading text-3xl font-extrabold text-primary-foreground shadow-elevated ring-4 ring-card sm:size-28"
                                aria-hidden="true"
                            >
                                {getInitials(auth.user.name)}
                            </span>
                            <div className="min-w-0 pb-1">
                                <p className="truncate font-heading text-xl font-extrabold tracking-tight text-foreground sm:text-2xl">
                                    {auth.user.name}
                                </p>
                                <p className="truncate text-sm text-muted-foreground">
                                    {auth.user.email}
                                </p>
                            </div>
                        </div>
                        <div className="flex flex-wrap gap-1.5 sm:justify-end sm:pb-1">
                            {auth.roleLabel && (
                                <ToneBadge tone="primary">
                                    {auth.roleLabel}
                                </ToneBadge>
                            )}
                            <ToneBadge tone={verified ? 'success' : 'warning'}>
                                {verified
                                    ? 'Email vérifié'
                                    : 'Email à vérifier'}
                            </ToneBadge>
                            <ToneBadge
                                tone={
                                    account.two_factor_enabled
                                        ? 'success'
                                        : 'neutral'
                                }
                            >
                                {account.two_factor_enabled
                                    ? '2FA activée'
                                    : '2FA désactivée'}
                            </ToneBadge>
                        </div>
                    </div>

                    <dl className="mt-5 grid gap-3 sm:grid-cols-3">
                        <Fact
                            icon={account.establishment ? Building2 : Globe2}
                            label="Périmètre"
                        >
                            {account.establishment
                                ? `${account.establishment.code} · ${account.establishment.name}`
                                : 'Accès global'}
                        </Fact>
                        <Fact icon={ShieldCheck} label="Rôle">
                            {auth.roleLabel ?? '—'}
                        </Fact>
                        <Fact icon={CalendarDays} label="Membre depuis">
                            <span className="first-letter:uppercase">
                                {formatMonth(account.created_at)}
                            </span>
                        </Fact>
                    </dl>
                </div>
            </section>

            <SettingsSection
                icon={UserRound}
                title="Informations personnelles"
                description="Le nom affiché dans le portail et le journal d’audit, et l’adresse utilisée pour vous connecter."
            >
                <Form
                    {...ProfileController.update.form()}
                    options={{ preserveScroll: true }}
                    className="space-y-6"
                >
                    {({ processing, errors, recentlySuccessful }) => (
                        <>
                            <div className="grid gap-5 sm:grid-cols-2">
                                <Field
                                    id="name"
                                    label="Nom complet"
                                    icon={UserRound}
                                    error={errors.name}
                                >
                                    <Input
                                        id="name"
                                        name="name"
                                        defaultValue={auth.user.name}
                                        required
                                        autoComplete="name"
                                        placeholder="Prénom NOM"
                                        aria-invalid={Boolean(errors.name)}
                                        className="h-12 pl-11 text-base md:text-base"
                                    />
                                </Field>
                                <Field
                                    id="email"
                                    label="Adresse email"
                                    icon={AtSign}
                                    error={errors.email}
                                >
                                    <Input
                                        id="email"
                                        type="email"
                                        name="email"
                                        defaultValue={auth.user.email}
                                        required
                                        autoComplete="username"
                                        placeholder="vous@exemple.mg"
                                        aria-invalid={Boolean(errors.email)}
                                        className="h-12 pl-11 text-base md:text-base"
                                    />
                                </Field>
                            </div>

                            {mustVerifyEmail && !verified && (
                                <div className="flex items-start gap-3 rounded-xl border border-warning/30 bg-warning-soft p-4 text-sm text-warning-foreground">
                                    <MailWarning
                                        className="mt-0.5 size-5 shrink-0"
                                        aria-hidden="true"
                                    />
                                    <div className="space-y-1">
                                        <p className="font-semibold">
                                            Votre adresse email n’est pas
                                            vérifiée.
                                        </p>
                                        <p>
                                            <Link
                                                href={send()}
                                                as="button"
                                                className="rounded-sm font-semibold underline underline-offset-4 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                            >
                                                Renvoyer l’email de vérification
                                            </Link>
                                        </p>
                                        {status ===
                                            'verification-link-sent' && (
                                            <p
                                                role="status"
                                                className="font-medium"
                                            >
                                                Un nouveau lien de vérification
                                                a été envoyé.
                                            </p>
                                        )}
                                    </div>
                                </div>
                            )}

                            <div className="flex flex-col-reverse items-stretch gap-3 border-t pt-5 sm:flex-row sm:items-center sm:justify-end">
                                <p
                                    role="status"
                                    aria-live="polite"
                                    className={cn(
                                        'flex items-center justify-center gap-1.5 text-sm font-medium text-success-foreground transition-opacity',
                                        recentlySuccessful
                                            ? 'opacity-100'
                                            : 'opacity-0',
                                    )}
                                >
                                    <Check
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    Modifications enregistrées
                                </p>
                                <Button
                                    disabled={processing}
                                    data-test="update-profile-button"
                                    className="h-11 px-5"
                                >
                                    {processing ? (
                                        <Spinner />
                                    ) : (
                                        <Save aria-hidden="true" />
                                    )}
                                    Enregistrer
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </SettingsSection>

            <SettingsSection
                icon={KeyRound}
                title="Accès et sécurité"
                description="Votre rôle et vos droits sont attribués par un administrateur ; la sécurité de connexion dépend de vous."
            >
                <div className="grid gap-3 sm:grid-cols-2">
                    <QuickLink
                        href={editSecurity().url}
                        icon={
                            account.two_factor_enabled ? ShieldCheck : ShieldOff
                        }
                        tone={
                            account.two_factor_enabled ? 'success' : 'warning'
                        }
                        title="Mot de passe et double authentification"
                        text={
                            account.two_factor_enabled
                                ? 'Double authentification active.'
                                : 'Activez la double authentification pour protéger votre compte.'
                        }
                    />
                    <QuickLink
                        href={editAppearance().url}
                        icon={Palette}
                        tone="primary"
                        title="Apparence"
                        text="Thème clair, sombre ou selon votre appareil."
                    />
                </div>
                {verified && (
                    <p className="mt-4 flex items-center gap-2 text-xs text-muted-foreground">
                        <BadgeCheck
                            className="size-4 text-success"
                            aria-hidden="true"
                        />
                        Adresse email vérifiée : elle peut servir à
                        réinitialiser votre mot de passe.
                    </p>
                )}
            </SettingsSection>

            <DeleteUser />
        </>
    );
}

function Fact({
    icon: Icon,
    label,
    children,
}: {
    icon: LucideIcon;
    label: string;
    children: ReactNode;
}) {
    return (
        <div className="flex items-center gap-3 rounded-xl border bg-muted/40 p-3">
            <span
                className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-card text-primary shadow-card"
                aria-hidden="true"
            >
                <Icon className="size-[18px]" />
            </span>
            <div className="min-w-0">
                <dt className="text-xs text-muted-foreground">{label}</dt>
                <dd className="truncate text-sm font-semibold text-foreground">
                    {children}
                </dd>
            </div>
        </div>
    );
}

function Field({
    id,
    label,
    icon: Icon,
    error,
    children,
}: {
    id: string;
    label: string;
    icon: LucideIcon;
    error?: string;
    children: ReactNode;
}) {
    return (
        <div className="grid content-start gap-2">
            <Label htmlFor={id} className="text-sm font-semibold">
                {label}
            </Label>
            <div className="relative">
                <Icon
                    className="pointer-events-none absolute top-1/2 left-3.5 z-10 size-5 -translate-y-1/2 text-muted-foreground"
                    aria-hidden="true"
                />
                {children}
            </div>
            {error && (
                <p className="text-sm font-medium text-danger-foreground">
                    {error}
                </p>
            )}
        </div>
    );
}

function QuickLink({
    href,
    icon: Icon,
    tone,
    title,
    text,
}: {
    href: string;
    icon: LucideIcon;
    tone: 'success' | 'warning' | 'primary';
    title: string;
    text: string;
}) {
    const tones = {
        success: 'bg-success-soft text-success',
        warning: 'bg-warning-soft text-warning',
        primary: 'bg-accent text-primary',
    };
    return (
        <Link
            href={href}
            className="group flex items-center gap-3 rounded-xl border bg-card p-4 transition-all hover:-translate-y-0.5 hover:border-primary/40 hover:shadow-elevated focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
        >
            <span
                className={cn(
                    'flex size-10 shrink-0 items-center justify-center rounded-xl',
                    tones[tone],
                )}
                aria-hidden="true"
            >
                <Icon className="size-5" />
            </span>
            <span className="min-w-0 flex-1">
                <span className="block font-semibold text-foreground group-hover:text-primary">
                    {title}
                </span>
                <span className="block text-xs text-muted-foreground">
                    {text}
                </span>
            </span>
            <ChevronRight
                className="size-4 shrink-0 text-muted-foreground group-hover:text-primary"
                aria-hidden="true"
            />
        </Link>
    );
}

Profile.layout = {
    breadcrumbs: [{ title: 'Mon profil', href: edit() }],
};
