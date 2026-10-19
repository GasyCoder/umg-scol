import { lazy, Suspense } from 'react';
import type { ComponentProps, ReactNode } from 'react';
import type * as Renderers from './chart-renderers';

export type { DailyPoint } from './chart-renderers';

// Recharts (~110 kB gzip) stays out of the page chunk; its download starts as soon as this module runs.
const renderers = import('./chart-renderers');
const Distribution = lazy(() =>
    renderers.then((module) => ({ default: module.DistributionChart })),
);
const Donut = lazy(() =>
    renderers.then((module) => ({ default: module.DonutChart })),
);
const Trend = lazy(() =>
    renderers.then((module) => ({ default: module.ReceptionTrendChart })),
);

function Placeholder({ children }: { children: ReactNode }) {
    return (
        <div
            role="status"
            className="flex h-60 items-center justify-center rounded-lg border border-dashed px-6 text-center text-sm text-muted-foreground"
        >
            {children}
        </div>
    );
}

function Chart({ children }: { children: ReactNode }) {
    return (
        <Suspense
            fallback={<Placeholder>Chargement du graphique…</Placeholder>}
        >
            {children}
        </Suspense>
    );
}

export function DistributionChart(
    props: ComponentProps<typeof Renderers.DistributionChart>,
) {
    if (!props.items.some((item) => item.value > 0))
        return <Placeholder>{props.empty}</Placeholder>;
    return (
        <Chart>
            <Distribution {...props} />
        </Chart>
    );
}

export function DonutChart(props: ComponentProps<typeof Renderers.DonutChart>) {
    if (!props.items.some((item) => item.value > 0))
        return <Placeholder>{props.empty}</Placeholder>;
    return (
        <Chart>
            <Donut {...props} />
        </Chart>
    );
}

export function ReceptionTrendChart(
    props: ComponentProps<typeof Renderers.ReceptionTrendChart>,
) {
    if (!props.points.some((point) => point.value > 0))
        return (
            <Placeholder>
                Aucun dossier reçu sur les 30 derniers jours pour cette année
                universitaire.
            </Placeholder>
        );
    return (
        <Chart>
            <Trend {...props} />
        </Chart>
    );
}
