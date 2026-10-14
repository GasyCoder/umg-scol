import { Head, router, useForm, usePage } from '@inertiajs/react';
import {
    CalendarRange,
    FolderOpen,
    Megaphone,
    Pencil,
    Plus,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { ErrorSummary, FormField } from '@/components/forms/form-kit';
import { TrashButton } from '@/components/trash/trash-button';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
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
    return (
        <div className="space-y-6 p-4 md:p-6">
            <Head title="Campagnes de bourse" />
            <div className="flex flex-wrap items-start justify-between gap-3">
                <Heading
                    title="Campagnes de bourse"
                    description="Une campagne par année universitaire encadre la réception des dossiers papier : la réception n’est possible que pendant une campagne ouverte."
                />
                {canManage && (
                    <Button onClick={() => open('new')}>
                        <Plus aria-hidden="true" />
                        Nouvelle campagne
                    </Button>
                )}
            </div>
            <p className="rounded-lg border bg-card px-4 py-3 text-sm text-muted-foreground">
                Cycle d’une campagne :{' '}
                <strong className="text-foreground">Brouillon</strong> →
                Programmée →{' '}
                <strong className="text-foreground">Ouverte</strong> (réception
                des dossiers) → Clôturée → Archivée. Chaque étape est
                définitive.
            </p>
            <InputError message={pageErrors.status} />
            {campaigns.data.length === 0 ? (
                <div className="flex flex-col items-center gap-3 rounded-lg border border-dashed bg-card p-10 text-center">
                    <Megaphone
                        className="size-8 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <p className="font-medium">
                        Aucune campagne pour le moment.
                    </p>
                    {canManage && (
                        <Button onClick={() => open('new')}>
                            Créer la première campagne
                        </Button>
                    )}
                </div>
            ) : (
                <div className="grid gap-4 md:grid-cols-2 2xl:grid-cols-3">
                    {campaigns.data.map((campaign) => (
                        <article
                            key={campaign.id}
                            className="flex flex-col rounded-lg border bg-card"
                        >
                            <div className="flex items-start gap-3 p-5">
                                <span
                                    className="flex size-10 shrink-0 items-center justify-center rounded-full bg-accent text-primary"
                                    aria-hidden="true"
                                >
                                    <Megaphone className="size-5" />
                                </span>
                                <div className="min-w-0 flex-1">
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <h2 className="truncate text-base font-bold text-foreground">
                                            {campaign.name}
                                        </h2>
                                        <StatusBadge
                                            status={campaign.status}
                                            label={campaign.status_label}
                                        />
                                    </div>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        Année {campaign.academic_year}
                                    </p>
                                </div>
                            </div>
                            <dl className="grid grid-cols-2 gap-4 border-y px-5 py-4 text-sm">
                                <div>
                                    <dt className="flex items-center gap-1 text-xs text-muted-foreground">
                                        <CalendarRange
                                            className="size-3.5"
                                            aria-hidden="true"
                                        />
                                        Période indicative
                                    </dt>
                                    <dd className="mt-1 font-medium text-foreground">
                                        {campaign.starts_at || campaign.ends_at
                                            ? `${formatDate(campaign.starts_at)} → ${formatDate(campaign.ends_at)}`
                                            : 'Non définie'}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="flex items-center gap-1 text-xs text-muted-foreground">
                                        <FolderOpen
                                            className="size-3.5"
                                            aria-hidden="true"
                                        />
                                        Dossiers reçus
                                    </dt>
                                    <dd className="mt-1 font-heading text-lg leading-none font-bold text-foreground tabular-nums">
                                        {campaign.applications_count}
                                    </dd>
                                </div>
                            </dl>
                            <div className="px-5 pb-4">
                                <TrashButton
                                    kind="campaigns"
                                    uuid={campaign.uuid}
                                    label={campaign.name}
                                />
                            </div>
                            {(canManage || campaign.targets.length > 0) && (
                                <div className="mt-auto flex flex-wrap items-center gap-2 p-4">
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
                                            {actionLabels[target.value] ??
                                                target.label}
                                        </Button>
                                    ))}
                                    {canManage &&
                                        campaign.status !== 'archived' && (
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                className="ml-auto"
                                                onClick={() => open(campaign)}
                                            >
                                                <Pencil aria-hidden="true" />
                                                Modifier
                                            </Button>
                                        )}
                                </div>
                            )}
                        </article>
                    ))}
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
        </div>
    );
}
Campaigns.layout = {
    breadcrumbs: [{ title: 'Campagnes', href: '/scholarships/campaigns' }],
};
