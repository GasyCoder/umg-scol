import { Link, router } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowUpRight,
    Building2,
    CalendarDays,
    Globe2,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { Tone } from '@/components/tone-badge';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';

export type Year = { id: number; name: string; is_current: boolean };
export type Scope = {
    year: { id: number; name: string; is_current: boolean } | null;
    global: boolean;
    establishment: string | null;
    /** Reports of the account's own activity only (reports.view_own). */
    own?: boolean;
    /** The account has both kinds of report and may switch. */
    canChoose?: boolean;
};
export type BarItem = {
    id?: number | null;
    label: string;
    value: number;
    /** Semantic colour for a status row; wins over `colorful`. */
    tone?: Tone;
};

export const applicationStatusLabels: Record<string, string> = {
    pending: 'Reçus',
    awaiting_list: 'En attente de la liste',
    in_review: 'En vérification',
    needs_correction: 'Correction demandée',
    validated: 'Validés',
    rejected: 'Rejetés',
};
export const correctionStatusLabels: Record<string, string> = {
    pending: 'En attente',
    approved: 'Approuvées',
    rejected: 'Rejetées',
    cancelled: 'Annulées',
};

export const formatNumber = (value: number) =>
    new Intl.NumberFormat('fr-FR').format(value);

export function Panel({
    title,
    description,
    icon: Icon,
    action,
    children,
    className,
    compact = false,
}: {
    title: string;
    description?: string;
    icon?: LucideIcon;
    action?: React.ReactNode;
    children: React.ReactNode;
    className?: string;
    compact?: boolean;
}) {
    return (
        <section
            className={cn(
                'surface min-w-0',
                compact ? 'p-4' : 'p-5 sm:p-6',
                className,
            )}
        >
            <div
                className={cn(
                    'flex flex-wrap items-start justify-between gap-3',
                    compact ? 'mb-4' : 'mb-5',
                )}
            >
                <div className="flex min-w-0 items-start gap-3">
                    {Icon && (
                        <span
                            className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-accent text-primary"
                            aria-hidden="true"
                        >
                            <Icon className="size-[18px]" />
                        </span>
                    )}
                    <div className="min-w-0">
                        <h2
                            className={cn(
                                'font-bold text-foreground',
                                compact ? 'text-sm' : 'text-base',
                            )}
                        >
                            {title}
                        </h2>
                        {description && (
                            <p
                                className={cn(
                                    'text-muted-foreground',
                                    compact ? 'mt-0.5 text-xs' : 'text-sm',
                                )}
                            >
                                {description}
                            </p>
                        )}
                    </div>
                </div>
                {action}
            </div>
            {children}
        </section>
    );
}

const kpiTones: Record<Tone, string> = {
    primary: 'bg-accent text-primary',
    success: 'bg-success-soft text-success',
    warning: 'bg-warning-soft text-warning',
    info: 'bg-info-soft text-info',
    danger: 'bg-danger-soft text-danger',
    neutral: 'bg-secondary text-muted-foreground',
};

export function KpiCard({
    label,
    value,
    hint,
    href,
    icon: Icon,
    tone = 'primary',
    compact = false,
}: {
    label: string;
    value: number;
    hint: string;
    href: string | null;
    icon?: LucideIcon;
    tone?: Tone;
    compact?: boolean;
}) {
    const body = (
        <>
            <div className="flex items-start justify-between gap-3">
                <p
                    className={cn(
                        'text-xs font-medium text-muted-foreground',
                        !compact && 'sm:text-sm',
                    )}
                >
                    {label}
                </p>
                {Icon && (
                    <span
                        className={cn(
                            'flex size-8 shrink-0 items-center justify-center rounded-lg',
                            !compact && 'sm:size-10 sm:rounded-xl',
                            kpiTones[tone],
                        )}
                        aria-hidden="true"
                    >
                        <Icon
                            className={cn('size-4', !compact && 'sm:size-5')}
                        />
                    </span>
                )}
            </div>
            <p
                className={cn(
                    'font-heading text-2xl font-extrabold tracking-tight text-foreground tabular-nums',
                    compact ? 'mt-1' : 'mt-2 sm:text-3xl',
                )}
            >
                {formatNumber(value)}
            </p>
            <p className="mt-1 flex items-start gap-1 text-xs text-muted-foreground">
                {hint}
                {href && (
                    <ArrowUpRight
                        className="ml-auto size-4 text-muted-foreground transition-colors group-hover:text-primary"
                        aria-hidden="true"
                    />
                )}
            </p>
        </>
    );
    const className = cn(
        'surface group block min-w-0 transition-colors',
        compact ? 'p-3.5' : 'p-4 sm:p-5',
    );
    return href ? (
        <Link
            href={href}
            className={`${className} hover:border-primary/40 hover:shadow-card focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none`}
        >
            {body}
        </Link>
    ) : (
        <div className={className}>{body}</div>
    );
}

