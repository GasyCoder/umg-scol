import { Link, router } from '@inertiajs/react';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

export type Year = { id: number; name: string; is_current: boolean };
export type Scope = {
    year: { id: number; name: string; is_current: boolean } | null;
    global: boolean;
    establishment: string | null;
};
export type BarItem = { id?: number | null; label: string; value: number };

export const applicationStatusLabels: Record<string, string> = {
    pending: 'Reçus',
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
    action,
    children,
}: {
    title: string;
    description?: string;
    action?: React.ReactNode;
    children: React.ReactNode;
}) {
    return (
        <section className="min-w-0 rounded-xl border bg-card p-5">
            <div className="mb-4 flex flex-wrap items-start justify-between gap-2">
                <div>
                    <h2 className="font-semibold">{title}</h2>
                    {description && (
                        <p className="text-sm text-muted-foreground">
                            {description}
                        </p>
                    )}
                </div>
                {action}
            </div>
            {children}
        </section>
    );
}

export function KpiCard({
    label,
    value,
    hint,
    href,
}: {
    label: string;
    value: number;
    hint: string;
    href: string | null;
}) {
    const body = (
        <>
            <p className="text-sm text-muted-foreground">{label}</p>
            <p className="mt-1 text-3xl font-semibold tabular-nums">
                {formatNumber(value)}
            </p>
            <p className="mt-1 text-xs text-muted-foreground">{hint}</p>
        </>
    );
    const className =
        'block min-w-0 rounded-xl border bg-card p-5 transition-colors';
    return href ? (
        <Link
            href={href}
            className={`${className} hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none`}
        >
            {body}
        </Link>
    ) : (
        <div className={className}>{body}</div>
    );
}

/** Single-series horizontal bars: every value is written out, the bar only repeats it visually. */
export function BarList({
    items,
    empty,
    label,
}: {
    items: BarItem[];
    empty: string;
    label: string;
}) {
    if (items.length === 0)
        return <p className="text-sm text-muted-foreground">{empty}</p>;
    const max = Math.max(...items.map((item) => item.value), 1);
    const total = items.reduce((sum, item) => sum + item.value, 0);
    return (
        <ul className="space-y-3" aria-label={label}>
            {items.map((item, index) => (
                <li
                    key={`${item.id ?? 'none'}-${index}`}
                    title={`${item.label} : ${formatNumber(item.value)}`}
                >
                    <div className="flex items-baseline justify-between gap-3 text-sm">
                        <span className="min-w-0 truncate">{item.label}</span>
                        <span className="shrink-0 font-medium tabular-nums">
                            {formatNumber(item.value)}
                            {total > 0 && (
                                <span className="ml-1 text-xs font-normal text-muted-foreground">
                                    ({Math.round((item.value / total) * 100)} %)
                                </span>
                            )}
                        </span>
                    </div>
                    <div
                        className="mt-1 h-2 rounded-full bg-muted"
                        aria-hidden="true"
                    >
                        <div
                            className="h-2 rounded-full bg-primary"
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
}: {
    years: Year[];
    value: number | null;
    path: string;
}) {
    if (years.length === 0) return null;
    return (
        <Select
            value={value === null ? undefined : String(value)}
            onValueChange={(year) =>
                router.get(path, { year }, { preserveScroll: true })
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
        <p className="text-sm">
            Année universitaire : <strong>{scope.year.name}</strong>
            {!scope.year.is_current && ' (année non courante)'}
            {' · '}
            {scope.global
                ? 'Vue globale'
                : `Périmètre : ${scope.establishment ?? 'votre établissement'}`}
        </p>
    ) : (
        <p
            role="status"
            className="rounded-md border border-dashed p-3 text-sm"
        >
            Aucune année universitaire courante n’est définie : les statistiques
            annuelles ne peuvent pas être calculées. Un superadmin doit activer
            une année courante dans les référentiels.
        </p>
    );
}
