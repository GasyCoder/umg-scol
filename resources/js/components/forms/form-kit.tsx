import { Link } from '@inertiajs/react';
import { AlertCircle, Info } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';

/**
 * Form building blocks shared by every internal form:
 * a titled card per section, labelled fields with hints and errors, an error summary and a sticky action bar.
 */
export function FormSection({
    icon: Icon,
    title,
    description,
    children,
    columns = 2,
    id,
    action,
    className,
}: {
    icon?: LucideIcon;
    title: string;
    description?: string;
    children: ReactNode;
    columns?: 1 | 2 | 3;
    /** Optional anchor, so a summary can link to the section. */
    id?: string;
    /** Optional element shown on the right of the section header. */
    action?: ReactNode;
    className?: string;
}) {
    const grid =
        columns === 3
            ? 'sm:grid-cols-2 xl:grid-cols-3'
            : columns === 2
              ? 'sm:grid-cols-2'
              : '';
    return (
        <section
            id={id}
            className={cn('surface scroll-mt-24 overflow-hidden', className)}
        >
            <header className="flex flex-wrap items-start justify-between gap-3 border-b bg-muted/30 px-5 py-4 sm:px-6">
                <div className="flex min-w-0 items-start gap-3">
                    {Icon && (
                        <span
                            className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-accent text-primary ring-1 ring-primary/10"
                            aria-hidden="true"
                        >
                            <Icon className="size-[18px]" />
                        </span>
                    )}
                    <div className="min-w-0 pt-0.5">
                        <h2 className="text-base leading-tight font-bold text-foreground">
                            {title}
                        </h2>
                        {description && (
                            <p className="mt-0.5 text-sm text-muted-foreground">
                                {description}
                            </p>
                        )}
                    </div>
                </div>
                {action}
            </header>
            <div className={cn('grid gap-x-6 gap-y-5 p-5 sm:p-6', grid)}>
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
            className={cn(
                'grid content-start gap-1.5',
                wide && 'sm:col-span-full',
            )}
        >
            <Label
                htmlFor={id}
                className="flex items-center gap-1 text-sm font-semibold text-foreground"
            >
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
                    className="flex items-center gap-1 text-xs font-medium text-destructive"
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
                        className="flex items-start gap-1 text-xs text-muted-foreground"
                    >
                        <Info
                            className="mt-px size-3.5 shrink-0 opacity-70"
                            aria-hidden="true"
                        />
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
            className="flex gap-3 rounded-xl border border-danger/30 bg-danger-soft p-4 text-sm"
        >
            <span
                className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-danger/15 text-danger"
                aria-hidden="true"
            >
                <AlertCircle className="size-[18px]" />
            </span>
            <div className="min-w-0">
                <p className="font-bold text-danger-foreground">
                    {entries.length > 1
                        ? `${entries.length} champs sont à corriger`
                        : 'Un champ est à corriger'}
                </p>
                <ul className="mt-1.5 list-disc space-y-1 pl-5 text-foreground marker:text-danger">
                    {entries.map(([key, message]) => (
                        <li key={key}>
                            <a
                                href={`#${key}`}
                                className="rounded-sm underline-offset-4 hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            >
                                {labels[key] && (
                                    <span className="font-semibold">
                                        {labels[key]} :{' '}
                                    </span>
                                )}
                                {message}
                            </a>
                        </li>
                    ))}
                </ul>
            </div>
        </div>
    );
}

/**
 * Floating sticky bar with the primary action: stays visible at the bottom of long forms
 * and works whatever the page padding.
 */
export function FormActions({
    processing,
    submitLabel,
    cancelHref,
    note = 'Les champs marqués * sont obligatoires.',
    cancelLabel = 'Annuler',
    submitIcon: SubmitIcon,
}: {
    processing: boolean;
    submitLabel: string;
    cancelHref: string;
    note?: string;
    cancelLabel?: string;
    /** Optional icon shown before the submit label when idle. */
    submitIcon?: LucideIcon;
}) {
    return (
        <div className="sticky bottom-0 z-20 pt-2 pb-3 md:pb-4">
            <div className="flex flex-col gap-3 rounded-xl border bg-card/85 px-4 py-3 shadow-elevated backdrop-blur-md supports-[backdrop-filter]:bg-card/75 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                <p className="flex items-center gap-1.5 text-xs text-muted-foreground">
                    <Info className="size-3.5 shrink-0" aria-hidden="true" />
                    {note}
                </p>
                <div className="flex gap-2 [&>*]:flex-1 sm:[&>*]:flex-none">
                    <Button variant="outline" asChild>
                        <Link href={cancelHref}>{cancelLabel}</Link>
                    </Button>
                    <Button type="submit" disabled={processing}>
                        {processing ? (
                            <Spinner />
                        ) : (
                            SubmitIcon && <SubmitIcon aria-hidden="true" />
                        )}
                        {processing ? 'Enregistrement…' : submitLabel}
                    </Button>
                </div>
            </div>
        </div>
    );
}
