import { Head, Link, useForm } from '@inertiajs/react';
import {
    BadgeCheck,
    CalendarRange,
    ChartColumn,
    Database,
    Download,
    Eye,
    FileCheck2,
    FilePenLine,
    FileUp,
    FolderOpen,
    GraduationCap,
    LogIn,
    RotateCcw,
    ScrollText,
    ShieldCheck,
    SlidersHorizontal,
    Trash2,
    UserCog,
    UserRound,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { FormEvent } from 'react';
import { EmptyState } from '@/components/empty-state';
import { IconAction } from '@/components/icon-action';
import InputError from '@/components/input-error';
import { PageBody, PageHeader } from '@/components/page-header';
import { formatDate, Pagination } from '@/components/scholarships/shared';
import type { Option, PageLink } from '@/components/scholarships/shared';
import { ToneBadge } from '@/components/tone-badge';
import type { Tone } from '@/components/tone-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type Row = {
    id: number;
    uuid: string;
    at: string | null;
    actor: string;
    role: string | null;
    module?: string;
    module_label: string;
    event?: string;
    event_label: string;
    subject_type: string | null;
    subject_id: number | string | null;
};
const KEYS = ['from', 'to', 'actor', 'module', 'event', 'subject'] as const;

const moduleIcons: Record<string, LucideIcon> = {
    authentication: LogIn,
    identity: UserCog,
    access: ShieldCheck,
    references: Database,
    students: GraduationCap,
    student_records: FileCheck2,
    student_imports: FileUp,
    student_portal: UserRound,
    student_corrections: FilePenLine,
    student_confirmations: BadgeCheck,
    scholarship_campaigns: CalendarRange,
    scholarships: FolderOpen,
    audits: ScrollText,
    reports: ChartColumn,
    trash: Trash2,
};

/** Module of an audit entry: icon + label, never the icon alone. */
export function AuditModuleBadge({
    module,
    label,
}: {
    module?: string;
    label: string;
}) {
    const Icon = (module && moduleIcons[module]) || ScrollText;
    return (
        <span className="inline-flex max-w-full items-center gap-1.5 rounded-md border bg-card px-2 py-1 text-xs font-medium text-foreground">
            <Icon
                className="size-3.5 shrink-0 text-primary"
                aria-hidden="true"
            />
            <span className="truncate">{label}</span>
        </span>
    );
}

function eventTone(event?: string): Tone {
    if (!event) return 'neutral';
    if (/rejected|deleted|failed|purged|trashed|detached/.test(event))
        return 'danger';
    if (
        /approved|validated|restored|confirmed|committed|attached|created|associated/.test(
            event,
        )
    )
        return 'success';
    if (/requested|needs_correction|permissions_updated/.test(event))
        return 'warning';
    if (/exported|imported/.test(event)) return 'primary';
    return 'info';
}

/** Event of an audit entry, tinted by its nature (creation, refusal, export…). */
export function AuditEventBadge({
    event,
    label,
}: {
    event?: string;
    label: string;
}) {
    return <ToneBadge tone={eventTone(event)}>{label}</ToneBadge>;
}

