import { Form, Head, setLayoutProps } from '@inertiajs/react';
import { REGEXP_ONLY_DIGITS } from 'input-otp';
import { KeyRound, Smartphone } from 'lucide-react';
import { useMemo, useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import { OTP_MAX_LENGTH } from '@/hooks/use-two-factor-auth';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/two-factor/login';

export default function TwoFactorChallenge() {
    const [showRecoveryInput, setShowRecoveryInput] = useState<boolean>(false);
    const [code, setCode] = useState<string>('');

    const authConfigContent = useMemo<{
        title: string;
        description: string;
        toggleText: string;
    }>(() => {
        if (showRecoveryInput) {
            return {
                title: 'Code de récupération',
                description:
                    "Veuillez confirmer l'accès à votre compte en saisissant l'un de vos codes de récupération d'urgence.",
                toggleText: "vous connecter avec un code d'authentification",
            };
        }

        return {
            title: "Code d'authentification",
            description:
                "Saisissez le code d'authentification fourni par votre application d'authentification.",
            toggleText: 'vous connecter avec un code de récupération',
        };
    }, [showRecoveryInput]);

    setLayoutProps({
        title: authConfigContent.title,
        description: authConfigContent.description,
    });

    const toggleRecoveryMode = (clearErrors: () => void): void => {
        setShowRecoveryInput(!showRecoveryInput);
        clearErrors();
        setCode('');
    };

    return (
        <>
            <Head title="Double authentification" />

            <div className="mb-6 flex items-center gap-3 rounded-lg border bg-card px-4 py-3 text-sm text-muted-foreground shadow-card">
                <span
                    className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-accent text-primary"
                    aria-hidden="true"
                >
                    {showRecoveryInput ? (
                        <KeyRound className="size-5" />
                    ) : (
                        <Smartphone className="size-5" />
                    )}
                </span>
                <p>
                    {showRecoveryInput
                        ? 'Chaque code de récupération ne peut servir qu’une seule fois.'
                        : 'Le code change toutes les 30 secondes dans votre application.'}
                </p>
            </div>

            <Form
                {...store.form()}
                className="grid gap-6"
                resetOnError
                resetOnSuccess={!showRecoveryInput}
            >
                {({ errors, processing, clearErrors }) => (
                    <>
                        {showRecoveryInput ? (
                            <div className="grid gap-2">
                                <Label htmlFor="recovery_code">
                                    Code de récupération
                                </Label>
                                <Input
                                    id="recovery_code"
                                    name="recovery_code"
                                    type="text"
                                    placeholder="Saisissez le code de récupération"
                                    autoFocus={showRecoveryInput}
                                    autoComplete="one-time-code"
                                    required
                                    className="h-11 font-mono tracking-wide"
                                />
                                <InputError message={errors.recovery_code} />
                            </div>
                        ) : (
                            <div className="flex flex-col items-center gap-3 text-center">
                                <InputOTP
                                    name="code"
                                    maxLength={OTP_MAX_LENGTH}
                                    value={code}
                                    onChange={(value) => setCode(value)}
                                    disabled={processing}
                                    pattern={REGEXP_ONLY_DIGITS}
                                    autoFocus
                                    aria-label="Code d'authentification à six chiffres"
                                >
                                    <InputOTPGroup className="gap-2 sm:gap-2.5">
                                        {Array.from(
                                            { length: OTP_MAX_LENGTH },
                                            (_, index) => (
                                                <InputOTPSlot
                                                    key={index}
                                                    index={index}
                                                    className="size-11 rounded-lg border bg-card font-heading text-lg font-bold shadow-card first:rounded-lg last:rounded-lg sm:size-12 sm:text-xl"
                                                />
                                            ),
                                        )}
                                    </InputOTPGroup>
                                </InputOTP>
                                <InputError message={errors.code} />
                            </div>
                        )}

                        <Button
                            type="submit"
                            className="h-11 w-full text-base font-semibold"
                            disabled={processing}
                        >
                            {processing && <Spinner />}
                            Continuer
                        </Button>

                        <p className="text-center text-sm text-muted-foreground">
                            <span>ou vous pouvez </span>
                            <button
                                type="button"
                                className="cursor-pointer rounded-sm font-medium text-primary underline decoration-primary/30 underline-offset-4 transition-colors hover:decoration-primary focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                onClick={() => toggleRecoveryMode(clearErrors)}
                            >
                                {authConfigContent.toggleText}
                            </button>
                        </p>
                    </>
                )}
            </Form>
        </>
    );
}
