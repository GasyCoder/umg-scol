import type { UrlMethodPair } from '@inertiajs/core';
import { router } from '@inertiajs/react';
import { usePasskeyVerify } from '@laravel/passkeys/react';
import { KeyRound } from 'lucide-react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';

type Props = {
    routes?: {
        options: UrlMethodPair;
        submit: UrlMethodPair;
    };
    label?: string;
    loadingLabel?: string;
    separator?: string;
};

export default function PasskeyVerify({
    routes,
    label,
    loadingLabel,
    separator,
}: Props = {}) {
    const { verify, isLoading, error, isSupported } = usePasskeyVerify({
        ...(routes && {
            routes: {
                options: routes.options.url,
                submit: routes.submit.url,
            },
        }),
        onSuccess: (response) => {
            router.visit(response.redirect ?? '/dashboard');
        },
    });

    if (!isSupported) {
        return null;
    }

    return (
        <>
            <div className="grid gap-2">
                <Button
                    type="button"
                    variant="outline"
                    className="h-11 w-full font-semibold"
                    onClick={verify}
                    disabled={isLoading}
                >
                    {isLoading ? (
                        <Spinner />
                    ) : (
                        <KeyRound className="size-4" aria-hidden="true" />
                    )}
                    {isLoading
                        ? (loadingLabel ?? 'Authentification...')
                        : (label ?? "Se connecter avec une clé d'accès")}
                </Button>
                {error && (
                    <InputError message={error} className="text-center" />
                )}
            </div>

            <div className="my-6 flex items-center gap-3">
                <span className="h-px flex-1 bg-border" aria-hidden="true" />
                <span className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                    {separator ?? 'Ou continuer avec votre adresse email'}
                </span>
                <span className="h-px flex-1 bg-border" aria-hidden="true" />
            </div>
        </>
    );
}
