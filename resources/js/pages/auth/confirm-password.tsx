import { Form, Head } from '@inertiajs/react';
import { LockKeyhole } from 'lucide-react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/password/confirm';
import {
    index as confirmOptions,
    store as confirmStore,
} from '@/actions/Laravel/Passkeys/Http/Controllers/PasskeyConfirmationController';
import PasskeyVerify from '@/components/passkey-verify';

export default function ConfirmPassword() {
    return (
        <>
            <Head title="Confirmer le mot de passe" />

            <div className="mb-6 flex items-start gap-3 rounded-lg border border-info/30 bg-info-soft px-4 py-3 text-sm text-info-foreground">
                <LockKeyhole
                    className="mt-0.5 size-4 shrink-0"
                    aria-hidden="true"
                />
                <p>
                    Par sécurité, cette confirmation est demandée avant toute
                    opération sensible.
                </p>
            </div>

            <PasskeyVerify
                routes={{
                    options: confirmOptions(),
                    submit: confirmStore(),
                }}
                label="Confirmer avec une clé d'accès"
                loadingLabel="Confirmation..."
                separator="Ou confirmer avec le mot de passe"
            />

            <Form {...store.form()} resetOnSuccess={['password']}>
                {({ processing, errors }) => (
                    <div className="grid gap-6">
                        <div className="grid gap-2">
                            <Label htmlFor="password">Mot de passe</Label>
                            <PasswordInput
                                id="password"
                                name="password"
                                placeholder="Mot de passe"
                                autoComplete="current-password"
                                autoFocus
                                className="h-11"
                            />

                            <InputError message={errors.password} />
                        </div>

                        <Button
                            className="h-11 w-full text-base font-semibold"
                            disabled={processing}
                            data-test="confirm-password-button"
                        >
                            {processing && <Spinner />}
                            Confirmer le mot de passe
                        </Button>
                    </div>
                )}
            </Form>
        </>
    );
}

ConfirmPassword.layout = {
    title: 'Confirmer le mot de passe',
    description:
        "Cette zone de l'application est sécurisée. Veuillez confirmer votre mot de passe avant de continuer.",
};
