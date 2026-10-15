import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

/**
 * Page title block used at the top of every internal page:
 * tinted icon tile, title, one-line description, optional meta line and actions on the right.
 */
export function PageHeader({
    icon: Icon,
    title,
    description,
    meta,
    actions,
    className,
}: {
    icon?: LucideIcon;
    title: string;
    description?: ReactNode;
    meta?: ReactNode;
    actions?: ReactNode;
    className?: string;
}) {
    return (
        <header
            className={cn(
                'flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between',
                className,
            )}
        >
            <div className="flex min-w-0 items-start gap-4">
                {Icon && (
                    <span
                        className="hidden size-12 shrink-0 items-center justify-center rounded-xl bg-primary text-primary-foreground shadow-sm ring-4 ring-accent sm:flex"
                        aria-hidden="true"
                    >
                        <Icon className="size-6" />
                    </span>
                )}
                <div className="min-w-0 space-y-1">
                    <h1 className="text-2xl leading-tight font-bold tracking-tight text-foreground lg:text-[1.65rem]">
                        {title}
                    </h1>
                    {description && (
                        <p className="max-w-3xl text-sm text-muted-foreground">
                            {description}
                        </p>
                    )}
                    {meta && <div className="pt-1">{meta}</div>}
                </div>
            </div>
            {actions && (
                <div className="flex shrink-0 flex-wrap items-center gap-2">
                    {actions}
                </div>
            )}
        </header>
    );
}

/** Standard page body spacing, so every page breathes the same way. */
export function PageBody({
    children,
    className,
}: {
    children: ReactNode;
    className?: string;
}) {
    return (
        <div className={cn('space-y-6 p-4 md:p-6 lg:p-8', className)}>
            {children}
        </div>
    );
}
