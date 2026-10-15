import type { LucideIcon } from 'lucide-react';
import { Check, Monitor, Moon, Sun } from 'lucide-react';
import type { HTMLAttributes, KeyboardEvent } from 'react';
import type { Appearance } from '@/hooks/use-appearance';
import { useAppearance } from '@/hooks/use-appearance';
import { cn } from '@/lib/utils';

const options: {
    value: Appearance;
    icon: LucideIcon;
    label: string;
    hint: string;
}[] = [
    { value: 'light', icon: Sun, label: 'Clair', hint: 'Toujours en clair' },
    { value: 'dark', icon: Moon, label: 'Sombre', hint: 'Toujours en sombre' },
    {
        value: 'system',
        icon: Monitor,
        label: 'Système',
        hint: 'Suit le réglage de l’appareil',
    },
];

/**
 * Miniature of the portal shell. The colours are theme-independent on purpose (white canvas or
 * navy sidebar token), so each card shows its own mode whatever the current theme is.
 */
function ShellPreview({ mode }: { mode: 'light' | 'dark' }) {
    const dark = mode === 'dark';
    const line = dark ? 'bg-white/20' : 'bg-sidebar/15';
    const card = dark
        ? 'bg-white/5 ring-1 ring-white/10'
        : 'bg-white ring-1 ring-sidebar/10 shadow-sm';

    return (
        <div
            className={cn(
                'absolute inset-0 flex',
                dark ? 'bg-sidebar' : 'bg-white',
            )}
            aria-hidden="true"
        >
            <div
                className={cn(
                    'flex w-1/4 flex-col gap-1.5 p-2',
                    dark ? 'bg-black/30' : 'bg-sidebar',
                )}
            >
                <span className="mb-1 size-3 rounded-sm bg-brand-gold" />
                <span className="h-1.5 w-4/5 rounded-full bg-white/40" />
                <span className="h-1.5 w-3/5 rounded-full bg-white/20" />
                <span className="h-1.5 w-2/3 rounded-full bg-white/20" />
            </div>
            <div
                className={cn(
                    'flex flex-1 flex-col gap-2 p-2.5',
                    !dark && 'bg-sidebar/[0.04]',
                )}
            >
                <span className={cn('h-2 w-1/2 rounded-full', line)} />
                <div className="grid grid-cols-3 gap-1.5">
                    {[0, 1, 2].map((key) => (
                        <span
                            key={key}
                            className={cn('h-5 rounded-md', card)}
                        />
                    ))}
                </div>
                <div className={cn('flex-1 space-y-1.5 rounded-md p-2', card)}>
                    <span
                        className={cn('block h-1.5 w-3/4 rounded-full', line)}
                    />
                    <span
                        className={cn('block h-1.5 w-1/2 rounded-full', line)}
                    />
                    <span className="block h-2.5 w-1/3 rounded-full bg-primary" />
                </div>
            </div>
        </div>
    );
}

/**
 * Theme picker with three visual previews (Clair / Sombre / Système), as a radio group.
 * The choice is applied immediately and kept in a cookie and localStorage.
 */
export default function AppearanceToggleTab({
    className = '',
    ...props
}: HTMLAttributes<HTMLDivElement>) {
    const { appearance, updateAppearance } = useAppearance();

    // Radio group keyboard pattern: arrows move and select, Tab leaves the group.
    const onKeyDown = (event: KeyboardEvent<HTMLButtonElement>) => {
        const step = {
            ArrowRight: 1,
            ArrowDown: 1,
            ArrowLeft: -1,
            ArrowUp: -1,
        }[event.key];

        if (!step) {
            return;
        }

        event.preventDefault();
        const index = options.findIndex(({ value }) => value === appearance);
        const next = (index + step + options.length) % options.length;
        updateAppearance(options[next].value);
        const buttons =
            event.currentTarget.parentElement?.querySelectorAll<HTMLButtonElement>(
                '[role="radio"]',
            );
        buttons?.[next]?.focus();
    };

    return (
        <div
            role="radiogroup"
            aria-label="Thème de l'interface"
            className={cn('grid gap-4 sm:grid-cols-3', className)}
            {...props}
        >
            {options.map(({ value, icon: Icon, label, hint }) => {
                const selected = appearance === value;

                return (
                    <button
                        key={value}
                        type="button"
                        role="radio"
                        aria-checked={selected}
                        tabIndex={selected ? 0 : -1}
                        onKeyDown={onKeyDown}
                        onClick={() => updateAppearance(value)}
                        className={cn(
                            'group rounded-xl border bg-card p-2 text-left transition-all focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background focus-visible:outline-none',
                            selected
                                ? 'border-primary shadow-elevated ring-2 ring-primary/30'
                                : 'hover:border-primary/40 hover:shadow-card',
                        )}
                    >
                        <span className="relative block aspect-[16/10] overflow-hidden rounded-lg ring-1 ring-border">
                            {value === 'system' ? (
                                <>
                                    <ShellPreview mode="light" />
                                    <span className="absolute inset-0 [clip-path:polygon(100%_0,100%_100%,0_100%)]">
                                        <ShellPreview mode="dark" />
                                    </span>
                                </>
                            ) : (
                                <ShellPreview mode={value} />
                            )}
                        </span>
                        <span className="flex items-center gap-3 px-1.5 pt-3 pb-1">
                            <span
                                className={cn(
                                    'flex size-8 shrink-0 items-center justify-center rounded-lg transition-colors',
                                    selected
                                        ? 'bg-primary text-primary-foreground'
                                        : 'bg-muted text-muted-foreground group-hover:text-primary',
                                )}
                                aria-hidden="true"
                            >
                                <Icon className="size-4" />
                            </span>
                            <span className="min-w-0 flex-1">
                                <span className="block text-sm font-semibold text-foreground">
                                    {label}
                                </span>
                                <span className="block truncate text-xs text-muted-foreground">
                                    {hint}
                                </span>
                            </span>
                            <span
                                className={cn(
                                    'flex size-5 shrink-0 items-center justify-center rounded-full border transition-colors',
                                    selected
                                        ? 'border-primary bg-primary text-primary-foreground'
                                        : 'border-input',
                                )}
                                aria-hidden="true"
                            >
                                {selected && <Check className="size-3" />}
                            </span>
                        </span>
                    </button>
                );
            })}
        </div>
    );
}
