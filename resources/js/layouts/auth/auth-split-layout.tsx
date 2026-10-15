import { Link, usePage } from '@inertiajs/react';
import { FolderCheck, ShieldCheck, Users } from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import { CampusIllustration } from '@/components/illustrations';
import { ThemeToggle } from '@/components/theme-toggle';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

const highlights = [
    {
        icon: Users,
        title: 'Étudiants déclarés',
        text: 'Listes des inscrits transmises par chaque établissement.',
    },
    {
        icon: FolderCheck,
        title: 'Dossiers de bourse',
        text: 'Réception, vérification et décision des dossiers papier.',
    },
    {
        icon: ShieldCheck,
        title: 'Accès tracé',
        text: 'Chaque action sensible est journalisée et contrôlée.',
    },
];

/**
 * Sign-in screens: navy institutional panel with the campus illustration on large screens,
 * form column on the right (alone on mobile). Theme switch stays reachable before login.
 */
export default function AuthSplitLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    const { name } = usePage().props;

    return (
        <div className="grid min-h-dvh bg-background lg:grid-cols-[minmax(0,1.05fr)_minmax(0,1fr)]">
            <aside className="relative hidden overflow-hidden bg-sidebar text-white lg:flex lg:flex-col">
                <div
                    className="bg-grid absolute inset-0 text-white opacity-40"
                    aria-hidden="true"
                />
                <div
                    className="absolute -top-32 -right-24 size-96 rounded-full bg-primary/40 blur-3xl"
                    aria-hidden="true"
                />
                <div
                    className="absolute -bottom-40 -left-24 size-96 rounded-full bg-brand-gold/20 blur-3xl"
                    aria-hidden="true"
                />
                <div className="relative z-10 flex h-full flex-col p-10 xl:p-14">
                    <Link href={home()} className="flex items-center gap-3">
                        <span className="flex size-11 items-center justify-center rounded-xl bg-white/10 ring-1 ring-white/20">
                            <AppLogoIcon className="size-8 fill-current text-brand-gold" />
                        </span>
                        <span className="leading-tight">
                            <span className="block font-heading text-lg font-extrabold">
                                {name}
                            </span>
                            <span className="block text-sm text-white/60">
                                Université de Mahajanga
                            </span>
                        </span>
                    </Link>

                    <div className="my-auto max-w-lg py-10">
                        <CampusIllustration className="mb-10 h-44 w-auto text-white/90" />
                        <h2 className="font-heading text-3xl leading-tight font-extrabold xl:text-4xl">
                            Scolarité centrale &amp; bourses nationales
                        </h2>
                        <p className="mt-3 text-base text-white/70">
                            La plateforme interne qui centralise les étudiants
                            inscrits dans les établissements et accompagne le
                            traitement des demandes de bourse.
                        </p>
                        <ul className="mt-8 space-y-4">
                            {highlights.map(
                                ({ icon: Icon, title: label, text }) => (
                                    <li key={label} className="flex gap-3">
                                        <span className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-white/10 text-brand-gold ring-1 ring-white/10">
                                            <Icon
                                                className="size-5"
                                                aria-hidden="true"
                                            />
                                        </span>
                                        <span>
                                            <span className="block font-semibold">
                                                {label}
                                            </span>
                                            <span className="block text-sm text-white/60">
                                                {text}
                                            </span>
                                        </span>
                                    </li>
                                ),
                            )}
                        </ul>
                    </div>

                    <p className="text-xs text-white/50">
                        © {new Date().getFullYear()} Université de Mahajanga ·
                        Portail réservé au personnel autorisé
                    </p>
                </div>
            </aside>

            <main className="relative flex flex-col px-6 py-8 sm:px-10">
                <div className="flex items-center justify-between">
                    <Link
                        href={home()}
                        className="flex items-center gap-2 lg:invisible"
                    >
                        <span className="flex size-10 items-center justify-center rounded-xl bg-sidebar">
                            <AppLogoIcon className="size-7 fill-current text-brand-gold" />
                        </span>
                        <span className="font-heading font-extrabold text-foreground">
                            {name}
                        </span>
                    </Link>
                    <ThemeToggle />
                </div>
                <div className="mx-auto flex w-full max-w-sm flex-1 flex-col justify-center py-10">
                    <div className="mb-8 space-y-2">
                        <h1 className="font-heading text-2xl font-extrabold tracking-tight text-foreground sm:text-3xl">
                            {title}
                        </h1>
                        {description && (
                            <p className="text-sm text-muted-foreground">
                                {description}
                            </p>
                        )}
                    </div>
                    {children}
                </div>
            </main>
        </div>
    );
}
