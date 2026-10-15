import { Form, Head } from '@inertiajs/react';
import { LogOut, MailCheck, Send } from 'lucide-react';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

export default function VerifyEmail({ status }: { status?: string }) {
    return (
        <>
            <Head title="Vérification de l'adresse email" />

            <div className="mb-6 flex items-center gap-4 rounded-xl border bg-card p-4 shadow-card">
                <span
                    className="flex size-12 shrink-0 items-center justify-center rounded-xl bg-accent text-primary"
                    aria-hidden="true"
                >
                    <MailCheck className="size-6" />
                </span>
                <p className="text-sm text-muted-foreground">
                    Ouvrez votre messagerie et cliquez sur le lien reçu. Pensez
                    à vérifier le dossier des courriers indésirables.
                </p>
            </div>

            {status === 'verification-link-sent' && (
                <div
                    role="status"
                    className="mb-6 rounded-lg border border-success/30 bg-success-soft px-4 py-3 text-sm font-medium text-success-foreground"
                >
                    Un nouveau lien de vérification a été envoyé à l'adresse
                    email indiquée lors de la création de votre compte.
                </div>
            )}

            <Form {...send.form()} className="grid gap-5 text-center">
                {({ processing }) => (
                    <>
                        <Button
                            disabled={processing}
                            className="h-11 w-full text-base font-semibold"
                        >
                            {processing ? (
                                <Spinner />
                            ) : (
                                <Send aria-hidden="true" />
                            )}
                            Renvoyer l'email de vérification
                        </Button>

                        <TextLink
                            href={logout()}
                            className="mx-auto inline-flex items-center gap-1.5 text-sm"
                        >
                            <LogOut className="size-4" aria-hidden="true" />
                            Se déconnecter
                        </TextLink>
                    </>
                )}
            </Form>
        </>
    );
}

VerifyEmail.layout = {
    title: "Vérification de l'adresse email",
    description:
        'Veuillez vérifier votre adresse email en cliquant sur le lien que nous venons de vous envoyer.',
};
