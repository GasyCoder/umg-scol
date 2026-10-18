import { useEffect, useRef, useState } from 'react';
import type { FormEvent } from 'react';
import { REGEXP_ONLY_DIGITS } from 'input-otp';
import { toast } from 'sonner';

import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
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
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';
import { api } from './api';
import type { Session } from './api';
import { BirthDateField } from './birth-date-field';

// Questions drawn by the server from the student's own record (never their values).
type IdentityQuestion = {
    id: string;
    kind: 'text' | 'choice';
    label: string;
    hint: string | null;
    input: 'text' | 'tel' | 'numeric';
    max_length: number;
    options?: { value: string; label: string }[];
};
const steps = ['Email', 'Code reçu', 'Naissance', 'Identité'];
// Same shape as the server check: something@domain.tld, no spaces.
const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;

export function StudentAccess({
    session,
    onComplete,
    supportEmail,
}: {
    session: Session;
    onComplete: () => Promise<void>;
    supportEmail: string | null;
}) {
    const [email, setEmail] = useState('');
    const [challenge, setChallenge] = useState('');
    const [code, setCode] = useState('');
    const [birth, setBirth] = useState('');
    const [questions, setQuestions] = useState<IdentityQuestion[] | null>(null);
    const [answers, setAnswers] = useState<Record<string, string>>({});
    const [locked, setLocked] = useState(false);
    const [busy, setBusy] = useState(false);
    const [resendIn, setResendIn] = useState(0);
    const otpRef = useRef<HTMLInputElement>(null);
    const titleRef = useRef<HTMLDivElement>(null);
    const step = session.authenticated
        ? questions === null
            ? 2
            : 3
        : challenge
          ? 1
          : 0;
    useEffect(() => {
        titleRef.current?.focus({ preventScroll: true });
        window.scrollTo({ top: 0, behavior: 'instant' });
    }, [step]);
    useEffect(() => {
        // Ready to type (or retype after a wrong code) without tapping the boxes first.
        if (step === 1 && !busy && code === '') otpRef.current?.focus();
    }, [step, busy, code]);
    useEffect(() => {
        if (resendIn <= 0) return;
        const timer = window.setTimeout(() => setResendIn(resendIn - 1), 1000);
        return () => window.clearTimeout(timer);
    }, [resendIn]);
    async function run(action: () => Promise<void>) {
        if (busy) return;
        setBusy(true);
        try {
            await action();
        } catch (reason) {
            // Notifications of the portal are toasts, at the bottom of the screen.
            toast.error(
                reason instanceof Error
                    ? reason.message
                    : 'La demande n’a pas pu aboutir. Réessayez.',
            );
        } finally {
            setBusy(false);
        }
    }
    const sendCode = () =>
        run(async () => {
            const result = await api<{ challenge_id: string }>(
                'auth/otp/request',
                { email },
            );
            setChallenge(result.challenge_id);
            setCode('');
            setResendIn(60);
            toast.success('Code envoyé. Consultez votre boîte mail.');
        });
    // Called as soon as the sixth digit is typed or pasted: no button to press.
    const verifyCode = (value: string) =>
        run(async () => {
            try {
                await api<Session>('auth/otp/verify', {
                    challenge_id: challenge,
                    code: value,
                });
            } catch (reason) {
                setCode('');
                throw reason;
            }
            setCode('');
            setChallenge('');
            await onComplete();
        });
    function submit(event: FormEvent) {
        event.preventDefault();
        if (step === 0) {
            void sendCode();
            return;
        }
        if (step === 1) {
            if (code.length === 6) void verifyCode(code);
            return;
        }
        void run(async () => {
            if (step === 2) {
                const result = await api<{
                    verified: boolean;
                    questions: IdentityQuestion[];
                }>('link/birth-date', { birth_date: birth });
                if (!result.verified)
                    throw new Error('Vérifiez votre date de naissance.');
                setQuestions(result.questions);
                setAnswers({});
                setLocked(false);
            } else {
                const result = await api<{
                    linked: boolean;
                    message: string;
                    remaining?: number;
                }>('link', { birth_date: birth, answers });
                if (!result.linked) {
                    setLocked(result.remaining === 0);
                    throw new Error(result.message);
                }
                setAnswers({});
                setBirth('');
                toast.success(result.message);
                await onComplete();
            }
        });
    }
    const emailValid = emailPattern.test(email.trim());
    const answered = questions?.length
        ? questions.every((question) => answers[question.id]?.trim())
        : false;
    const setAnswer = (id: string, next: string) =>
        setAnswers((current) => ({ ...current, [id]: next }));
    const content = [
        {
            title: 'Connexion étudiante',
            description: 'Utilisez l’email enregistré par votre établissement.',
        },
        {
            title: 'Vérifiez votre email',
            description: (
                <>
                    Code à 6 chiffres envoyé à{' '}
                    <span className="font-medium break-all text-foreground">
                        {email.trim()}
                    </span>
                </>
            ),
        },
        {
            title: 'Votre date de naissance',
            description: 'Celle déclarée à votre établissement.',
        },
        {
            title: 'Dernière vérification',
            description:
                questions?.length === 0
                    ? 'Votre fiche ne permet pas encore cette vérification.'
                    : questions && questions.length > 1
                      ? 'Répondez à ces questions sur votre fiche.'
                      : 'Répondez à cette question sur votre fiche.',
        },
    ][step];
    return (
        <div className="w-full space-y-6">
            {/* Plein écran sur téléphone, carte shadcn à partir de sm. */}
            <Card className="gap-6 border-0 bg-transparent py-0 shadow-none sm:border sm:bg-card sm:py-8 sm:shadow-sm">
                <CardHeader className="gap-2 px-0 sm:px-8">
                    <div
                        className="mb-3 flex items-center gap-3"
                        aria-label={`Étape ${step + 1} sur 4 : ${steps[step]}`}
                    >
                        <div className="flex flex-1 gap-1.5" aria-hidden="true">
                            {steps.map((label, index) => (
                                <span
                                    key={label}
                                    className={cn(
                                        'h-1 flex-1 rounded-full',
                                        index <= step
                                            ? 'bg-primary'
                                            : 'bg-border',
                                    )}
                                />
                            ))}
                        </div>
                        <span className="shrink-0 text-xs text-muted-foreground">
                            Étape {step + 1} sur 4
                        </span>
                    </div>
                    <CardTitle
                        role="heading"
                        aria-level={1}
                        ref={titleRef}
                        tabIndex={-1}
                        className="text-xl leading-tight font-semibold tracking-tight outline-none sm:text-2xl"
                    >
                        {content.title}
                    </CardTitle>
                    <CardDescription>{content.description}</CardDescription>
                </CardHeader>
                <CardContent className="px-0 sm:px-8">
                    <form
                        onSubmit={submit}
                        className="grid gap-6"
                        aria-busy={busy}
                    >
                        {step === 0 && (
                            <div className="space-y-2">
                                <Label htmlFor="student-email">
                                    Adresse email
                                </Label>
                                <Input
                                    id="student-email"
                                    className="h-12 text-base"
                                    type="email"
                                    autoComplete="email"
                                    inputMode="email"
                                    placeholder="prenom.nom@exemple.mg"
                                    value={email}
                                    onChange={(event) =>
                                        setEmail(event.target.value)
                                    }
                                    required
                                    maxLength={255}
                                    disabled={busy}
                                />
                            </div>
                        )}
                        {step === 1 && (
                            <div className="space-y-3">
                                <InputOTP
                                    ref={otpRef}
                                    id="student-otp"
                                    aria-label="Code de vérification à 6 chiffres"
                                    maxLength={6}
                                    pattern={REGEXP_ONLY_DIGITS}
                                    inputMode="numeric"
                                    autoComplete="one-time-code"
                                    value={code}
                                    onChange={setCode}
                                    onComplete={(value: string) =>
                                        void verifyCode(value)
                                    }
                                    disabled={busy}
                                    containerClassName="w-full"
                                >
                                    <InputOTPGroup className="w-full gap-2">
                                        {Array.from(
                                            { length: 6 },
                                            (_, index) => (
                                                <InputOTPSlot
                                                    key={index}
                                                    index={index}
                                                    className="h-12 max-w-14 flex-1 rounded-md border text-lg first:rounded-md last:rounded-md"
                                                />
                                            ),
                                        )}
                                    </InputOTPGroup>
                                </InputOTP>
                                <p
                                    className="flex min-h-5 items-center gap-2 text-xs text-muted-foreground"
                                    role="status"
                                >
                                    {busy ? (
                                        <>
                                            <Spinner />
                                            Vérification…
                                        </>
                                    ) : (
                                        'Valable 10 minutes.'
                                    )}
                                </p>
                            </div>
                        )}
                        {step === 2 && (
                            <BirthDateField
                                value={birth}
                                onChange={setBirth}
                                disabled={busy}
                            />
                        )}
                        {step === 3 && questions?.length === 0 && (
                            <Alert>
                                <AlertTitle>Fiche à compléter</AlertTitle>
                                <AlertDescription>
                                    Contactez votre scolarité pour compléter les
                                    informations manquantes.
                                </AlertDescription>
                            </Alert>
                        )}
                        {step === 3 &&
                            questions?.map((question) =>
                                question.kind === 'choice' ? (
                                    <fieldset
                                        key={question.id}
                                        className="space-y-3"
                                    >
                                        <legend className="mb-3 text-sm font-medium">
                                            {question.label}
                                        </legend>
                                        <RadioGroup
                                            value={answers[question.id] ?? ''}
                                            onValueChange={(next) =>
                                                setAnswer(question.id, next)
                                            }
                                            disabled={busy || locked}
                                            className="grid gap-2 sm:grid-cols-2"
                                        >
                                            {question.options?.map((option) => (
                                                <Label
                                                    key={option.value}
                                                    htmlFor={`${question.id}-${option.value}`}
                                                    className="flex min-h-12 cursor-pointer items-center gap-3 rounded-lg border p-3 font-normal has-[[data-state=checked]]:border-primary has-[[data-state=checked]]:bg-accent/40"
                                                >
                                                    <RadioGroupItem
                                                        id={`${question.id}-${option.value}`}
                                                        value={option.value}
                                                    />
                                                    <span className="text-sm">
                                                        {option.label}
                                                    </span>
                                                </Label>
                                            ))}
                                        </RadioGroup>
                                    </fieldset>
                                ) : (
                                    <div
                                        key={question.id}
                                        className="space-y-2"
                                    >
                                        <Label
                                            htmlFor={`identity-${question.id}`}
                                        >
                                            {question.label}
                                        </Label>
                                        <Input
                                            id={`identity-${question.id}`}
                                            className="h-12 text-base"
                                            type={
                                                question.input === 'tel'
                                                    ? 'tel'
                                                    : 'text'
                                            }
                                            inputMode={question.input}
                                            value={answers[question.id] ?? ''}
                                            onChange={(event) =>
                                                setAnswer(
                                                    question.id,
                                                    event.target.value,
                                                )
                                            }
                                            autoComplete="off"
                                            maxLength={question.max_length}
                                            required
                                            disabled={busy || locked}
                                            aria-describedby={
                                                question.hint
                                                    ? `identity-${question.id}-hint`
                                                    : undefined
                                            }
                                        />
                                        {question.hint && (
                                            <p
                                                id={`identity-${question.id}-hint`}
                                                className="text-xs leading-relaxed text-muted-foreground"
                                            >
                                                {question.hint}
                                            </p>
                                        )}
                                    </div>
                                ),
                            )}
                        {step !== 1 &&
                            (step !== 3 || Boolean(questions?.length)) && (
                                <Button
                                    type="submit"
                                    className="h-12 w-full text-base"
                                    disabled={
                                        busy ||
                                        (step === 0 && !emailValid) ||
                                        (step === 2 && !birth) ||
                                        (step === 3 && (!answered || locked))
                                    }
                                >
                                    {busy ? (
                                        <>
                                            <Spinner />
                                            {step === 0
                                                ? 'Envoi du code…'
                                                : 'Vérification…'}
                                        </>
                                    ) : (
                                        <>
                                            {
                                                [
                                                    'Recevoir un code',
                                                    'Valider le code',
                                                    'Continuer',
                                                    'Accéder à ma fiche',
                                                ][step]
                                            }
                                        </>
                                    )}
                                </Button>
                            )}
                        {step === 1 && (
                            <div className="-mt-2 flex items-center justify-between gap-3 text-sm">
                                <Button
                                    type="button"
                                    variant="link"
                                    className="h-10 px-0 text-muted-foreground"
                                    disabled={busy}
                                    onClick={() => {
                                        setChallenge('');
                                        setCode('');
                                    }}
                                >
                                    Modifier l’email
                                </Button>
                                <Button
                                    type="button"
                                    variant="link"
                                    className="h-10 px-0 tabular-nums"
                                    disabled={busy || resendIn > 0}
                                    onClick={() => void sendCode()}
                                >
                                    {resendIn > 0
                                        ? `Renvoyer (${resendIn} s)`
                                        : 'Renvoyer le code'}
                                </Button>
                            </div>
                        )}
                        {step === 3 && (
                            <Button
                                type="button"
                                variant="ghost"
                                className="h-11 w-full text-sm text-muted-foreground"
                                disabled={busy}
                                onClick={() => {
                                    setQuestions(null);
                                    setAnswers({});
                                }}
                            >
                                Modifier la date de naissance
                            </Button>
                        )}
                    </form>
                </CardContent>
            </Card>
            <p className="text-center text-sm text-muted-foreground">
                Un problème avec votre fiche ?{' '}
                {supportEmail ? (
                    <a
                        href={`mailto:${supportEmail}`}
                        className="font-medium text-foreground underline underline-offset-4"
                    >
                        Contactez la scolarité
                    </a>
                ) : (
                    'Contactez la scolarité.'
                )}
            </p>
        </div>
    );
}
