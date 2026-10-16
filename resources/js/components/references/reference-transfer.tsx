import { useForm } from '@inertiajs/react';
import {
    ArrowRight,
    Download,
    FileSpreadsheet,
    ShieldCheck,
    Upload,
    X,
} from 'lucide-react';
import { useId, useRef, useState } from 'react';
import type { DragEvent, FormEvent } from 'react';
import { UploadIllustration } from '@/components/illustrations';
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
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';
import { baseUrl, definitions } from './definitions';

export type TransferKind = 'mentions' | 'programs';

const MAX_BYTES = 2 * 1024 * 1024;
const MAX_ROWS = 1000;
const ACCEPTED = ['xlsx', 'csv'];

const formatSize = (bytes: number) =>
    bytes < 1024 * 1024
        ? `${Math.max(1, Math.round(bytes / 1024))} Ko`
        : `${(bytes / 1024 / 1024).toFixed(1).replace('.', ',')} Mo`;

const transferSteps = [
    'Modèle XLSX',
    'Fichier complété',
    'Vérification puis confirmation',
];

/** Model download, filtered export and import dialog of the mention/program lists, as one card. */
export function ReferenceTransferToolbar({
    kind,
    filters,
}: {
    kind: TransferKind;
    filters: { search?: string; establishment_id?: string | number };
}) {
    const [open, setOpen] = useState(false);
    const query = new URLSearchParams(
        Object.entries(filters)
            .filter(([, value]) => value !== undefined && value !== '')
            .map(([key, value]) => [key, String(value)]),
    ).toString();
    const definition = definitions[kind];
    return (
        <section
            className="surface relative overflow-hidden"
            aria-labelledby={`${kind}-transfer-title`}
        >
            <div
                className="bg-grid absolute inset-y-0 right-0 w-1/2 [mask-image:linear-gradient(to_left,black,transparent)] text-primary opacity-40"
                aria-hidden="true"
            />
            <div className="relative flex flex-col gap-4 p-5 lg:flex-row lg:items-center lg:justify-between">
                <div className="flex min-w-0 items-start gap-3">
                    <span
                        className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-success-soft text-success"
                        aria-hidden="true"
                    >
                        <FileSpreadsheet className="size-5" />
                    </span>
                    <div className="min-w-0 space-y-1.5">
                        <h2
                            id={`${kind}-transfer-title`}
                            className="text-base font-bold text-foreground"
                        >
                            Import et export Excel
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Créez ou mettez à jour les{' '}
                            {definition.title.toLowerCase()} en masse. Chaque
                            ligne est vérifiée avant enregistrement ; l’export
                            reprend les filtres actifs.
                        </p>
                        <ol
                            className="flex flex-wrap items-center gap-x-2 gap-y-1 pt-0.5 text-xs text-muted-foreground"
                            aria-label="Étapes d’un import"
                        >
                            {transferSteps.map((step, index) => (
                                <li
                                    key={step}
                                    className="inline-flex items-center gap-1.5"
                                >
                                    <span className="flex size-5 items-center justify-center rounded-full bg-accent text-[10px] font-bold text-primary">
                                        {index + 1}
                                    </span>
                                    {step}
                                    {index < transferSteps.length - 1 && (
                                        <ArrowRight
                                            className="size-3 text-muted-foreground/60"
                                            aria-hidden="true"
                                        />
                                    )}
                                </li>
                            ))}
                        </ol>
                    </div>
                </div>
                <div
                    className="flex shrink-0 flex-wrap gap-2"
                    role="group"
                    aria-label="Modèle, export et import"
                >
                    <Button variant="outline" asChild>
                        <a href={`${baseUrl(kind)}/template`} download>
                            <FileSpreadsheet aria-hidden="true" />
                            Modèle
                        </a>
                    </Button>
                    <Button variant="outline" asChild>
                        <a
                            href={`${baseUrl(kind)}/export${query ? `?${query}` : ''}`}
                            download
                        >
                            <Download aria-hidden="true" />
                            Exporter
                        </a>
                    </Button>
                    <Button onClick={() => setOpen(true)}>
                        <Upload aria-hidden="true" />
                        Importer
                    </Button>
                </div>
            </div>
            <ImportDialog kind={kind} open={open} onOpenChange={setOpen} />
        </section>
    );
}

