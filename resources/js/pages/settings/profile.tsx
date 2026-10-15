import { Form, Head, Link, usePage } from '@inertiajs/react';
import { MailWarning, UserRound } from 'lucide-react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/delete-user';
import InputError from '@/components/input-error';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useInitials } from '@/hooks/use-initials';
import { SettingsSection } from '@/layouts/settings/layout';
import { edit } from '@/routes/profile';
import type { Auth } from '@/types';
import { send } from '@/routes/verification';

type PageProps = {
    auth: Auth;
};

export default function Profile({
    mustVerifyEmail,
    status,
}: {
    mustVerifyEmail: boolean;
    status?: string;
}) {
    const { auth } = usePage<PageProps>().props;
    const getInitials = useInitials();

    return (
        <>
            <Head title="Paramètres du profil" />

            <h1 className="sr-only">Paramètres du profil</h1>

            <SettingsSection
                icon={UserRound}
                title="Profil"
                description="Modifiez votre nom et votre adresse email"
            >
                <div className="mb-6 flex items-center gap-4 rounded-xl bg-muted/50 p-4">
                    <Avatar className="size-12 rounded-xl">
                        <AvatarFallback className="rounded-xl bg-primary font-heading text-base font-bold text-primary-foreground">
                            {getInitials(auth.user.name)}
                        </AvatarFallback>
                    </Avatar>
                    <div className="min-w-0">
                        <p className="truncate font-semibold text-foreground">
                            {auth.user.name}
                        </p>
                        <p className="truncate text-sm text-muted-foreground">
                            {auth.user.email}
                        </p>
                    </div>
                </div>

                <Form
                    {...ProfileController.update.form()}
                    options={{
                        preserveScroll: true,
                    }}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-5 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="name">Nom</Label>

                                    <Input
                                        id="name"
                                        className="h-10"
                                        defaultValue={auth.user.name}
                                        name="name"
                                        required
                                        autoComplete="name"
                                        placeholder="Nom complet"
                                    />

                                    <InputError message={errors.name} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="email">Adresse email</Label>

                                    <Input
                                        id="email"
                                        type="email"
                                        className="h-10"
                                        defaultValue={auth.user.email}
                                        name="email"
                                        required
                                        autoComplete="username"
                                        placeholder="Adresse email"
                                    />

                                    <InputError message={errors.email} />
                                </div>
                            </div>

                            {mustVerifyEmail &&
                                auth.user.email_verified_at === null && (
                                    <div className="flex items-start gap-3 rounded-lg border border-warning/30 bg-warning-soft px-4 py-3 text-sm text-warning-foreground">
                                        <MailWarning
                                            className="mt-0.5 size-4 shrink-0"
                                            aria-hidden="true"
                                        />
                                        <div className="space-y-1">
                                            <p>
                                                Votre adresse email n'est pas
                                                vérifiée.{' '}
                                                <Link
                                                    href={send()}
                                                    as="button"
                                                    className="rounded-sm font-semibold underline underline-offset-4 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                                >
                                                    Cliquez ici pour renvoyer
                                                    l'email de vérification.
                                                </Link>
                                            </p>

                                            {status ===
                                                'verification-link-sent' && (
                                                <p
                                                    role="status"
                                                    className="font-medium"
                                                >
                                                    Un nouveau lien de
                                                    vérification a été envoyé à
                                                    votre adresse email.
                                                </p>
                                            )}
                                        </div>
                                    </div>
                                )}

                            <div className="flex items-center justify-end gap-4 border-t pt-5">
                                <Button
                                    disabled={processing}
                                    data-test="update-profile-button"
                                >
                                    {processing && <Spinner />}
                                    Enregistrer
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </SettingsSection>

            <DeleteUser />
        </>
    );
}

Profile.layout = {
    breadcrumbs: [
        {
            title: 'Paramètres du profil',
            href: edit(),
        },
    ],
};
