import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
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
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';
import type { ImportRow } from './types';

const DATE_FIELDS = ['birth_date', 'cin_issued_at', 'scholarship_requested_at'];

/**
 * Corrects one row of an import. Before confirmation, saving analyses the whole file again
 * (duplicates inside the file, references); once the import is completed, the row is checked
 * again and imported alone. A value differing from the existing record is either taken from the
 * record or, for an actor allowed to correct it, written to the record.
 */
export function RowCorrectionDialog({
    importUuid,
    row,
    columns,
    completed,
    onClose,
}: {
    importUuid: string;
    row: ImportRow;
    /** Label of each field (StudentImportColumns::labels()). */
    columns: Record<string, string>;
    /** Import already confirmed: the row is imported alone. */
    completed: boolean;
    onClose: () => void;
}) {
    const initial = row.values ?? {};
    const initialUpdates = row.identity_updates ?? [];
    const official = row.official ?? {};
    const form = useForm<{
        values: Record<string, string>;
        identity_updates: string[];
    }>({
        values: initial,
        identity_updates: initialUpdates,
    });
    const updates = form.data.identity_updates;
    const updatesChanged =
        [...updates].sort().join(',') !== [...initialUpdates].sort().join(',');
    const choose = (field: string, choice: 'record' | 'file') => {
        form.setData({
            values: {
                ...form.data.values,
                [field]:
                    choice === 'record'
                        ? (official[field] ?? '')
                        : form.data.values[field] === official[field]
                          ? (initial[field] ?? '')
                          : (form.data.values[field] ?? ''),
            },
            identity_updates:
                choice === 'file'
                    ? [...updates.filter((item) => item !== field), field]
                    : updates.filter((item) => item !== field),
        });
    };
    const issues = [...(row.errors ?? []), ...(row.warnings ?? [])];
    // Fields named by an issue first, then the others in the file order.
    const fields = Object.keys(initial).sort(
        (a, b) =>
            Number(issues.some((issue) => issue.field === b)) -
            Number(issues.some((issue) => issue.field === a)),
    );
    const changed = fields.filter(
        (field) => (form.data.values[field] ?? '') !== (initial[field] ?? ''),
    );
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.transform((data) => ({
            values: Object.fromEntries(
                changed.map((field) => [field, data.values[field] ?? '']),
            ),
            identity_updates: data.identity_updates,
        }));
        form.patch(`/imports/${importUuid}/rows/${row.id}`, {
            preserveScroll: true,
            onSuccess: onClose,
        });
    };
    const formErrors = form.errors as Record<string, string | undefined>;

    return (
        <Dialog
            open
            onOpenChange={(open) => {
                if (!open && !form.processing) onClose();
            }}
        >
            <DialogContent className="flex max-h-[90vh] flex-col sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>
                        Corriger la ligne {row.row_number}
                    </DialogTitle>
                    <DialogDescription>
                        {completed
                            ? 'Cette ligne n’a pas été importée. Corrigez-la : à l’enregistrement, elle est vérifiée de nouveau et importée si tout est en ordre.'
                            : 'La correction s’applique à cet import seulement : le fichier déposé n’est pas modifié. À l’enregistrement, tout le fichier est analysé de nouveau.'}
                    </DialogDescription>
                </DialogHeader>
                <form
                    id="row-correction"
                    onSubmit={submit}
                    className="-mx-6 min-h-0 flex-1 space-y-4 overflow-y-auto px-6 py-1"
                >
                    <div className="grid gap-4 sm:grid-cols-2">
                        {fields.map((field) => {
                            const fieldIssues = issues.filter(
                                (issue) => issue.field === field,
                            );
                            const id = `correction-${field}`;
                            return (
                                <div key={field} className="space-y-1.5">
                                    <Label htmlFor={id} className="flex gap-2">
                                        {columns[field] ?? field}
                                        {row.corrected?.includes(field) && (
                                            <span className="text-xs font-normal text-muted-foreground">
                                                (déjà corrigé)
                                            </span>
                                        )}
                                    </Label>
                                    <Input
                                        id={id}
                                        value={form.data.values[field] ?? ''}
                                        onChange={(event) =>
                                            form.setData('values', {
                                                ...form.data.values,
                                                [field]: event.target.value,
                                            })
                                        }
                                        placeholder={
                                            DATE_FIELDS.includes(field)
                                                ? 'AAAA-MM-JJ ou JJ/MM/AAAA'
                                                : undefined
                                        }
                                        aria-invalid={fieldIssues.length > 0}
                                        aria-describedby={
                                            fieldIssues.length > 0
                                                ? `${id}-issue`
                                                : undefined
                                        }
                                        className={cn(
                                            fieldIssues.length > 0 &&
                                                'border-destructive',
                                        )}
                                    />
                                    {fieldIssues.length > 0 && (
                                        <p
                                            id={`${id}-issue`}
                                            className="text-xs text-destructive"
                                        >
                                            {fieldIssues
                                                .map((issue) => issue.message)
                                                .join(' ')}
                                        </p>
                                    )}
                                    {official[field] !== undefined && (
                                        <fieldset className="space-y-1 rounded-lg border p-2.5 text-xs">
                                            <legend className="px-1 text-muted-foreground">
                                                Différent de la fiche de
                                                l’étudiant
                                            </legend>
                                            <label className="flex cursor-pointer items-start gap-2">
                                                <input
                                                    type="radio"
                                                    name={`choice-${field}`}
                                                    className="mt-0.5 accent-primary"
                                                    checked={
                                                        !updates.includes(
                                                            field,
                                                        ) &&
                                                        form.data.values[
                                                            field
                                                        ] === official[field]
                                                    }
                                                    onChange={() =>
                                                        choose(field, 'record')
                                                    }
                                                />
                                                <span>
                                                    Garder la fiche :{' '}
                                                    <span className="font-medium text-foreground">
                                                        {official[field] || '—'}
                                                    </span>
                                                </span>
                                            </label>
                                            <label
                                                className={cn(
                                                    'flex items-start gap-2',
                                                    row.identity_editable
                                                        ? 'cursor-pointer'
                                                        : 'cursor-not-allowed opacity-60',
                                                )}
                                            >
                                                <input
                                                    type="radio"
                                                    name={`choice-${field}`}
                                                    className="mt-0.5 accent-primary"
                                                    disabled={
                                                        !row.identity_editable
                                                    }
                                                    checked={updates.includes(
                                                        field,
                                                    )}
                                                    onChange={() =>
                                                        choose(field, 'file')
                                                    }
                                                />
                                                <span>
                                                    Mettre à jour la fiche avec
                                                    la valeur ci-dessus
                                                    {!row.identity_editable &&
                                                        ' (votre rôle ne permet pas de modifier cette fiche)'}
                                                </span>
                                            </label>
                                        </fieldset>
                                    )}
                                </div>
                            );
                        })}
                    </div>
                    {Object.keys(official).length > 0 && (
                        <p className="text-sm text-muted-foreground">
                            Cette ligne correspond à un étudiant déjà
                            enregistré. Pour chaque différence, gardez la valeur
                            de la fiche, ou mettez la fiche à jour si c’est le
                            fichier qui est juste : la modification est tracée
                            dans l’historique de l’étudiant.
                        </p>
                    )}
                    {issues.some(
                        (issue) =>
                            !fields.includes(issue.field) &&
                            issue.field !== 'identity',
                    ) && (
                        <p className="text-sm text-muted-foreground">
                            Certaines anomalies ne portent pas sur une colonne
                            du fichier (référentiel manquant, situation déjà
                            déclarée…) : corrigez-les dans les référentiels ou
                            sur la fiche, puis relancez l’analyse.
                        </p>
                    )}
                    <InputError
                        message={
                            formErrors.values ??
                            Object.entries(formErrors).find(
                                ([key]) =>
                                    key.startsWith('values') ||
                                    key.startsWith('identity_updates'),
                            )?.[1]
                        }
                    />
                </form>
                <DialogFooter className="gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        disabled={form.processing}
                        onClick={onClose}
                    >
                        Annuler
                    </Button>
                    <Button
                        type="submit"
                        form="row-correction"
                        disabled={
                            form.processing ||
                            (!completed &&
                                changed.length === 0 &&
                                !updatesChanged)
                        }
                    >
                        {form.processing && <Spinner />}
                        {form.processing
                            ? 'Enregistrement…'
                            : completed
                              ? 'Enregistrer et importer la ligne'
                              : 'Enregistrer et relancer l’analyse'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
