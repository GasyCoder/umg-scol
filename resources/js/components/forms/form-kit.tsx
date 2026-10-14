import { Link } from '@inertiajs/react';
import { AlertCircle } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

/**
 * DashWind-style form building blocks shared by every internal form:
 * a titled card per section, labelled fields with hints and errors, an error summary and a sticky action bar.
 */
export function FormSection({
    icon: Icon,
    title,
    description,
    children,
    columns = 2,
}: {
    icon?: LucideIcon;
    title: string;
    description?: string;
    children: ReactNode;
    columns?: 1 | 2 | 3;
}) {
    const grid =
        columns === 3
            ? 'sm:grid-cols-2 xl:grid-cols-3'
            : columns === 2
              ? 'sm:grid-cols-2'
              : '';
    return (
        <section className="rounded-lg border bg-card">
            <header className="flex items-start gap-3 border-b px-5 py-4 sm:px-6">
                {Icon && (
                    <span
                        className="flex size-9 shrink-0 items-center justify-center rounded-full bg-accent text-primary"
                        aria-hidden="true"
                    >
                        <Icon className="size-4" />
                    </span>
                )}
                <div className="min-w-0">
                    <h2 className="text-base font-bold text-foreground">
                        {title}
                    </h2>
                    {description && (
                        <p className="text-sm text-muted-foreground">
                            {description}
                        </p>
                    )}
                </div>
            </header>
            <div className={`grid gap-x-6 gap-y-5 p-5 sm:p-6 ${grid}`}>
                {children}
            </div>
        </section>
    );
}

export function FormField({
    id,
    label,
    required = false,
    hint,
    error,
    wide = false,
    children,
}: {
    id: string;
    label: string;
    required?: boolean;
    hint?: string;
    error?: string;
    wide?: boolean;
    children: ReactNode;
}) {
    return (
        <div
            className={`grid content-start gap-1.5 ${wide ? 'sm:col-span-full' : ''}`}
        >
            <Label htmlFor={id} className="flex items-center gap-1">
                {label}
                {required ? (
                    <span className="text-destructive" aria-hidden="true">
                        *
                    </span>
                ) : (
                    <span className="text-xs font-normal text-muted-foreground">
                        (facultatif)
                    </span>
                )}
            </Label>
            {children}
            {error ? (
                <p
                    id={`${id}-error`}
                    role="alert"
                    className="flex items-center gap-1 text-xs text-destructive"
                >
                    <AlertCircle
                        className="size-3.5 shrink-0"
                        aria-hidden="true"
                    />
                    {error}
                </p>
            ) : (
                hint && (
                    <p
                        id={`${id}-hint`}
                        className="text-xs text-muted-foreground"
                    >
                        {hint}
                    </p>
                )
            )}
        </div>
    );
}

/** Lists the errors returned by the server above the form, so nothing goes unnoticed on long forms. */
export function ErrorSummary({
    errors,
    labels = {},
}: {
    errors: Record<string, string | undefined>;
    labels?: Record<string, string>;
}) {
    const entries = Object.entries(errors).filter(
        (entry): entry is [string, string] => Boolean(entry[1]),
    );
    if (entries.length === 0) return null;
    return (
        <div
            role="alert"
            className="rounded-lg border border-destructive/40 bg-destructive/5 p-4 text-sm"
        >
            <p className="flex items-center gap-2 font-bold text-destructive">
                <AlertCircle className="size-4" aria-hidden="true" />
                {entries.length > 1
                    ? `${entries.length} champs sont à corriger`
                    : 'Un champ est à corriger'}
            </p>
            <ul className="mt-2 list-disc space-y-1 pl-6 text-foreground">
                {entries.map(([key, message]) => (
                    <li key={key}>
                        <a
                            href={`#${key}`}
                            className="underline-offset-4 hover:underline"
                        >
                            {labels[key] ? `${labels[key]} : ` : ''}
                            {message}
                        </a>
                    </li>
                ))}
            </ul>
        </div>
    );
}

/** Sticky footer with the primary action; stays visible at the bottom of long forms. */
export function FormActions({
    processing,
    submitLabel,
    cancelHref,
    note = 'Les champs marqués * sont obligatoires.',
}: {
    processing: boolean;
    submitLabel: string;
    cancelHref: string;
    note?: string;
}) {
    return (
        <div className="sticky bottom-0 z-10 -mx-4 flex flex-wrap items-center justify-between gap-3 border-t bg-card/95 px-4 py-3 backdrop-blur md:-mx-6 md:px-6">
            <p className="text-xs text-muted-foreground">{note}</p>
            <div className="flex gap-2">
                <Button variant="outline" asChild>
                    <Link href={cancelHref}>Annuler</Link>
                </Button>
                <Button type="submit" disabled={processing}>
                    {processing && <Spinner />}
                    {processing ? 'Enregistrement…' : submitLabel}
                </Button>
            </div>
        </div>
    );
}