const toneBars: Record<Tone, string> = {
    primary: 'bg-primary',
    success: 'bg-success',
    warning: 'bg-warning',
    info: 'bg-info',
    danger: 'bg-danger',
    neutral: 'bg-muted-foreground/50',
};

const barColors = [
    'bg-chart-1',
    'bg-chart-2',
    'bg-chart-3',
    'bg-chart-4',
    'bg-chart-5',
];

/** Single-series horizontal bars: every value is written out, the bar only repeats it visually. */
export function BarList({
    items,
    empty,
    label,
    colorful = false,
}: {
    items: BarItem[];
    empty: string;
    label: string;
    /** One chart colour per row, for categorical series such as statuses. */
    colorful?: boolean;
}) {
    if (items.length === 0)
        return (
            <p className="rounded-lg border border-dashed px-4 py-6 text-center text-sm text-muted-foreground">
                {empty}
            </p>
        );
    const max = Math.max(...items.map((item) => item.value), 1);
    const total = items.reduce((sum, item) => sum + item.value, 0);
    return (
        <ul className="space-y-3.5" aria-label={label}>
            {items.map((item, index) => (
                <li
                    key={`${item.id ?? 'none'}-${index}`}
                    title={`${item.label} : ${formatNumber(item.value)}`}
                >
                    <div className="flex items-baseline justify-between gap-3 text-sm">
                        <span className="min-w-0 truncate text-foreground">
                            {item.label}
                        </span>
                        <span className="shrink-0 font-semibold tabular-nums">
                            {formatNumber(item.value)}
                            {total > 0 && (
                                <span className="ml-1.5 text-xs font-normal text-muted-foreground">
                                    {Math.round((item.value / total) * 100)} %
                                </span>
                            )}
                        </span>
                    </div>
                    <div
                        className="mt-1.5 h-2 overflow-hidden rounded-full bg-muted"
                        aria-hidden="true"
                    >
                        <div
                            className={cn(
                                'h-2 rounded-full transition-[width] duration-500',
                                item.tone
                                    ? toneBars[item.tone]
                                    : colorful
                                      ? barColors[index % barColors.length]
                                      : 'bg-primary',
                            )}
                            style={{
                                width: `${item.value === 0 ? 0 : Math.max((item.value / max) * 100, 2)}%`,
                            }}
                        />
                    </div>
                </li>
            ))}
        </ul>
    );
}

export function YearSelect({
    years,
    value,
    path,
    params = {},
}: {
    years: Year[];
    value: number | null;
    path: string;
    /** Other query parameters kept when the year changes. */
    params?: Record<string, string>;
}) {
    if (years.length === 0) return null;
    return (
        <Select
            value={value === null ? undefined : String(value)}
            onValueChange={(year) =>
                router.get(path, { ...params, year }, { preserveScroll: true })
            }
        >
            <SelectTrigger
                className="w-full sm:w-56"
                aria-label="Année universitaire"
            >
                <SelectValue placeholder="Année universitaire" />
            </SelectTrigger>
            <SelectContent>
                {years.map((year) => (
                    <SelectItem key={year.id} value={String(year.id)}>
                        {year.name}
                        {year.is_current ? ' (courante)' : ''}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}

export function YearLine({ scope }: { scope: Scope }) {
    return scope.year ? (
        <p className="flex flex-wrap items-center gap-2 text-sm">
            <span className="inline-flex items-center gap-1.5 rounded-full bg-accent px-3 py-1 font-medium text-accent-foreground">
                <CalendarDays className="size-3.5" aria-hidden="true" />
                Année {scope.year.name}
                {!scope.year.is_current && ' (non courante)'}
            </span>
            <span className="inline-flex items-center gap-1.5 rounded-full bg-secondary px-3 py-1 font-medium text-secondary-foreground">
                {scope.global ? (
                    <Globe2 className="size-3.5" aria-hidden="true" />
                ) : (
                    <Building2 className="size-3.5" aria-hidden="true" />
                )}
                {scope.global
                    ? 'Vue globale'
                    : (scope.establishment ?? 'Votre établissement')}
            </span>
        </p>
    ) : (
        <p
            role="status"
            className="flex items-start gap-2 rounded-lg border border-warning/30 bg-warning-soft p-3 text-sm text-warning-foreground"
        >
            <AlertTriangle
                className="mt-0.5 size-4 shrink-0"
                aria-hidden="true"
            />
            Aucune année universitaire courante n’est définie : les statistiques
            annuelles ne peuvent pas être calculées. Un superadmin doit activer
            une année courante dans les référentiels.
        </p>
    );
}
