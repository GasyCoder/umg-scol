import { Head } from '@inertiajs/react';
import AppearanceTabs from '@/components/appearance-tabs';
import Heading from '@/components/heading';
import { edit as editAppearance } from '@/routes/appearance';

export default function Appearance() {
    return (
        <>
            <Head title="Paramètres d'apparence" />

            <h1 className="sr-only">Paramètres d'apparence</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Paramètres d'apparence"
                    description="Modifiez les paramètres d'apparence de votre compte"
                />
                <AppearanceTabs />
            </div>
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
