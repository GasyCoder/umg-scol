import type { ComponentProps, ReactElement } from "react";
import { ResponsiveContainer } from "recharts";
import type { TooltipContentProps } from "recharts";
import { cn } from "@/lib/utils";

/** Chart surface using the same theme tokens as the shadcn/ui components. */
export function ChartContainer({
    children,
    className,
    ...props
}: Omit<ComponentProps<"div">, "children"> & { children: ReactElement }) {
    return (
        <div
            data-slot="chart"
            className={cn(
                "h-60 min-w-0 text-xs [&_.recharts-cartesian-axis-tick_text]:fill-muted-foreground [&_.recharts-cartesian-grid_line]:stroke-border [&_.recharts-layer:focus-visible]:outline-ring [&_.recharts-surface:focus-visible]:outline-ring",
                className,
            )}
            {...props}
        >
            <ResponsiveContainer
                width="100%"
                height="100%"
                minWidth={0}
                initialDimension={{ width: 320, height: 240 }}
            >
                {children}
            </ResponsiveContainer>
        </div>
    );
}

export function ChartTooltipContent({
    active,
    payload,
    label,
    labelFormatter,
}: Partial<TooltipContentProps<number, string>>) {
    if (!active || !payload?.length) return null;
    return (
        <div className="max-w-64 rounded-lg border bg-popover px-3 py-2 text-xs text-popover-foreground shadow-elevated">
            {label != null && (
                <p className="mb-1.5 font-medium">
                    {labelFormatter ? labelFormatter(label, payload) : label}
                </p>
            )}
            {payload.map((item, index) => (
                <p key={index} className="flex items-center justify-between gap-4">
                    <span className="flex min-w-0 items-center gap-2">
                        <span
                            className="size-2 shrink-0 rounded-sm"
                            style={{ backgroundColor: item.color ?? item.fill }}
                            aria-hidden="true"
                        />
                        {item.name}
                    </span>
                    <span className="font-semibold tabular-nums">
                        {typeof item.value === "number"
                            ? new Intl.NumberFormat("fr-FR").format(item.value)
                            : "—"}
                    </span>
                </p>
            ))}
        </div>
    );
}
