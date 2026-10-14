import { Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Panel } from '@/components/reporting/shared';
import { formatDate } from '@/components/scholarships/shared';
import { Button } from '@/components/ui/button';

type Detail = {
    id: number;
    at: string | null;
    actor: string;
    role: string | null;
    module_label: string;
    event_label: string;
    subject_type: string | null;
    subject_id: number | string | null;
    metadata: { key: string; value: string }[];
    changes: {
        field: string;
        old: string | null;
        new: string | null;
        hidden: boolean;
    }[];
};

export default function AuditShow({ activity }: { activity: Detail }) {
    const rows: [string, string][] = [
        ['Date et heure', formatDate(activity.at, true)],
        ['Acteur', activity.actor],
        ['Rôle', activity.role ?? '—'],
        ['Module', activity.module_label],
        ['Événement', activity.event_label],
        [
            'Sujet',
            activity.subject_type
                ? `${activity.subject_type} n° ${activity.subject_id}`
                : '—',
        ],
    ];
    return (
        <div className="space-y-6 p-4 md:p-6">
            <Head title={`Audit n° ${activity.id}`} />
            <div className="flex flex-wrap items-start justify-between gap-3">
                <Heading
                    title={`Entrée d’audit n° ${activity.id}`}
                    description="Seules les métadonnées administratives autorisées sont affichées."
                />
                <Button variant="outline" asChild>
                    <Link href="/audits">Retour au journal</Link>
                </Button>
            </div>
            <div className="grid gap-4 lg:grid-cols-2">
                <Panel title="Événement">
                    <dl className="grid gap-3 text-sm sm:grid-cols-2">
                        {rows.map(([label, value]) => (
                            <div key={label}>
                                <dt className="text-muted-foreground">
                                    {label}
                                </dt>
                                <dd className="break-words">{value}</dd>
                            </div>
                        ))}
                    </dl>
                </Panel>
                <Panel title="Métadonnées administratives">
                    {activity.metadata.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            Aucune métadonnée affichable.
                        </p>
                    ) : (
                        <dl className="grid gap-3 text-sm sm:grid-cols-2">
                            {activity.metadata.map((item) => (
                                <div key={item.key}>
                                    <dt className="font-mono text-xs text-muted-foreground">
                                        {item.key}
                                    </dt>
                                    <dd className="break-words">
                                        {item.value}
                                    </dd>
                                </div>
                            ))}
                        </dl>
                    )}
                </Panel>
            </div>
            {activity.changes.length > 0 && (
                <Panel
                    title="Champs modifiés"
                    description="Les valeurs des champs non administratifs restent masquées."
                >
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[30rem] text-sm">
                            <thead className="text-left text-muted-foreground">
                                <tr>
                                    <th
                                        scope="col"
                                        className="py-2 pr-3 font-medium"
                                    >
                                        Champ
                                    </th>
                                    <th
                                        scope="col"
                                        className="py-2 pr-3 font-medium"
                                    >
                                        Avant
                                    </th>
                                    <th
                                        scope="col"
                                        className="py-2 font-medium"
                                    >
                                        Après
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {activity.changes.map((change) => (
                                    <tr key={change.field}>
                                        <td className="py-2 pr-3 font-mono text-xs">
                                            {change.field}
                                        </td>
                                        <td className="py-2 pr-3">
                                            {change.hidden
                                                ? 'Valeur masquée'
                                                : (change.old ?? '—')}
                                        </td>
                                        <td className="py-2">
                                            {change.hidden
                                                ? 'Valeur masquée'
                                                : (change.new ?? '—')}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Panel>
            )}
        </div>
    );
}

AuditShow.layout = {
    breadcrumbs: [{ title: 'Audit', href: '/audits' }],
};
