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
    return (
        <div className={cn('surface px-4 py-2.5 sm:px-5', className)}>
            <ol
                aria-label="Étapes de l’import"
                className="flex items-center gap-2"
            >
                {importSteps.map((item, index) => {
                    const done =
                        index < current || (index === current && finished);
                    const active = index === current && !finished;
                    const Icon = item.icon;
                    return (
                        <li
                            key={item.title}
                            className={cn(
                                'flex min-w-0 items-center gap-2',
                                index > 0 && 'flex-1',
                            )}
                            aria-current={active ? 'step' : undefined}
                        >
                            {index > 0 && (
                                <span
                                    aria-hidden="true"
                                    className={cn(
                                        'h-px min-w-3 flex-1',
                                        index <= current
                                            ? 'bg-primary'
                                            : 'bg-border',
                                    )}
                                />
                            )}
                            <span
                                aria-hidden="true"
                                className={cn(
                                    'flex size-7 shrink-0 items-center justify-center rounded-full transition-colors',
                                    done &&
                                        'bg-primary text-primary-foreground',
                                    active &&
                                        (failed
                                            ? 'bg-danger-soft text-danger ring-2 ring-danger'
                                            : 'bg-card text-primary ring-2 ring-primary'),
                                    !done &&
                                        !active &&
                                        'bg-muted text-muted-foreground',
                                )}
                            >
                                {done ? (
                                    <Check className="size-3.5" />
                                ) : active && failed ? (
                                    <X className="size-3.5" />
                                ) : (
                                    <Icon className="size-3.5" />
                                )}
                            </span>
                            <span
                                className={cn(
                                    'truncate text-xs font-semibold',
                                    active || done
                                        ? 'text-foreground'
                                        : 'text-muted-foreground',
                                    // On a phone only the current step is named.
                                    !active && 'max-md:sr-only',
                                )}
                                title={item.hint}
                            >
                                <span className="sr-only">
                                    Étape {index + 1} :{' '}
                                </span>
                                {item.title}
                                {done && (
                                    <span className="sr-only"> (terminée)</span>
                                )}
                            </span>
                        </li>
                    );
                })}
            </ol>
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
