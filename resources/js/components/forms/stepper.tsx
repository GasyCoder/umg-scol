import { AlertCircle, Check } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { cn } from '@/lib/utils';

export type Step = {
    id: string;
    title: string;
    /** Short line under the title (desktop only). */
    description?: string;
    icon: LucideIcon;
};

export type StepState = 'current' | 'done' | 'error' | 'upcoming';

/**
 * Progress of a step-by-step form: numbered list of steps on large screens, a compact
 * "Étape x sur n" with a progress bar on small ones. Reachable steps are buttons.
 */
export function Stepper({
    steps,
    current,
    stateOf,
    canOpen,
    onOpen,
    subtitleOf,
}: {
    steps: Step[];
    current: number;
    stateOf: (index: number) => StepState;
    canOpen: (index: number) => boolean;
    onOpen: (index: number) => void;
    /** Overrides the line under a step title (e.g. "Modifiée"). */
    subtitleOf?: (index: number) => string | undefined;
}) {
    const progress = Math.round(((current + 1) / steps.length) * 100);
    const step = steps[current];

    return (
        <nav aria-label="Étapes du formulaire" className="surface p-4 sm:p-5">
            <div className="lg:hidden">
                <div className="flex items-center justify-between gap-3 text-sm">
                    <p className="font-semibold text-foreground">
                        <span className="text-muted-foreground">
                            Étape {current + 1} sur {steps.length} ·{' '}
                        </span>
                        {step.title}
                    </p>
                    <span className="text-xs font-medium text-muted-foreground tabular-nums">
                        {progress} %
                    </span>
                </div>
                <div
                    className="mt-3 h-1.5 overflow-hidden rounded-full bg-muted"
                    role="progressbar"
                    aria-valuemin={1}
                    aria-valuemax={steps.length}
                    aria-valuenow={current + 1}
                    aria-label={`Étape ${current + 1} sur ${steps.length}`}
                >
                    <div
                        className="h-full rounded-full bg-primary transition-[width] duration-300 motion-reduce:transition-none"
                        style={{ width: `${progress}%` }}
                    />
                </div>
                <ol className="mt-3 flex gap-1.5">
                    {steps.map((item, index) => (
                        <li key={item.id} className="flex-1">
                            <button
                                type="button"
                                onClick={() => onOpen(index)}
                                disabled={!canOpen(index)}
                                aria-label={`Étape ${index + 1} : ${item.title}`}
                                aria-current={
                                    index === current ? 'step' : undefined
                                }
                                className={cn(
                                    'block h-9 w-full rounded-md focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed',
                                )}
                            >
                                <span
                                    className={cn(
                                        'mx-auto block h-1 rounded-full',
                                        dotTone[stateOf(index)],
                                    )}
                                />
                            </button>
                        </li>
                    ))}
                </ol>
            </div>

            <ol className="hidden gap-2 lg:flex">
                {steps.map((item, index) => {
                    const state = stateOf(index);
                    const Icon = item.icon;
                    return (
                        <li
                            key={item.id}
                            className="flex min-w-0 flex-1 items-center gap-2"
                        >
                            <button
                                type="button"
                                onClick={() => onOpen(index)}
                                disabled={!canOpen(index)}
                                aria-current={
                                    index === current ? 'step' : undefined
                                }
                                className={cn(
                                    'group flex min-w-0 flex-1 items-center gap-3 rounded-lg p-2 text-left transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed',
                                    index === current
                                        ? 'bg-accent'
                                        : 'enabled:hover:bg-muted/60',
                                )}
                            >
                                <span
                                    className={cn(
                                        'flex size-9 shrink-0 items-center justify-center rounded-full text-sm font-bold ring-1 transition-colors',
                                        circleTone[state],
                                    )}
                                    aria-hidden="true"
                                >
                                    {state === 'done' ? (
                                        <Check className="size-4" />
                                    ) : state === 'error' ? (
                                        <AlertCircle className="size-4" />
                                    ) : index === current ? (
                                        <Icon className="size-4" />
                                    ) : (
                                        index + 1
                                    )}
                                </span>
                                <span className="min-w-0">
                                    <span
                                        className={cn(
                                            'block truncate text-sm font-semibold',
                                            state === 'upcoming'
                                                ? 'text-muted-foreground'
                                                : 'text-foreground',
                                        )}
                                    >
                                        {item.title}
                                    </span>
                                    <span className="block truncate text-xs text-muted-foreground">
                                        {state === 'error'
                                            ? 'À corriger'
                                            : subtitleOf?.(index) !== undefined
                                              ? subtitleOf(index)
                                              : state === 'done'
                                                ? 'Complétée'
                                                : (item.description ??
                                                  `Étape ${index + 1}`)}
                                    </span>
                                </span>
                            </button>
                            {index < steps.length - 1 && (
                                <span
                                    className={cn(
                                        'h-px w-4 shrink-0 xl:w-6',
                                        state === 'done'
                                            ? 'bg-primary/50'
                                            : 'bg-border',
                                    )}
                                    aria-hidden="true"
                                />
                            )}
                        </li>
                    );
                })}
            </ol>
        </nav>
    );
}

const circleTone: Record<StepState, string> = {
    current: 'bg-primary text-primary-foreground ring-primary',
    done: 'bg-success-soft text-success-foreground ring-success/40',
    error: 'bg-danger-soft text-danger ring-danger/40',
    upcoming: 'bg-muted text-muted-foreground ring-border',
};

const dotTone: Record<StepState, string> = {
    current: 'bg-primary',
    done: 'bg-success',
    error: 'bg-danger',
    upcoming: 'bg-border',
};
