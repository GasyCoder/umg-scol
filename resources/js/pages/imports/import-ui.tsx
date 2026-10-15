import {
    Check,
    CheckCircle2,
    Columns3,
    FileCheck2,
    FileUp,
    ListChecks,
    X,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { ToneBadge, toneFor } from '@/components/tone-badge';
import { cn } from '@/lib/utils';
import { rowStatusTones, statuses } from './types';
import type { ImportStatus, RowStatus } from './types';

/** The import pipeline, as shown to users: nothing is written before step 4. */
export const importSteps: { title: string; hint: string; icon: LucideIcon }[] =
    [
        { title: 'Fichier', hint: 'Dépôt du CSV ou Excel', icon: FileUp },
        {
            title: 'Colonnes',
            hint: 'Correspondance des en-têtes',
            icon: Columns3,
        },
        {
            title: 'Vérification',
            hint: 'Contrôle ligne par ligne',
            icon: ListChecks,
        },
        {
            title: 'Confirmation',
            hint: 'Validation explicite',
            icon: FileCheck2,
        },
        { title: 'Résultat', hint: 'Résumé de l’import', icon: CheckCircle2 },
    ];

/**
 * Shared building blocks of the student import pages (not an Inertia page: no default export).
 */

export function ImportStatusBadge({
    status,
    className,
}: {
    status: ImportStatus;
    className?: string;
}) {
    return (
        <ToneBadge tone={toneFor(status)} className={className}>
            {statuses[status] ?? status}
        </ToneBadge>
    );
}

export function RowStatusBadge({ status }: { status: RowStatus }) {
    return (
        <ToneBadge tone={rowStatusTones[status] ?? 'neutral'}>
            {statuses[status] ?? status}
        </ToneBadge>
    );
}

/**
 * Horizontal pipeline stepper: finished steps are ticked, the current one is ringed.
 * `finished` ticks the current step too (completed import); `failed` marks it in red.
 */
export function ImportStepper({
    current,
    finished = false,
    failed = false,
    className,
}: {
    current: number;
    finished?: boolean;
    failed?: boolean;
    className?: string;
}) {
    const step = importSteps[current];
    return (
        <div className={cn('surface px-4 py-4 sm:px-6', className)}>
            <ol aria-label="Étapes de l’import" className="flex items-start">
                {importSteps.map((item, index) => {
                    const done =
                        index < current || (index === current && finished);
                    const active = index === current && !finished;
                    const Icon = item.icon;
                    return (
                        <li
                            key={item.title}
                            className="relative flex min-w-0 flex-1 flex-col items-center gap-2 text-center"
                            aria-current={active ? 'step' : undefined}
                        >
                            {index > 0 && (
                                <span
                                    aria-hidden="true"
                                    className={cn(
                                        'absolute top-[1.125rem] right-1/2 -left-1/2 h-0.5 -translate-y-1/2 rounded-full',
                                        index <= current
                                            ? 'bg-primary'
                                            : 'bg-border',
                                    )}
                                />
                            )}
                            <span
                                aria-hidden="true"
                                className={cn(
                                    'relative z-10 flex size-9 items-center justify-center rounded-full text-sm font-bold transition-colors',
                                    done &&
                                        'bg-primary text-primary-foreground',
                                    active &&
                                        (failed
                                            ? 'bg-danger-soft text-danger ring-2 ring-danger ring-offset-2 ring-offset-card'
                                            : 'bg-card text-primary ring-2 ring-primary ring-offset-2 ring-offset-card'),
                                    !done &&
                                        !active &&
                                        'bg-muted text-muted-foreground',
                                )}
                            >
                                {done ? (
                                    <Check className="size-4" />
                                ) : active && failed ? (
                                    <X className="size-4" />
                                ) : (
                                    <Icon className="size-4" />
                                )}
                            </span>
                            <span className="min-w-0 max-sm:sr-only">
                                <span
                                    className={cn(
                                        'block truncate text-xs font-semibold',
                                        active || done
                                            ? 'text-foreground'
                                            : 'text-muted-foreground',
                                    )}
                                >
                                    <span className="sr-only">
                                        Étape {index + 1} :{' '}
                                    </span>
                                    {item.title}
                                    {done && (
                                        <span className="sr-only">
                                            {' '}
                                            (terminée)
                                        </span>
                                    )}
                                </span>
                                <span className="hidden truncate text-[11px] text-muted-foreground lg:block">
                                    {item.hint}
                                </span>
                            </span>
                        </li>
                    );
                })}
            </ol>
            <p
                className="mt-3 text-center text-sm text-muted-foreground sm:hidden"
                aria-hidden="true"
            >
                Étape {current + 1} sur {importSteps.length} ·{' '}
                <span className="font-semibold text-foreground">
                    {step?.title}
                </span>
            </p>
        </div>
    );
}

/**
 * Thin stacked bar repeating the row counts (valid / warning / rejected / duplicate).
 * Decorative: the numbers are always written next to it.
 */
export function RowMixBar({
    valid,
    warning,
    rejected,
    duplicate,
    className,
}: {
    valid: number;
    warning: number;
    rejected: number;
    duplicate: number;
    className?: string;
}) {
    const total = valid + warning + rejected + duplicate;
    const parts = [
        { value: valid, className: 'bg-success' },
        { value: warning, className: 'bg-warning' },
        { value: rejected, className: 'bg-danger' },
        { value: duplicate, className: 'bg-info' },
    ];
    return (
        <span
            aria-hidden="true"
            className={cn(
                'flex h-1.5 w-full overflow-hidden rounded-full bg-muted',
                className,
            )}
        >
            {total > 0 &&
                parts.map((part, index) =>
                    part.value > 0 ? (
                        <span
                            key={index}
                            className={part.className}
                            style={{ width: `${(part.value / total) * 100}%` }}
                        />
                    ) : null,
                )}
        </span>
    );
}
