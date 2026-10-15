import { Form } from '@inertiajs/react';
import { ShieldCheck, ShieldOff, Smartphone } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { ToneBadge } from '@/components/tone-badge';
import TwoFactorRecoveryCodes from '@/components/two-factor-recovery-codes';
import TwoFactorSetupModal from '@/components/two-factor-setup-modal';
import { Button } from '@/components/ui/button';
import { useTwoFactorAuth } from '@/hooks/use-two-factor-auth';
import { SettingsSection } from '@/layouts/settings/layout';
import { disable, enable } from '@/routes/two-factor';

export type Props = {
    canManageTwoFactor?: boolean;
    requiresConfirmation?: boolean;
    twoFactorEnabled?: boolean;
};

export default function ManageTwoFactor(props: Props) {
    const requiresConfirmation = props.requiresConfirmation ?? false;
    const twoFactorEnabled = props.twoFactorEnabled ?? false;

    const {
        qrCodeSvg,
        hasSetupData,
        manualSetupKey,
        clearSetupData,
        clearTwoFactorAuthData,
        fetchSetupData,
        recoveryCodesList,
        fetchRecoveryCodes,
        errors,
    } = useTwoFactorAuth();
    const [showSetupModal, setShowSetupModal] = useState<boolean>(false);
    const prevTwoFactorEnabled = useRef(twoFactorEnabled);

    useEffect(() => {
        if (prevTwoFactorEnabled.current && !twoFactorEnabled) {
            clearTwoFactorAuthData();
        }

        prevTwoFactorEnabled.current = twoFactorEnabled;
    }, [twoFactorEnabled, clearTwoFactorAuthData]);

    if (!(props.canManageTwoFactor ?? false)) {
        return null;
    }

    return (
        <SettingsSection
            icon={ShieldCheck}
            title="Double authentification"
            description="Gérez les paramètres de la double authentification"
            meta={
                twoFactorEnabled ? (
                    <ToneBadge tone="success">Activée</ToneBadge>
                ) : (
                    <ToneBadge tone="neutral">Désactivée</ToneBadge>
                )
            }
        >
            {twoFactorEnabled ? (
                <div className="space-y-5">
                    <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div className="flex items-start gap-3">
                            <Smartphone
                                className="mt-0.5 size-4 shrink-0 text-success"
                                aria-hidden="true"
                            />
                            <p className="text-sm text-muted-foreground">
                                Un code sécurisé et aléatoire vous sera demandé
                                lors de la connexion. Vous pourrez l'obtenir
                                depuis l'application compatible TOTP installée
                                sur votre téléphone.
                            </p>
                        </div>

                        <Form {...disable.form()} className="shrink-0">
                            {({ processing }) => (
                                <Button
                                    variant="outline"
                                    type="submit"
                                    disabled={processing}
                                    className="border-danger/40 text-danger-foreground hover:bg-danger-soft hover:text-danger-foreground"
                                >
                                    <ShieldOff aria-hidden="true" />
                                    Désactiver la 2FA
                                </Button>
                            )}
                        </Form>
                    </div>

                    <TwoFactorRecoveryCodes
                        recoveryCodesList={recoveryCodesList}
                        fetchRecoveryCodes={fetchRecoveryCodes}
                        errors={errors}
                    />
                </div>
            ) : (
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-start gap-3">
                        <Smartphone
                            className="mt-0.5 size-4 shrink-0 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <p className="text-sm text-muted-foreground">
                            Lorsque vous activez la double authentification, un
                            code sécurisé vous est demandé lors de la connexion.
                            Ce code peut être obtenu depuis une application
                            compatible TOTP installée sur votre téléphone.
                        </p>
                    </div>

                    <div className="shrink-0">
                        {hasSetupData ? (
                            <Button onClick={() => setShowSetupModal(true)}>
                                <ShieldCheck aria-hidden="true" />
                                Poursuivre la configuration
                            </Button>
                        ) : (
                            <Form
                                {...enable.form()}
                                onSuccess={() => setShowSetupModal(true)}
                            >
                                {({ processing }) => (
                                    <Button type="submit" disabled={processing}>
                                        <ShieldCheck aria-hidden="true" />
                                        Activer la 2FA
                                    </Button>
                                )}
                            </Form>
                        )}
                    </div>
                </div>
            )}

            <TwoFactorSetupModal
                isOpen={showSetupModal}
                onClose={() => setShowSetupModal(false)}
                requiresConfirmation={requiresConfirmation}
                twoFactorEnabled={twoFactorEnabled}
                qrCodeSvg={qrCodeSvg}
                manualSetupKey={manualSetupKey}
                clearSetupData={clearSetupData}
                fetchSetupData={fetchSetupData}
                errors={errors}
            />
        </SettingsSection>
    );
}
