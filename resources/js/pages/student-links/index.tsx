import { Head, useForm } from '@inertiajs/react';
import {
    CalendarClock,
    Check,
    Mail,
    ShieldCheck,
    UserCheck,
    X,
} from 'lucide-react';
import { useState } from 'react';
import { EmptyState } from '@/components/empty-state';
import InputError from '@/components/input-error';
import { PageBody, PageHeader } from '@/components/page-header';
import { formatDate, Pagination } from '@/components/scholarships/shared';
import type { PageLink } from '@/components/scholarships/shared';
import { ToneBadge } from '@/components/tone-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
type Review = {
    id: number;
    uuid: string;
    requested_at: string;
    user: { name: string; email: string };
    student: {
        last_name: string;
        first_names: string | null;
        birth_date: string | null;
    };
};

const initials = (review: Review) =>
    `${review.student.last_name.charAt(0)}${review.student.first_names?.charAt(0) ?? ''}`.toUpperCase();

export default function LinkReview({
    requests,
}: {
    requests: {
        data: Review[];
        total?: number;
        links: PageLink[];
    };
}) {
    const [selected, setSelected] = useState<Review | null>(null);
    const form = useForm({ decision: 'approve', rejection_reason: '' });
    const choose = (item: Review, decision: 'approve' | 'reject') => {
        form.reset();
        form.clearErrors();
        form.setData('decision', decision);
        setSelected(item);
    };
    const count = requests.total ?? requests.data.length;
    return (
        <>
            <Head title="Rattachements étudiants" />
            <PageBody>
                <PageHeader
                    icon={UserCheck}
                    title="Rattachements étudiants"
                    description="Vérifier les justificatifs et l’identité du demandeur avant de donner accès à la fiche officielle."
                    meta={
                        count > 0 ? (
                            <ToneBadge tone="warning">
                                {count} demande(s) en attente
                            </ToneBadge>
                        ) : undefined
                    }
                />
                {requests.data.length === 0 ? (
                    <div className="surface">
                        <EmptyState
                            illustration="done"
                            title="Aucune demande à examiner"
                            description="Aucune demande de rattachement n’est en attente dans votre périmètre."
                        />
                    </div>
                ) : (
                    <>
                        <p className="flex items-start gap-2 rounded-xl border border-info/30 bg-info-soft p-3 text-sm text-info-foreground">
                            <ShieldCheck
                                className="mt-0.5 size-4 shrink-0"
                                aria-hidden="true"
                            />
                            L’approbation donne au compte demandeur l’accès aux
                            informations personnelles officielles de l’étudiant.
                            En cas de doute, rejetez la demande.
                        </p>
                        <ul className="grid gap-4 lg:grid-cols-2">
                            {requests.data.map((item) => (
                                <li key={item.id}>
                                    <article className="surface flex h-full flex-col">
                                        <div className="flex items-start gap-4 p-5">
                                            <span
                                                className="flex size-11 shrink-0 items-center justify-center rounded-full bg-accent font-heading text-sm font-bold text-primary"
                                                aria-hidden="true"
                                            >
                                                {initials(item)}
                                            </span>
                                            <div className="min-w-0 flex-1 space-y-1">
                                                <h2 className="truncate text-base font-bold text-foreground">
                                                    {item.student.last_name}{' '}
                                                    {item.student.first_names}
                                                </h2>
                                                <p className="text-xs text-muted-foreground">
                                                    Né(e) le{' '}
                                                    {formatDate(
                                                        item.student.birth_date,
                                                    )}
                                                </p>
                                            </div>
                                            <ToneBadge tone="warning">
                                                En attente
                                            </ToneBadge>
                                        </div>
                                        <dl className="mx-5 mb-5 grid gap-3 rounded-lg border bg-muted/40 p-3 text-sm sm:grid-cols-2">
                                            <div className="min-w-0">
                                                <dt className="flex items-center gap-1.5 text-xs text-muted-foreground">
                                                    <Mail
                                                        className="size-3.5"
                                                        aria-hidden="true"
                                                    />
                                                    Compte demandeur
                                                </dt>
                                                <dd className="mt-0.5 truncate font-medium text-foreground">
                                                    {item.user.email}
                                                </dd>
                                                <dd className="truncate text-xs text-muted-foreground">
                                                    {item.user.name}
                                                </dd>
                                            </div>
                                            <div>
                                                <dt className="flex items-center gap-1.5 text-xs text-muted-foreground">
                                                    <CalendarClock
                                                        className="size-3.5"
                                                        aria-hidden="true"
                                                    />
                                                    Demandé le
                                                </dt>
                                                <dd className="mt-0.5 font-medium text-foreground tabular-nums">
                                                    {new Date(
                                                        item.requested_at,
                                                    ).toLocaleDateString(
                                                        'fr-FR',
                                                    )}
                                                </dd>
                                            </div>
                                        </dl>
                                        <div className="mt-auto flex flex-wrap items-center justify-between gap-2 border-t bg-muted/30 px-5 py-3">
                                            <Button
                                                variant="outline"
                                                className="border-danger/30 text-danger hover:bg-danger-soft hover:text-danger-foreground"
                                                onClick={() =>
                                                    choose(item, 'reject')
                                                }
                                            >
                                                <X aria-hidden="true" />
                                                Rejeter
                                            </Button>
                                            <Button
                                                onClick={() =>
                                                    choose(item, 'approve')
                                                }
                                            >
                                                <Check aria-hidden="true" />
                                                Approuver
                                            </Button>
                                        </div>
                                    </article>
                                </li>
                            ))}
                        </ul>
                    </>
                )}
                <Pagination links={requests.links} />
            </PageBody>
            <Dialog
                open={selected !== null}
                onOpenChange={(open) => {
                    if (!open && !form.processing) setSelected(null);
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {form.data.decision === 'approve'
                                ? 'Approuver le rattachement'
                                : 'Rejeter la demande'}
                        </DialogTitle>
                        <DialogDescription>
                            {form.data.decision === 'approve'
                                ? 'Confirmez avoir vérifié que ce compte appartient à l’étudiant. L’approbation donne accès aux informations personnelles officielles.'
                                : 'Indiquez un motif interne factuel. Il ne sera pas communiqué par l’API étudiante ni copié dans l’audit.'}
                        </DialogDescription>
                    </DialogHeader>
                    {selected && (
                        <div className="rounded-lg border bg-muted/40 p-3 text-sm">
                            <p className="font-semibold text-foreground">
                                {selected.student.last_name}{' '}
                                {selected.student.first_names}
                            </p>
                            <p className="text-muted-foreground">
                                {selected.user.email}
                            </p>
                        </div>
                    )}
                    {form.data.decision === 'reject' && (
                        <div className="space-y-2">
                            <Label htmlFor="reason">Motif interne</Label>
                            <Input
                                id="reason"
                                minLength={10}
                                maxLength={500}
                                value={form.data.rejection_reason}
                                onChange={(event) =>
                                    form.setData(
                                        'rejection_reason',
                                        event.target.value,
                                    )
                                }
                            />
                            <InputError
                                message={form.errors.rejection_reason}
                            />
                        </div>
                    )}
                    <InputError
                        message={
                            (form.errors as Record<string, string>).request
                        }
                    />
                    <DialogFooter>
                        <Button
                            variant="outline"
                            disabled={form.processing}
                            onClick={() => setSelected(null)}
                        >
                            Annuler
                        </Button>
                        <Button
                            variant={
                                form.data.decision === 'reject'
                                    ? 'destructive'
                                    : 'default'
                            }
                            disabled={form.processing}
                            onClick={() =>
                                form.post(
                                    `/student-links/${selected?.uuid}/review`,
                                    { onSuccess: () => setSelected(null) },
                                )
                            }
                        >
                            {form.processing ? 'Traitement…' : 'Confirmer'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
LinkReview.layout = {
    breadcrumbs: [{ title: 'Rattachements étudiants', href: '/student-links' }],
};
