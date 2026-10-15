import { Head } from '@inertiajs/react';
import { Palette } from 'lucide-react';
import AppearanceTabs from '@/components/appearance-tabs';
import { SettingsSection } from '@/layouts/settings/layout';
import { edit as editAppearance } from '@/routes/appearance';

export default function Appearance() {
    return (
        <>
            <Head title="Paramètres d'apparence" />

            <h1 className="sr-only">Paramètres d'apparence</h1>

            <SettingsSection
                icon={Palette}
                title="Paramètres d'apparence"
                description="Modifiez les paramètres d'apparence de votre compte"
            >
                <AppearanceTabs />
                <p className="mt-5 text-xs text-muted-foreground">
                    Le choix s’applique immédiatement et reste mémorisé sur cet
                    appareil. Le mode « Système » suit automatiquement le
                    réglage clair ou sombre de votre ordinateur ou téléphone.
                </p>
            </SettingsSection>
        </>
    );
}

Appearance.layout = {
    breadcrumbs: [
        {
            title: "Paramètres d'apparence",
            href: editAppearance(),
        },
    ],
};
