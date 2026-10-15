import { Form, Head } from '@inertiajs/react';
import { ArrowLeft, MailCheck } from 'lucide-react';
import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { email } from '@/routes/password';

export default function ForgotPassword({ status }: { status?: string }) {
    return (
        <>
            <Head title="Mot de passe oublié" />

            {status && (
                <div
                    role="status"
                    className="mb-6 flex items-start gap-3 rounded-lg border border-success/30 bg-success-soft px-4 py-3 text-sm font-medium text-success-foreground"
                >
                    <MailCheck
                        className="mt-0.5 size-4 shrink-0"
                        aria-hidden="true"
                    />
                    <span>{status}</span>
                </div>
            )}

            <div className="space-y-8">
                <Form {...email.form()} className="grid gap-6">
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="email">Adresse email</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    autoComplete="off"
                                    autoFocus
                                    placeholder="vous@exemple.mg"
                                    className="h-11"
                                />
                                <InputError message={errors.email} />
                            </div>

                            <Button
                                className="h-11 w-full text-base font-semibold"
                                disabled={processing}
                                data-test="email-password-reset-link-button"
                            >
                                {processing && <Spinner />}
                                Envoyer le lien de réinitialisation
                            </Button>
                        </>
                    )}
                </Form>

                <p className="flex items-center justify-center gap-1.5 text-sm text-muted-foreground">
                    <ArrowLeft className="size-4" aria-hidden="true" />
                    <span>Revenir à la</span>
                    <TextLink href={login()}>connexion</TextLink>
                </p>
            </div>
        </>
    );
}

ForgotPassword.layout = {
    title: 'Mot de passe oublié',
    description:
        'Saisissez votre adresse email pour recevoir un lien de réinitialisation du mot de passe',
};
