import {
    AlertCircle,
    CircleCheck,
    FileSpreadsheet,
    UploadCloud,
    X,
} from 'lucide-react';
import { useRef, useState } from 'react';
import type { DragEvent } from 'react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

const size = (bytes: number) =>
    bytes >= 1024 * 1024
        ? `${(bytes / 1024 / 1024).toFixed(1).replace('.', ',')} Mo`
        : `${Math.max(1, Math.round(bytes / 1024))} Ko`;

/**
 * Drag-and-drop file picker with immediate extension and size checks.
 * The server still validates everything; this only spares a useless upload.
 */
export function FileDropzone({
    id,
    file,
    onChange,
    accept,
    maxKilobytes,
    invalid = false,
}: {
    id: string;
    file: File | null;
    onChange: (file: File | null) => void;
    accept: string[];
    maxKilobytes: number;
    invalid?: boolean;
}) {
    const input = useRef<HTMLInputElement>(null);
    const [over, setOver] = useState(false);
    const [problem, setProblem] = useState('');
    const pick = (candidate: File | undefined) => {
        if (!candidate) return;
        const extension = candidate.name.split('.').pop()?.toLowerCase() ?? '';
        if (!accept.includes(extension)) {
            setProblem(
                `Format non accepté : choisissez un fichier ${accept.map((item) => `.${item}`).join(' ou ')}.`,
            );
            return;
        }
        if (candidate.size > maxKilobytes * 1024) {
            setProblem(
                `Fichier trop volumineux (${size(candidate.size)}) : ${size(maxKilobytes * 1024)} au plus.`,
            );
            return;
        }
        setProblem('');
        onChange(candidate);
    };
    const drop = (event: DragEvent<HTMLLabelElement>) => {
        event.preventDefault();
        setOver(false);
        pick(event.dataTransfer.files[0]);
    };

    return (
        <div className="space-y-2">
            {file ? (
                <div className="flex items-center gap-3 rounded-xl border border-success/30 bg-success-soft/60 p-4">
                    <span
                        className="flex size-11 shrink-0 items-center justify-center rounded-xl bg-card text-primary shadow-card ring-1 ring-border"
                        aria-hidden="true"
                    >
                        <FileSpreadsheet className="size-5" />
                    </span>
                    <div className="min-w-0 flex-1">
                        <p className="truncate text-sm font-bold text-foreground">
                            {file.name}
                        </p>
                        <p className="flex items-center gap-1 text-xs text-success-foreground">
                            <CircleCheck
                                className="size-3.5 shrink-0"
                                aria-hidden="true"
                            />
                            {size(file.size)} · prêt à être lu
                        </p>
                    </div>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        aria-label="Retirer le fichier"
                        className="text-muted-foreground hover:bg-danger-soft hover:text-danger"
                        onClick={() => {
                            onChange(null);
                            if (input.current) input.current.value = '';
                        }}
                    >
                        <X />
                    </Button>
                </div>
            ) : (
                <label
                    htmlFor={id}
                    onDragOver={(event) => {
                        event.preventDefault();
                        setOver(true);
                    }}
                    onDragLeave={() => setOver(false)}
                    onDrop={drop}
                    className={cn(
                        'group relative flex cursor-pointer flex-col items-center justify-center gap-2 overflow-hidden rounded-xl border-2 border-dashed px-6 py-10 text-center transition-colors focus-within:ring-2 focus-within:ring-ring',
                        over
                            ? 'border-primary bg-accent'
                            : invalid || problem
                              ? 'border-danger/50 bg-danger-soft/40'
                              : 'border-input bg-muted/20 hover:border-primary/60 hover:bg-accent/50',
                    )}
                >
                    <span
                        className="bg-grid pointer-events-none absolute inset-0 text-primary opacity-60"
                        aria-hidden="true"
                    />
                    <span
                        className={cn(
                            'relative flex size-14 items-center justify-center rounded-2xl bg-card text-primary shadow-card ring-1 ring-border transition-transform group-hover:-translate-y-0.5',
                            over && '-translate-y-0.5',
                        )}
                        aria-hidden="true"
                    >
                        <UploadCloud className="size-7" />
                    </span>
                    <span className="relative text-sm font-bold text-foreground">
                        {over ? (
                            'Déposez le fichier'
                        ) : (
                            <>
                                Glissez le fichier ici ou{' '}
                                <span className="text-primary underline underline-offset-4">
                                    parcourez
                                </span>
                            </>
                        )}
                    </span>
                    <span className="relative rounded-full bg-card px-2.5 py-0.5 text-xs text-muted-foreground ring-1 ring-border">
                        {accept.map((item) => item.toUpperCase()).join(' ou ')}{' '}
                        · {size(maxKilobytes * 1024)} au plus
                    </span>
                </label>
            )}
            <input
                ref={input}
                id={id}
                type="file"
                className="sr-only"
                accept={accept.map((item) => `.${item}`).join(',')}
                onChange={(event) => pick(event.target.files?.[0])}
            />
            {problem && (
                <p
                    role="alert"
                    className="flex items-center gap-1 text-xs font-medium text-destructive"
                >
                    <AlertCircle
                        className="size-3.5 shrink-0"
                        aria-hidden="true"
                    />
                    {problem}
                </p>
            )}
        </div>
    );
}
