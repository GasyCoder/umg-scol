import { useState } from 'react';
import {
    Area,
    AreaChart,
    Bar,
    BarChart,
    CartesianGrid,
    LabelList,
    Pie,
    PieChart,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { ChartContainer, ChartTooltipContent } from '@/components/ui/chart';
import { Button } from '@/components/ui/button';
import { formatNumber } from '@/components/reporting/shared';
import type { BarItem } from '@/components/reporting/shared';
import type { Tone } from '@/components/tone-badge';

export type DailyPoint = { date: string; value: number };
const colors = [
    'var(--chart-1)',
    'var(--chart-2)',
    'var(--chart-3)',
    'var(--chart-4)',
    'var(--chart-5)',
];
const tones: Record<Tone, string> = {
    primary: 'var(--primary)',
    success: 'var(--success)',
    warning: 'var(--warning)',
    info: 'var(--info)',
    danger: 'var(--danger)',
    neutral: 'var(--muted-foreground)',
};
const shortNumber = (value: number) =>
    new Intl.NumberFormat('fr-FR', {
        notation: 'compact',
        maximumFractionDigits: 1,
    }).format(value);
const shortLabel = (label: string) =>
    label.length > 18 ? `${label.slice(0, 17)}…` : label;
const dateLabel = (date: string, full = false) =>
    new Intl.DateTimeFormat('fr-FR', {
        day: 'numeric',
        month: full ? 'long' : 'short',
        ...(full ? { year: 'numeric' } : {}),
    }).format(new Date(`${date}T12:00:00`));

function NoChart({ message }: { message: string }) {
    return (
        <div
            role="status"
            className="flex h-60 items-center justify-center rounded-lg border border-dashed px-6 text-center text-sm text-muted-foreground"
        >
            {message}
        </div>
    );
}

/** Exact values remain available to keyboard and screen reader users. */
function ChartData({ items, label }: { items: BarItem[]; label: string }) {
    return (
        <details className="group mt-3 border-t pt-1 text-xs">
            <summary className="flex min-h-9 cursor-pointer items-center justify-between gap-2 rounded-sm text-muted-foreground outline-none hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring">
                <span>Voir les données</span>
                <span
                    aria-hidden="true"
                    className="text-base group-open:rotate-45"
                >
                    +
                </span>
            </summary>
            <table className="mt-1 w-full text-left">
                <caption className="sr-only">{label}</caption>
                <thead className="sr-only">
                    <tr>
                        <th>Catégorie</th>
                        <th>Nombre</th>
                    </tr>
                </thead>
                <tbody>
                    {items.map((item, index) => (
                        <tr key={index} className="border-t">
                            <th scope="row" className="py-2 pr-3 font-normal">
                                {item.label}
                            </th>
                            <td className="text-right font-medium tabular-nums">
                                {formatNumber(item.value)}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </details>
    );
}

export function DistributionChart({
    items,
    label,
    empty,
    horizontal = false,
}: {
    items: BarItem[];
    label: string;
    empty: string;
    horizontal?: boolean;
}) {
    const [expanded, setExpanded] = useState(false);
    if (!items.some((item) => item.value > 0))
        return <NoChart message={empty} />;
    const visible = expanded ? items : items.slice(0, horizontal ? 8 : 12);
    const limit = horizontal ? 8 : 12;
    const data = visible.map((item, index) => ({
        ...item,
        fill: item.tone ? tones[item.tone] : colors[index % colors.length],
    }));
    return (
        <figure aria-label={label} className="min-w-0">
            <div
                className={
                    expanded && horizontal
                        ? 'max-h-80 overflow-y-auto'
                        : undefined
                }
            >
                <ChartContainer
                    style={{
                        height: horizontal
                            ? Math.max(240, visible.length * 34)
                            : 240,
                    }}
                >
                    <BarChart
                        accessibilityLayer
                        data={data}
                        layout={horizontal ? 'vertical' : 'horizontal'}
                        margin={{ top: 20, right: 26, left: 0, bottom: 0 }}
                        barSize={horizontal ? 16 : undefined}
                        maxBarSize={40}
                    >
                        <CartesianGrid
                            horizontal={!horizontal}
                            vertical={horizontal}
                            strokeDasharray="3 3"
                        />
                        {horizontal ? (
                            <>
                                <XAxis
                                    type="number"
                                    allowDecimals={false}
                                    tickFormatter={shortNumber}
                                    tickLine={false}
                                    axisLine={false}
                                />
                                <YAxis
                                    type="category"
                                    dataKey="label"
                                    width={115}
                                    tickFormatter={shortLabel}
                                    tickLine={false}
                                    axisLine={false}
                                    interval={0}
                                />
                            </>
                        ) : (
                            <>
                                <XAxis
                                    dataKey="label"
                                    tickFormatter={shortLabel}
                                    tickLine={false}
                                    axisLine={false}
                                    tickMargin={10}
                                    minTickGap={8}
                                    height={40}
                                />
                                <YAxis
                                    allowDecimals={false}
                                    tickFormatter={shortNumber}
                                    tickLine={false}
                                    axisLine={false}
                                    width={40}
                                />
                            </>
                        )}
                        <Tooltip
                            cursor={{ fill: 'var(--muted)', fillOpacity: 0.6 }}
                            content={<ChartTooltipContent />}
                        />
                        <Bar
                            dataKey="value"
                            name="Nombre"
                            radius={horizontal ? [0, 4, 4, 0] : [4, 4, 0, 0]}
                            isAnimationActive={false}
                        >
                            <LabelList
                                dataKey="value"
                                position={horizontal ? 'right' : 'top'}
                                fill="var(--foreground)"
                                fontSize={11}
                                formatter={(value) =>
                                    typeof value === 'number'
                                        ? shortNumber(value)
                                        : ''
                                }
                            />
                        </Bar>
                    </BarChart>
                </ChartContainer>
            </div>
            {items.length > limit && (
                <div className="mt-1 flex items-center justify-between gap-2 text-xs text-muted-foreground">
                    <span>
                        {visible.length} sur {items.length} catégories
                    </span>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() => setExpanded(!expanded)}
                    >
                        {expanded ? 'Réduire' : 'Tout afficher'}
                    </Button>
                </div>
            )}
            <ChartData items={items} label={label} />
        </figure>
    );
}

export function DonutChart({
    items,
    label,
    empty,
    unit,
    summary,
}: {
    items: BarItem[];
    label: string;
    empty: string;
    unit: string;
    summary?: { value: string; label: string };
}) {
    const total = items.reduce((sum, item) => sum + item.value, 0);
    if (total === 0) return <NoChart message={empty} />;
    const data = items.map((item, index) => ({
        ...item,
        fill: item.tone ? tones[item.tone] : colors[index % colors.length],
    }));
    return (
        <figure aria-label={label}>
            <div className="relative mx-auto max-w-64">
                <ChartContainer className="h-44">
                    <PieChart accessibilityLayer>
                        <Tooltip content={<ChartTooltipContent />} />
                        <Pie
                            data={data.filter((item) => item.value > 0)}
                            dataKey="value"
                            nameKey="label"
                            innerRadius={56}
                            outerRadius={77}
                            paddingAngle={
                                data.filter((item) => item.value > 0).length > 1
                                    ? 3
                                    : 0
                            }
                            cornerRadius={3}
                            stroke="var(--card)"
                            strokeWidth={2}
                            isAnimationActive={false}
                        />
                    </PieChart>
                </ChartContainer>
                <div
                    className="pointer-events-none absolute inset-0 flex flex-col items-center justify-center"
                    aria-hidden="true"
                >
                    <span className="font-heading text-2xl font-bold tabular-nums">
                        {summary?.value ?? formatNumber(total)}
                    </span>
                    <span className="max-w-24 text-center text-[11px] leading-tight text-muted-foreground">
                        {summary?.label ?? unit}
                    </span>
                </div>
            </div>
            {summary && (
                <p className="sr-only">
                    {summary.value} {summary.label}
                </p>
            )}
            <ul
                className="mt-2 grid grid-cols-2 gap-x-3 gap-y-2 text-xs"
                aria-label="Légende"
            >
                {data.map((item) => (
                    <li key={item.label} className="flex items-start gap-1.5">
                        <span
                            className="mt-1 size-2 shrink-0 rounded-full"
                            style={{ backgroundColor: item.fill }}
                            aria-hidden="true"
                        />
                        <span className="min-w-0 flex-1 text-muted-foreground">
                            {item.label}
                        </span>
                        <span
                            className="shrink-0 font-semibold tabular-nums"
                            title={`${((item.value / total) * 100).toFixed(1)} %`}
                        >
                            {formatNumber(item.value)}
                        </span>
                    </li>
                ))}
            </ul>
            <ChartData items={items} label={label} />
        </figure>
    );
}

export function ReceptionTrendChart({ points }: { points: DailyPoint[] }) {
    const total = points.reduce((sum, point) => sum + point.value, 0);
    if (total === 0)
        return (
            <NoChart message="Aucun dossier reçu sur les 30 derniers jours pour cette année universitaire." />
        );
    return (
        <figure aria-label="Réceptions quotidiennes des 30 derniers jours">
            <div className="mb-2 flex items-baseline gap-2">
                <span className="font-heading text-xl font-bold tabular-nums">
                    {formatNumber(total)}
                </span>
                <span className="text-xs text-muted-foreground">
                    dossier(s) sur la période
                </span>
            </div>
            <ChartContainer className="h-52">
                <AreaChart
                    accessibilityLayer
                    data={points}
                    margin={{ top: 12, right: 10, left: 0, bottom: 0 }}
                >
                    <CartesianGrid vertical={false} strokeDasharray="3 3" />
                    <XAxis
                        dataKey="date"
                        tickFormatter={(value: string) => dateLabel(value)}
                        tickLine={false}
                        axisLine={false}
                        minTickGap={30}
                        tickMargin={10}
                    />
                    <YAxis
                        allowDecimals={false}
                        tickFormatter={shortNumber}
                        tickLine={false}
                        axisLine={false}
                        width={35}
                    />
                    <Tooltip
                        content={<ChartTooltipContent />}
                        labelFormatter={(value) =>
                            typeof value === 'string'
                                ? dateLabel(value, true)
                                : ''
                        }
                    />
                    <Area
                        type="linear"
                        dataKey="value"
                        name="Dossiers reçus"
                        stroke="var(--primary)"
                        strokeWidth={2}
                        fill="var(--primary)"
                        fillOpacity={0.12}
                        isAnimationActive={false}
                    />
                </AreaChart>
            </ChartContainer>
            <ChartData
                items={points.map((point) => ({
                    label: dateLabel(point.date, true),
                    value: point.value,
                }))}
                label="Réceptions quotidiennes"
            />
        </figure>
    );
}
