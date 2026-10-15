import { router } from '@inertiajs/react';
import { Fingerprint, KeyRound } from 'lucide-react';
import { destroy } from '@/actions/Laravel/Passkeys/Http/Controllers/PasskeyRegistrationController';
import PasskeyItem from '@/components/passkey-item';
import PasskeyRegistration from '@/components/passkey-register';
import { ToneBadge } from '@/components/tone-badge';
import { SettingsSection } from '@/layouts/settings/layout';
import type { Passkey } from '@/types/auth';

export type Props = {
    canManagePasskeys?: boolean;
    passkeys?: Passkey[];
};

const EmptyState = () => {
    return (
        <div className="px-6 py-8 text-center">
            <div
                className="mx-auto mb-4 flex size-14 items-center justify-center rounded-2xl bg-accent text-primary ring-4 ring-accent/50"
                aria-hidden="true"
            >
                <KeyRound className="size-7" />
            </div>
            <p className="font-heading font-bold text-foreground">
                Aucune clé d'accès pour le moment
            </p>
            <p className="mt-1 text-sm text-muted-foreground">
                Ajoutez une clé d'accès pour vous connecter sans mot de passe
            </p>
        </div>
    );
};

export default function ManagePasskeys(props: Props) {
    const passkeys = props.passkeys ?? [];

    const handleDelete = (id: number, onError: () => void) => {
        router.delete(destroy.url(id), {
            preserveScroll: true,
            onError,
        });
    };

    const handleRegisterSuccess = () => {
        router.reload();
    };

    if (!(props.canManagePasskeys ?? false)) {
        return null;
    }

    return (
        <SettingsSection
            icon={Fingerprint}
            title="Clés d'accès"
            description="Gérez vos clés d'accès pour vous connecter sans mot de passe"
            meta={
                passkeys.length > 0 ? (
                    <ToneBadge tone="primary">
                        {passkeys.length} {passkeys.length > 1 ? 'clés' : 'clé'}
                    </ToneBadge>
                ) : undefined
            }
        >
            <div className="mb-4 overflow-hidden rounded-xl border bg-card">
                {passkeys.length > 0 ? (
                    passkeys.map((passkey) => (
                        <PasskeyItem
                            key={passkey.id}
                            passkey={passkey}
                            onDelete={handleDelete}
                        />
                    ))
                ) : (
                    <EmptyState />
                )}
            </div>

            <PasskeyRegistration onSuccess={handleRegisterSuccess} />
        </SettingsSection>
    );
}
