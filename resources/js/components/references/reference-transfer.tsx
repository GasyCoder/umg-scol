import { useForm } from '@inertiajs/react';
import { Download, FileSpreadsheet, FileUp, Upload, X } from 'lucide-react';
import { useId, useRef, useState } from 'react';
import type { DragEvent, FormEvent } from 'react';
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
import { baseUrl, definitions } from './definitions';

export type TransferKind = 'mentions' | 'programs';

const MAX_BYTES = 2 * 1024 * 1024;
const MAX_ROWS = 1000;
const ACCEPTED = ['xlsx', 'csv'];

const formatSize = (bytes: number) =>
    bytes < 1024 * 1024
        ? `${Math.max(1, Math.round(bytes / 1024))} Ko`
        : `${(bytes / 1024 / 1024).toFixed(1).replace('.', ',')} Mo`;

/** Model download, filtered export and import dialog of the mention/program lists. */
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
    return (
        <div
            className="flex flex-wrap gap-2"
            role="group"
            aria-label="Modèle, export et import"
        >
            <Button variant="outline" size="sm" asChild>
                <a href={`${baseUrl(kind)}/template`} download>
                    <FileSpreadsheet aria-hidden="true" />
                    Modèle
                </a>
            </Button>
            <Button variant="outline" size="sm" asChild>
                <a
                    href={`${baseUrl(kind)}/export${query ? `?${query}` : ''}`}
                    download
                >
                    <Download aria-hidden="true" />
                    Exporter
                </a>
            </Button>
            <Button variant="outline" size="sm" onClick={() => setOpen(true)}>
                <Upload aria-hidden="true" />
                Importer
            </Button>
            <ImportDialog kind={kind} open={open} onOpenChange={setOpen} />
        </div>
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
                        className={`flex cursor-pointer flex-col items-center gap-2 rounded-lg border-2 border-dashed px-4 py-8 text-center transition-colors focus-within:ring-2 focus-within:ring-ring ${dragging ? 'border-primary bg-accent' : 'border-border hover:bg-muted/50'}`}
                    >
                        <FileUp
                            className="size-8 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <span className="text-sm font-medium">
                            Déposez le fichier ici ou cliquez pour le choisir
                        </span>
                        <span className="text-xs text-muted-foreground">
                            XLSX ou CSV (séparateur « ; » ou « , », UTF-8) — 2
                            Mo et {MAX_ROWS} lignes au maximum
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
                        <div className="flex items-center justify-between gap-3 rounded-md border bg-muted/40 px-3 py-2 text-sm">
                            <span className="flex min-w-0 items-center gap-2">
                                <FileSpreadsheet
                                    className="size-4 shrink-0 text-primary"
                                    aria-hidden="true"
                                />
                                <span className="truncate">
                                    {form.data.file.name}
                                </span>
                                <span className="shrink-0 text-xs text-muted-foreground">
                                    {formatSize(form.data.file.size)}
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
                    <p className="text-xs text-muted-foreground">
                        Utilisez le modèle : la feuille « Codes » liste les
                        codes d’établissement
                        {kind === 'programs' ? ' et de mention' : ''}{' '}
                        utilisables. Les lignes en erreur ne sont jamais
                        importées.
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