function ImportDialog({
    kind,
    open,
    onOpenChange,
}: {
    kind: TransferKind;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const id = useId();
    const input = useRef<HTMLInputElement>(null);
    const [dragging, setDragging] = useState(false);
    const [clientError, setClientError] = useState<string | null>(null);
    const form = useForm<{ file: File | null }>({ file: null });
    const definition = definitions[kind];

    const choose = (file: File | null) => {
        form.clearErrors();
        setClientError(null);
        if (file) {
            const extension = file.name.split('.').pop()?.toLowerCase() ?? '';
            if (!ACCEPTED.includes(extension)) {
                setClientError(
                    'Seules les extensions .xlsx et .csv sont acceptées.',
                );
            } else if (file.size > MAX_BYTES) {
                setClientError(
                    'Le fichier dépasse la taille maximale autorisée (2 Mo).',
                );
            }
        }
        form.setData('file', file);
    };
    const reset = () => {
        choose(null);
        if (input.current) input.current.value = '';
    };
    const drop = (event: DragEvent<HTMLLabelElement>) => {
        event.preventDefault();
        setDragging(false);
        choose(event.dataTransfer.files[0] ?? null);
    };
    const submit = (event: FormEvent) => {
        event.preventDefault();
        if (!form.data.file || clientError) return;
        form.post(`${baseUrl(kind)}/import`, { forceFormData: true });
    };
    const close = (state: boolean) => {
        if (form.processing) return;
        if (!state) reset();
        onOpenChange(state);
    };
    const error = clientError ?? form.errors.file;

    return (
        <Dialog open={open} onOpenChange={close}>
            <DialogContent className="sm:max-w-lg">
                <form onSubmit={submit} className="grid gap-4">
                    <DialogHeader>
                        <DialogTitle>
                            Importer des {definition.title.toLowerCase()}
                        </DialogTitle>
                        <DialogDescription>
                            Le fichier est d’abord analysé : vous vérifierez
                            chaque ligne avant de confirmer. Rien n’est
                            enregistré à cette étape.
                        </DialogDescription>
                    </DialogHeader>
                    <label
                        htmlFor={id}
                        onDragOver={(event) => {
                            event.preventDefault();
                            setDragging(true);
                        }}
                        onDragLeave={() => setDragging(false)}
                        onDrop={drop}
                        className={cn(
                            'flex cursor-pointer flex-col items-center gap-1.5 rounded-xl border-2 border-dashed px-4 py-6 text-center transition-colors focus-within:ring-2 focus-within:ring-ring',
                            dragging
                                ? 'border-primary bg-accent'
                                : error
                                  ? 'border-danger/50 bg-danger-soft/30'
                                  : 'border-input bg-muted/30 hover:border-primary/60 hover:bg-accent/40',
                        )}
                    >
                        <UploadIllustration className="h-24 w-auto" />
                        <span className="text-sm font-bold text-foreground">
                            Déposez le fichier ici ou{' '}
                            <span className="text-primary underline underline-offset-4">
                                cliquez pour le choisir
                            </span>
                        </span>
                        <span className="text-xs text-muted-foreground">
                            XLSX ou CSV (séparateur « ; » ou « , », UTF-8). 2 Mo
                            et {MAX_ROWS} lignes au maximum
                        </span>
                        <input
                            ref={input}
                            id={id}
                            type="file"
                            accept=".xlsx,.csv"
                            className="sr-only"
                            aria-describedby={error ? `${id}-error` : undefined}
                            aria-invalid={error ? true : undefined}
                            onChange={(event) =>
                                choose(event.target.files?.[0] ?? null)
                            }
                        />
                    </label>
                    {form.data.file && (
                        <div className="flex items-center justify-between gap-3 rounded-xl border bg-card p-3 text-sm shadow-card">
                            <span className="flex min-w-0 items-center gap-3">
                                <span
                                    className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-success-soft text-success"
                                    aria-hidden="true"
                                >
                                    <FileSpreadsheet className="size-4" />
                                </span>
                                <span className="min-w-0">
                                    <span className="block truncate font-semibold text-foreground">
                                        {form.data.file.name}
                                    </span>
                                    <span className="block text-xs text-muted-foreground">
                                        {formatSize(form.data.file.size)}
                                    </span>
                                </span>
                            </span>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="size-7"
                                onClick={reset}
                                disabled={form.processing}
                                aria-label="Retirer le fichier"
                            >
                                <X aria-hidden="true" />
                            </Button>
                        </div>
                    )}
                    <div id={`${id}-error`}>
                        <InputError message={error} />
                    </div>
                    <p className="flex items-start gap-2 rounded-lg bg-muted/50 p-3 text-xs text-muted-foreground">
                        <ShieldCheck
                            className="mt-px size-4 shrink-0 text-success"
                            aria-hidden="true"
                        />
                        <span>
                            Utilisez le modèle : la feuille « Codes » liste les
                            codes d’établissement
                            {kind === 'programs' ? ' et de mention' : ''}{' '}
                            utilisables. Les lignes en erreur ne sont jamais
                            importées.
                        </span>
                    </p>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => close(false)}
                            disabled={form.processing}
                        >
                            Annuler
                        </Button>
                        <Button
                            type="submit"
                            disabled={
                                !form.data.file ||
                                Boolean(clientError) ||
                                form.processing
                            }
                        >
                            {form.processing && <Spinner />}
                            {form.processing
                                ? 'Analyse en cours…'
                                : 'Analyser le fichier'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
