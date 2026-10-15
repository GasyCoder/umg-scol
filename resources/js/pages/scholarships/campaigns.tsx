import { Head, router, useForm, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    CalendarCheck,
    CalendarDays,
    ChevronRight,
    FolderOpen,
    GraduationCap,
    Info,
    Megaphone,
    Pencil,
    PlayCircle,
    Plus,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { ErrorSummary, FormField } from '@/components/forms/form-kit';
import { EmptyState } from '@/components/empty-state';
import { PageBody, PageHeader } from '@/components/page-header';
import { formatNumber } from '@/components/reporting/shared';
import { ToneBadge, toneFor } from '@/components/tone-badge';
import type { Tone } from '@/components/tone-badge';
import { TrashButton } from '@/components/trash/trash-button';
import {
    formatDate,
    Pagination,
    StatusBadge,
} from '@/components/scholarships/shared';
import type { Option, PageLink } from '@/components/scholarships/shared';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';

type Campaign = {
    id: number;
    uuid: string;
    name: string;
    academic_year_id: number;
    academic_year: string;
    starts_at: string | null;
    ends_at: string | null;
    notes: string | null;
    status: string;
    status_label: string;
    applications_count: number;
    targets: Option[];
};

/** Status changes worded as the action the manager performs. */
const actionLabels: Record<string, string> = {
    scheduled: 'Programmer',
    open: 'Ouvrir la réception',
    closed: 'Clôturer',
    archived: 'Archiver',
};

/** Campaign lifecycle, in order (labels as shown by the status badges). */
const lifecycle = [
    { status: 'draft', label: 'Brouillon' },
    { status: 'scheduled', label: 'Programmée' },
    { status: 'open', label: 'Ouverte' },
    { status: 'closed', label: 'Clôturée' },
    { status: 'archived', label: 'Archivée' },
];

/** Thin top accent of a campaign card, coloured by its status tone. */
const accents: Record<Tone, string> = {
    primary: 'bg-primary',
    success: 'bg-success',
    warning: 'bg-warning',
    info: 'bg-info',
    danger: 'bg-danger',
    neutral: 'bg-border',
};

export default function Campaigns({
    campaigns,
    academicYears,
    canManage,
}: {
    campaigns: { data: Campaign[]; links: PageLink[] };
    academicYears: { id: number; name: string }[];
    canManage: boolean;
}) {
    const [editing, setEditing] = useState<Campaign | 'new' | null>(null);
    const [transition, setTransition] = useState<{
        campaign: Campaign;
        target: Option;
    } | null>(null);
    const [busy, setBusy] = useState(false);
    const form = useForm({
        academic_year_id: '',
        name: '',
        starts_at: '',
        ends_at: '',
        notes: '',
    });
    const errors = form.errors as Record<string, string | undefined>;
    const pageErrors = usePage().props.errors as Record<
        string,
        string | undefined
    >;
    const open = (campaign: Campaign | 'new') => {
        form.clearErrors();
        form.setData(
            campaign === 'new'
                ? {
                      academic_year_id: '',
                      name: '',
                      starts_at: '',
                      ends_at: '',
                      notes: '',
                  }
                : {
                      academic_year_id: String(campaign.academic_year_id),
                      name: campaign.name,
                      starts_at: campaign.starts_at ?? '',
                      ends_at: campaign.ends_at ?? '',
                      notes: campaign.notes ?? '',
                  },
        );
        setEditing(campaign);
    };
    const submit = (event: FormEvent) => {
        event.preventDefault();
        const options = { onSuccess: () => setEditing(null) };
        if (editing === 'new') form.post('/scholarships/campaigns', options);
        else if (editing)
            form.put(`/scholarships/campaigns/${editing.uuid}`, options);
    };
    const openCampaigns = campaigns.data.filter(
        (campaign) => campaign.status === 'open',
    );
    return (
        <PageBody>
            <Head title="Campagnes de bourse" />
            <PageHeader
                icon={Megaphone}
                title="Campagnes de bourse"
                description="Une campagne par année universitaire encadre la réception des dossiers papier : la réception n’est possible que pendant une campagne ouverte."
                meta={
                    openCampaigns.length > 0 && (
                        <ToneBadge tone="success" className="whitespace-normal">
                            Réception ouverte :{' '}
                            {openCampaigns
                                .map((campaign) => campaign.name)
                                .join(', ')}
                        </ToneBadge>
                    )
                }
                actions={
                    canManage && (
                        <Button onClick={() => open('new')}>
                            <Plus aria-hidden="true" />
                            Nouvelle campagne
                        </Button>
                    )
                }
            />

            <section
                aria-labelledby="campaign-lifecycle"
                className="surface flex flex-col gap-3 p-4 sm:p-5 lg:flex-row lg:items-center lg:justify-between"
            >
                <div className="flex items-start gap-3">
                    <span
                        className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-info-soft text-info"
                        aria-hidden="true"
                    >
                        <Info className="size-[18px]" />
                    </span>
                    <div>
                        <h2
                            id="campaign-lifecycle"
                            className="text-sm font-bold text-foreground"
                        >
                            Cycle d’une campagne
                        </h2>
                        <p className="text-xs text-muted-foreground">
                            La réception des dossiers n’a lieu que pendant
                            l’étape « Ouverte ». Chaque étape est définitive.
                        </p>
                    </div>
                </div>
                <ol className="flex flex-wrap items-center gap-1.5 text-xs font-semibold">
                    {lifecycle.map((step, index) => (
                        <li
                            key={step.status}
                            className="flex items-center gap-1.5"
                        >
                            <ToneBadge
                                tone={toneFor(step.status)}
                                className={
                                    step.status === 'open'
                                        ? 'ring-1 ring-success/40'
                                        : undefined
                                }
                            >
                                {step.label}
                                {step.status === 'open' && (
                                    <span className="font-normal">
                                        (réception)
                                    </span>
                                )}
                            </ToneBadge>
                            {index < lifecycle.length - 1 && (
                                <ChevronRight
                                    className="size-3.5 text-muted-foreground"
                                    aria-hidden="true"
                                />
                            )}
                        </li>
                    ))}
                </ol>
            </section>

            {pageErrors.status && (
                <p
                    role="alert"
                    className="flex items-start gap-2 rounded-xl border border-danger/30 bg-danger-soft px-4 py-3 text-sm text-danger-foreground"
                >
                    <AlertTriangle
                        className="mt-0.5 size-4 shrink-0"
                        aria-hidden="true"
                    />
                    {pageErrors.status}
                </p>
            )}

            {campaigns.data.length === 0 ? (
                <div className="surface">
                    <EmptyState
                        illustration="scholarship"
                        title="Aucune campagne pour le moment."
                        description="Créez une campagne pour l’année universitaire, puis ouvrez-la pour commencer la réception des dossiers papier."
                        action={
                            canManage && (
                                <Button onClick={() => open('new')}>
                                    <Plus aria-hidden="true" />
                                    Créer la première campagne
                                </Button>
                            )
                        }
                    />
                </div>
            ) : (
                <div className="grid gap-4 md:grid-cols-2 2xl:grid-cols-3">
                    {campaigns.data.map((campaign) => {
                        const isOpen = campaign.status === 'open';
                        return (
                            <article
                                key={campaign.id}
                                className={cn(
                                    'surface relative flex flex-col overflow-hidden transition-shadow hover:shadow-elevated',
                                    isOpen && 'border-success/40',
                                )}
                            >
                                <span
                                    className={cn(
                                        'absolute inset-x-0 top-0 h-1',
                                        accents[toneFor(campaign.status)],
                                    )}
                                    aria-hidden="true"
                                />
                                <div className="flex items-start gap-3 p-5 pb-4">
                                    <span
                                        className={cn(
                                            'flex size-11 shrink-0 items-center justify-center rounded-xl',
                                            isOpen
                                                ? 'bg-success-soft text-success'
                                                : 'bg-accent text-primary',
                                        )}
                                        aria-hidden="true"
                                    >
                                        <Megaphone className="size-5" />
                                    </span>
                                    <div className="min-w-0 flex-1 space-y-1">
                                        <div className="flex flex-wrap items-start justify-between gap-2">
                                            <h2 className="min-w-0 text-base leading-snug font-bold break-words text-foreground">
                                                {campaign.name}
                                            </h2>
                                            <StatusBadge
                                                status={campaign.status}
                                                label={campaign.status_label}
                                            />
                                        </div>
                                        <p className="flex items-center gap-1.5 text-sm text-muted-foreground">
                                            <GraduationCap
                                                className="size-4 shrink-0"
                                                aria-hidden="true"
                                            />
                                            Année {campaign.academic_year}
                                        </p>
                                    </div>
                                </div>
                                <dl className="mx-5 grid grid-cols-3 gap-3 rounded-lg border bg-muted/40 p-3 text-sm">
                                    <div className="min-w-0">
                                        <dt className="flex items-center gap-1 text-xs text-muted-foreground">
                                            <CalendarDays
                                                className="size-3.5 shrink-0"
                                                aria-hidden="true"
                                            />
                                            Début
                                        </dt>
                                        <dd className="mt-1 font-medium text-foreground tabular-nums">
                                            {campaign.starts_at
                                                ? formatDate(campaign.starts_at)
                                                : '—'}
                                        </dd>
                                    </div>
                                    <div className="min-w-0">
                                        <dt className="flex items-center gap-1 text-xs text-muted-foreground">
                                            <CalendarCheck
                                                className="size-3.5 shrink-0"
                                                aria-hidden="true"
                                            />
                                            Fin
                                        </dt>
                                        <dd className="mt-1 font-medium text-foreground tabular-nums">
                                            {campaign.ends_at
                                                ? formatDate(campaign.ends_at)
                                                : '—'}
                                        </dd>
                                    </div>
                                    <div className="min-w-0">
                                        <dt className="flex items-center gap-1 text-xs text-muted-foreground">
                                            <FolderOpen
                                                className="size-3.5 shrink-0"
                                                aria-hidden="true"
                                            />
                                            Dossiers
                                        </dt>
                                        <dd className="mt-1 font-heading text-lg leading-none font-bold text-foreground tabular-nums">
                                            {formatNumber(
                                                campaign.applications_count,
                                            )}
                                            <span className="sr-only">
                                                {' '}
                                                dossiers reçus
                                            </span>
                                        </dd>
                                    </div>
                                </dl>
                                {!campaign.starts_at && !campaign.ends_at && (
                                    <p className="mx-5 mt-2 text-xs text-muted-foreground">
                                        Période indicative non définie.
                                    </p>
                                )}
                                {campaign.notes && (
                                    <p className="mx-5 mt-3 line-clamp-2 text-sm text-muted-foreground">
                                        {campaign.notes}
                                    </p>
                                )}
                                <div
                                    className="min-h-5 flex-1"
                                    aria-hidden="true"
                                />
                                <div className="flex flex-wrap items-center gap-2 border-t bg-muted/30 px-5 py-3">
                                    {campaign.targets.map((target) => (
                                        <Button
                                            key={target.value}
                                            size="sm"
                                            variant={
                                                target.value === 'open'
                                                    ? 'default'
                                                    : 'outline'
                                            }
                                            onClick={() =>
                                                setTransition({
                                                    campaign,
                                                    target,
                                                })
                                            }
                                        >
                                            {target.value === 'open' && (
                                                <PlayCircle aria-hidden="true" />
                                            )}
                                            {actionLabels[target.value] ??
                                                target.label}
                                        </Button>
                                    ))}
                                    <div className="ml-auto flex flex-wrap items-center gap-2">
                                        {canManage &&
                                            campaign.status !== 'archived' && (
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    onClick={() =>
                                                        open(campaign)
                                                    }
                                                >
                                                    <Pencil aria-hidden="true" />
                                                    Modifier
                                                </Button>
                                            )}
                                        <TrashButton
                                            kind="campaigns"
                                            uuid={campaign.uuid}
                                            label={campaign.name}
                                        />
                                    </div>
                                </div>
                            </article>
                        );
                    })}
                </div>
            )}
            <Pagination links={campaigns.links} />
            <Dialog
                open={editing !== null}
                onOpenChange={(value) => {
                    if (!value && !form.processing) setEditing(null);
                }}
            >
                <DialogContent className="sm:max-w-xl">
                    <form onSubmit={submit} className="space-y-5" noValidate>
                        <DialogHeader>
                            <DialogTitle>
                                {editing === 'new'
                                    ? 'Nouvelle campagne'
                                    : 'Modifier la campagne'}
                            </DialogTitle>
                            <DialogDescription>
                                {editing === 'new'
                                    ? 'La campagne est créée en brouillon ; vous l’ouvrirez ensuite pour commencer la réception.'
                                    : 'Les dates restent indicatives : l’ouverture et la clôture se font depuis la liste.'}
                            </DialogDescription>
                        </DialogHeader>
                        <ErrorSummary errors={{ campaign: errors.campaign }} />
                        <FormField
                            id="academic_year_id"
                            label="Année universitaire"
                            required
                            error={errors.academic_year_id}
                        >
                            <Select
                                value={form.data.academic_year_id}
                                onValueChange={(value) =>
                                    form.setData('academic_year_id', value)
                                }
                            >
                                <SelectTrigger
                                    id="academic_year_id"
                                    className="w-full"
                                >
                                    <SelectValue placeholder="Choisir une année" />
                                </SelectTrigger>
                                <SelectContent>
                                    {academicYears.map((year) => (
                                        <SelectItem
                                            key={year.id}
                                            value={String(year.id)}
                                        >
                                            {year.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </FormField>
                        <FormField
                            id="name"
                            label="Nom de la campagne"
                            required
                            error={errors.name}
                            hint="Unique pour l’année universitaire."
                        >
                            <Input
                                id="name"
                                maxLength={150}
                                required
                                placeholder="Bourse nationale 2026-2027"
                                value={form.data.name}
                                onChange={(e) =>
                                    form.setData('name', e.target.value)
                                }
                            />
                        </FormField>
                        <div className="grid gap-5 sm:grid-cols-2">
                            <FormField
                                id="starts_at"
                                label="Début"
                                error={errors.starts_at}
                            >
                                <Input
                                    id="starts_at"
                                    type="date"
                                    value={form.data.starts_at}
                                    onChange={(e) =>
                                        form.setData(
                                            'starts_at',
                                            e.target.value,
                                        )
                                    }
                                />
                            </FormField>
                            <FormField
                                id="ends_at"
                                label="Fin"
                                error={errors.ends_at}
                            >
                                <Input
                                    id="ends_at"
                                    type="date"
                                    min={form.data.starts_at || undefined}
                                    value={form.data.ends_at}
                                    onChange={(e) =>
                                        form.setData('ends_at', e.target.value)
                                    }
                                />
                            </FormField>
                        </div>
                        <FormField
                            id="notes"
                            label="Notes internes"
                            error={errors.notes}
                            hint={`${form.data.notes.length}/1000 caractères — visibles uniquement par le personnel.`}
                        >
                            <textarea
                                id="notes"
                                className="min-h-24 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                maxLength={1000}
                                value={form.data.notes}
                                onChange={(e) =>
                                    form.setData('notes', e.target.value)
                                }
                            />
                        </FormField>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                disabled={form.processing}
                                onClick={() => setEditing(null)}
                            >
                                Annuler
                            </Button>
                            <Button disabled={form.processing}>
                                {form.processing
                                    ? 'Enregistrement…'
                                    : editing === 'new'
                                      ? 'Créer la campagne'
                                      : 'Enregistrer'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
            <Dialog
                open={transition !== null}
                onOpenChange={(value) => {
                    if (!value && !busy) setTransition(null);
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {transition
                                ? `${actionLabels[transition.target.value] ?? 'Changer le statut'} ?`
                                : 'Changer le statut'}
                        </DialogTitle>
                        <DialogDescription>
                            {transition &&
                                `« ${transition.campaign.name} » passera de ${transition.campaign.status_label} à ${transition.target.label}. Ce changement ne peut pas être annulé.`}
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            disabled={busy}
                            onClick={() => setTransition(null)}
                        >
                            Annuler
                        </Button>
                        <Button
                            disabled={busy}
                            onClick={() => {
                                if (!transition) return;
                                router.post(
                                    `/scholarships/campaigns/${transition.campaign.uuid}/status`,
                                    { status: transition.target.value },
                                    {
                                        onStart: () => setBusy(true),
                                        onFinish: () => {
                                            setBusy(false);
                                            setTransition(null);
                                        },
                                    },
                                );
                            }}
                        >
                            Confirmer
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </PageBody>
    );
}
Campaigns.layout = {
    breadcrumbs: [{ title: 'Campagnes', href: '/scholarships/campaigns' }],
};
