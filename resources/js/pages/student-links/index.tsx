import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
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
        student_number: string | null;
    };
};
export default function LinkReview({
    requests,
}: {
    requests: {
        data: Review[];
        links: { url: string | null; label: string; active: boolean }[];
    };
}) {
    const [selected, setSelected] = useState<Review | null>(null);
    const form = useForm({ decision: 'approve', rejection_reason: '' });
    return (
        <div className="space-y-6 p-4 md:p-6">
            <Head title="Rattachements étudiants" />
            <Heading
                title="Rattachements étudiants"
                description="Vérifier les justificatifs et l’identité du demandeur avant de donner accès à la fiche officielle."
            />
            {requests.data.length === 0 ? (
                <p>Aucune demande à examiner dans votre périmètre.</p>
            ) : (
                requests.data.map((item) => (
                    <article
                        key={item.id}
                        className="flex flex-wrap items-center justify-between gap-3 rounded-md border p-4"
                    >
                        <div>
                            <p className="font-medium">
                                {item.student.last_name}{' '}
                                {item.student.first_names} —{' '}
                                {item.student.student_number ??
                                    'Sans matricule'}
                            </p>
                            <p className="text-sm">
                                Compte demandeur : {item.user.email}
                            </p>
                            <p className="text-sm text-muted-foreground">
                                {new Date(item.requested_at).toLocaleDateString(
                                    'fr-FR',
                                )}
                            </p>
                        </div>
                        <div className="flex gap-2">
                            {[
                                ['approve', 'Approuver'],
                                ['reject', 'Rejeter'],
                            ].map(([decision, label]) => (
                                <Button
                                    key={decision}
                                    variant={
                                        decision === 'approve'
                                            ? 'default'
                                            : 'outline'
                                    }
                                    onClick={() => {
                                        form.reset();
                                        form.clearErrors();
                                        form.setData('decision', decision);
                                        setSelected(item);
                                    }}
                                >
                                    {label}
                                </Button>
                            ))}
                        </div>
                    </article>
                ))
            )}
            <nav aria-label="Pagination" className="flex flex-wrap gap-2">
                {requests.links.map((link, i) =>
                    link.url ? (
                        <Button
                            key={i}
                            variant={link.active ? 'default' : 'outline'}
                            asChild
                        >
                            <Link href={link.url}>
                                {i === 0
                                    ? 'Précédent'
                                    : i === requests.links.length - 1
                                      ? 'Suivant'
                                      : link.label}
                            </Link>
                        </Button>
                    ) : null,
                )}
            </nav>
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
        </div>
    );
}
LinkReview.layout = {
    breadcrumbs: [{ title: 'Rattachements étudiants', href: '/student-links' }],
};
