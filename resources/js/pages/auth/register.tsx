import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';

type Props = {
    passwordRules: string;
};

export default function Register({ passwordRules }: Props) {
    return (
        <>
            <Head title="Créer un compte" />
            <Form
                action="/register"
                method="post"
                resetOnSuccess={['password', 'password_confirmation']}
                disableWhileProcessing
                className="flex flex-col gap-8"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-5">
                            <div className="grid gap-2">
                                <Label htmlFor="name">Nom</Label>
                                <Input
                                    id="name"
                                    type="text"
                                    required
                                    autoFocus
                                    tabIndex={1}
                                    autoComplete="name"
                                    name="name"
                                    placeholder="Nom complet"
                                    className="h-11"
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="email">Adresse email</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    required
                                    tabIndex={2}
                                    autoComplete="email"
                                    name="email"
                                    placeholder="vous@exemple.mg"
                                    className="h-11"
                                />
                                <InputError message={errors.email} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password">Mot de passe</Label>
                                <PasswordInput
                                    id="password"
                                    required
                                    tabIndex={3}
                                    autoComplete="new-password"
                                    name="password"
                                    placeholder="Mot de passe"
                                    passwordrules={passwordRules}
                                    className="h-11"
                                />
                                <InputError message={errors.password} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password_confirmation">
                                    Confirmer le mot de passe
                                </Label>
                                <PasswordInput
                                    id="password_confirmation"
                                    required
                                    tabIndex={4}
                                    autoComplete="new-password"
                                    name="password_confirmation"
                                    placeholder="Confirmer le mot de passe"
                                    passwordrules={passwordRules}
                                    className="h-11"
                                />
                                <InputError
                                    message={errors.password_confirmation}
                                />
                            </div>

                            <Button
                                type="submit"
                                className="mt-2 h-11 w-full text-base font-semibold"
                                tabIndex={5}
                                data-test="register-user-button"
                            >
                                {processing && <Spinner />}
                                Créer le compte
                            </Button>
                        </div>

                        <p className="text-center text-sm text-muted-foreground">
                            Vous avez déjà un compte ?{' '}
                            <TextLink href={login()} tabIndex={6}>
                                Se connecter
                            </TextLink>
                        </p>
                    </>
                )}
            </Form>
        </>
    );
}

Register.layout = {
    title: 'Créer un compte',
    description:
        'Saisissez vos informations ci-dessous pour créer votre compte',
};