export default function AuditIndex({
    activities,
    filters,
    options,
    canExport,
}: {
    activities: { data: Row[]; total: number; links: PageLink[] };
    filters: Partial<Record<(typeof KEYS)[number], string | number>>;
    options: {
        actors: { id: number; name: string }[];
        modules: Option[];
        events: Option[];
        subjects: string[];
    };
    canExport: boolean;
}) {
    const applied = (key: (typeof KEYS)[number]) =>
        filters[key] === undefined || filters[key] === null
            ? ''
            : String(filters[key]);
    const form = useForm<Record<string, string>>(
        Object.fromEntries(KEYS.map((key) => [key, applied(key)])),
    );
    const errors = form.errors as Record<string, string | undefined>;
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.get('/audits', { preserveState: true });
    };
    const query = new URLSearchParams(
        KEYS.map((key): [string, string] => [key, applied(key)]).filter(
            ([, value]) => value !== '',
        ),
    ).toString();
    const activeFilters = KEYS.filter((key) => applied(key) !== '').length;
    const selects: { key: string; label: string; items: Option[] }[] = [
        {
            key: 'actor',
            label: 'Acteur',
            items: options.actors.map((actor) => ({
                value: String(actor.id),
                label: actor.name,
            })),
        },
        { key: 'module', label: 'Module', items: options.modules },
        { key: 'event', label: 'Événement', items: options.events },
        {
            key: 'subject',
            label: 'Type de sujet',
            items: options.subjects.map((subject) => ({
                value: subject,
                label: subject,
            })),
        },
    ];
    return (
        <>
            <Head title="Audit" />
            <PageBody>
                <PageHeader
                    icon={ScrollText}
                    title="Journal d’audit"
                    description="Actions tracées sur la plateforme, tous acteurs confondus, développeurs compris. Aucune donnée personnelle n’est affichée."
                    actions={
                        canExport && (
                            <Button variant="outline" asChild>
                                <a
                                    href={`/audits/export${query ? `?${query}` : ''}`}
                                >
                                    <Download aria-hidden="true" />
                                    Exporter (CSV)
                                </a>
                            </Button>
                        )
                    }
                />
                <form
                    onSubmit={submit}
                    className="surface space-y-4 p-4 sm:p-5"
                >
                    <div className="flex items-center gap-2 text-sm font-semibold text-foreground">
                        <SlidersHorizontal
                            className="size-4 text-primary"
                            aria-hidden="true"
                        />
                        Filtres
                        {activeFilters > 0 && (
                            <span className="rounded-full bg-accent px-2 py-0.5 text-xs font-semibold text-accent-foreground tabular-nums">
                                {activeFilters} actif(s)
                            </span>
                        )}
                    </div>
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
                        <div className="grid content-start gap-1.5">
                            <Label htmlFor="from">Du</Label>
                            <Input
                                id="from"
                                type="date"
                                value={form.data.from}
                                onChange={(e) =>
                                    form.setData('from', e.target.value)
                                }
                            />
                            <InputError message={errors.from} />
                        </div>
                        <div className="grid content-start gap-1.5">
                            <Label htmlFor="to">Au</Label>
                            <Input
                                id="to"
                                type="date"
                                value={form.data.to}
                                onChange={(e) =>
                                    form.setData('to', e.target.value)
                                }
                            />
                            <InputError message={errors.to} />
                        </div>
                        {selects.map((select) => (
                            <div
                                key={select.key}
                                className="grid content-start gap-1.5"
                            >
                                <Label htmlFor={`filter-${select.key}`}>
                                    {select.label}
                                </Label>
                                <Select
                                    value={form.data[select.key] || 'all'}
                                    onValueChange={(value) =>
                                        form.setData(
                                            select.key,
                                            value === 'all' ? '' : value,
                                        )
                                    }
                                >
                                    <SelectTrigger
                                        id={`filter-${select.key}`}
                                        className="w-full"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">
                                            Tous
                                        </SelectItem>
                                        {select.items.map((item) => (
                                            <SelectItem
                                                key={item.value}
                                                value={item.value}
                                            >
                                                {item.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors[select.key]} />
                            </div>
                        ))}
                    </div>
                    <div className="flex flex-wrap items-center justify-between gap-3 border-t pt-4">
                        <p className="text-sm text-muted-foreground">
                            <span className="font-semibold text-foreground tabular-nums">
                                {activities.total}
                            </span>{' '}
                            entrée(s)
                        </p>
                        <div className="flex gap-2">
                            <Button variant="ghost" asChild>
                                <Link href="/audits">
                                    <RotateCcw aria-hidden="true" />
                                    Réinitialiser
                                </Link>
                            </Button>
                            <Button disabled={form.processing}>Filtrer</Button>
                        </div>
                    </div>
                </form>
                {activities.data.length === 0 ? (
                    <div className="surface">
                        {activeFilters > 0 ? (
                            <EmptyState
                                illustration="search"
                                title="Aucune entrée"
                                description="Aucune entrée ne correspond à ces critères."
                                action={
                                    <Button variant="outline" asChild>
                                        <Link href="/audits">
                                            Réinitialiser les filtres
                                        </Link>
                                    </Button>
                                }
                            />
                        ) : (
                            <EmptyState
                                illustration="inbox"
                                title="Journal vide"
                                description="Aucune action n’a encore été tracée."
                            />
                        )}
                    </div>
                ) : (
                    <div className="surface overflow-hidden">
                        <div className="overflow-x-auto">
                            <table className="data-table min-w-[56rem]">
                                <caption className="sr-only">
                                    Journal d’audit
                                </caption>
                                <thead>
                                    <tr>
                                        {[
                                            'Date et heure',
                                            'Événement',
                                            'Module',
                                            'Acteur',
                                            'Sujet',
                                        ].map((title) => (
                                            <th key={title} scope="col">
                                                {title}
                                            </th>
                                        ))}
                                        <th scope="col">
                                            <span className="sr-only">
                                                Actions
                                            </span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {activities.data.map((row) => (
                                        <tr key={row.id}>
                                            <td className="whitespace-nowrap text-muted-foreground tabular-nums">
                                                {formatDate(row.at, true)}
                                            </td>
                                            <td>
                                                <AuditEventBadge
                                                    event={row.event}
                                                    label={row.event_label}
                                                />
                                            </td>
                                            <td className="max-w-52">
                                                <AuditModuleBadge
                                                    module={row.module}
                                                    label={row.module_label}
                                                />
                                            </td>
                                            <td>
                                                <span className="block font-medium text-foreground">
                                                    {row.actor}
                                                </span>
                                                {row.role && (
                                                    <span className="block text-xs text-muted-foreground">
                                                        {row.role}
                                                    </span>
                                                )}
                                            </td>
                                            <td className="font-mono text-xs text-muted-foreground">
                                                {row.subject_type
                                                    ? `${row.subject_type} n° ${row.subject_id}`
                                                    : '—'}
                                            </td>
                                            <td>
                                                <div className="flex items-center justify-end gap-1">
                                                    <IconAction
                                                        icon={Eye}
                                                        label="Voir le détail de l’action"
                                                        href={`/audits/${row.uuid}`}
                                                    />
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}
                <Pagination links={activities.links} />
            </PageBody>
        </>
    );
}

AuditIndex.layout = {
    breadcrumbs: [{ title: 'Audit', href: '/audits' }],
};
