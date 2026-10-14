import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
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
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type Campaign = {
    id: number;
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
            form.put(`/scholarships/campaigns/${editing.id}`, options);
    };
    return (
        <div className="space-y-6 p-4 md:p-6">
            <Head title="Campagnes de bourse" />
            <div className="flex flex-wrap items-start justify-between gap-3">
                <Heading
                    title="Campagnes de bourse"
                    description="Une campagne par année universitaire encadre la réception des dossiers papier. La réception n’est possible que lorsque la campagne est ouverte."
                />
                {canManage && (
                    <Button onClick={() => open('new')}>
                        Nouvelle campagne
                    </Button>
                )}
            </div>
            <InputError message={pageErrors.status} />
            {campaigns.data.length === 0 ? (
                <p>Aucune campagne.</p>
            ) : (
                <div className="divide-y rounded-md border">
                    {campaigns.data.map((campaign) => (
                        <article
                            key={campaign.id}
                            className="flex flex-wrap items-center justify-between gap-3 p-4"
                        >
                            <div className="space-y-1">
                                <p className="font-medium">{campaign.name}</p>
                                <p className="text-sm text-muted-foreground">
                                    {campaign.academic_year} · du{' '}
                                    {formatDate(campaign.starts_at)} au{' '}
                                    {formatDate(campaign.ends_at)} ·{' '}
                                    {campaign.applications_count} dossier(s)
                                </p>
                            </div>
                            <div className="flex flex-wrap items-center gap-2">
                                <StatusBadge
                                    status={campaign.status}
                                    label={campaign.status_label}
                                />
                                {canManage &&
                                    campaign.status !== 'archived' && (
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() => open(campaign)}
                                        >
                                            Modifier
                                        </Button>
                                    )}
                                {campaign.targets.map((target) => (
                                    <Button
                                        key={target.value}
                                        size="sm"
                                        variant="outline"
                                        onClick={() =>
                                            setTransition({ campaign, target })
                                        }
                                    >
                                        → {target.label}
                                    </Button>
                                ))}
                            </div>
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
                <DialogContent>
                    <form onSubmit={submit} className="space-y-4">
                        <DialogHeader>
                            <DialogTitle>
                                {editing === 'new'
                                    ? 'Nouvelle campagne'
                                    : 'Modifier la campagne'}
                            </DialogTitle>
                            <DialogDescription>
                                Une nouvelle campagne est créée en brouillon.
                            </DialogDescription>
                        </DialogHeader>
                        <div className="space-y-2">
                            <Label>Année universitaire</Label>
                            <Select
                                value={form.data.academic_year_id}
                                onValueChange={(value) =>
                                    form.setData('academic_year_id', value)
                                }
                            >
                                <SelectTrigger aria-label="Année universitaire">
                                    <SelectValue placeholder="Choisir" />
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
                            <InputError message={errors.academic_year_id} />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="name">Nom</Label>
                            <Input
                                id="name"
                                maxLength={150}
                                required
                                value={form.data.name}
                                onChange={(e) =>
                                    form.setData('name', e.target.value)
                                }
                            />
                            <InputError message={errors.name} />
                        </div>
                        <div className="grid gap-3 sm:grid-cols-2">
                            <div className="space-y-2">
                                <Label htmlFor="starts_at">Début</Label>
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
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="ends_at">Fin</Label>
                                <Input
                                    id="ends_at"
                                    type="date"
                                    value={form.data.ends_at}
                                    onChange={(e) =>
                                        form.setData('ends_at', e.target.value)
                                    }
                                />
                                <InputError message={errors.ends_at} />
                            </div>
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="notes">Notes internes</Label>
                            <textarea
                                id="notes"
                                className="min-h-20 w-full rounded-md border bg-transparent p-2 text-sm"
                                maxLength={1000}
                                value={form.data.notes}
                                onChange={(e) =>
                                    form.setData('notes', e.target.value)
                                }
                            />
                        </div>
                        <InputError message={errors.campaign} />
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
                        <DialogTitle>Changer le statut</DialogTitle>
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
                                    `/scholarships/campaigns/${transition.campaign.id}/status`,
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
