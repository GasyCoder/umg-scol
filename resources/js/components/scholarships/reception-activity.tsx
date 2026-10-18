import { Link } from '@inertiajs/react';
import { ChartColumn } from 'lucide-react';
import { missingLabels } from '@/components/scholarships/reception-shared';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';

export type ReceptionActivityData = {
    today: number;
    week: number;
    /** Files received for the academic year being received. */
    year: number | null;
    updatedToday: number;
    days: { date: string; count: number }[];
    recent: {
        id: number;
        event: string;
        label: string;
        student: string | null;
        /** N° DDB of the file concerned. */
        reference: string | null;
        href: string | null;
        fields: string[];
        at: string | null;
    }[];
};

const time = (value: string | null) => {
    if (!value) return '';
    const date = new Date(value);
    const sameDay = date.toDateString() === new Date().toDateString();
    return sameDay
        ? date.toLocaleTimeString('fr-FR', {
              hour: '2-digit',
              minute: '2-digit',
          })
        : date.toLocaleDateString('fr-FR', {
              day: '2-digit',
              month: 'short',
          });
};

const weekday = (value: string) =>
    new Date(`${value}T00:00:00`)
        .toLocaleDateString('fr-FR', { weekday: 'short' })
        .replace('.', '');

/** Button opening, on the right, the agent's own work at the counter: counts, last seven days and latest actions. */
export function ReceptionActivity({
    activity,
    reportHref,
}: {
    activity: ReceptionActivityData;
    reportHref: string | null;
}) {
    const max = Math.max(1, ...activity.days.map((day) => day.count));
    const figures: [string, number][] = [
        ['Aujourd’hui', activity.today],
        ['7 derniers jours', activity.week],
        ...(activity.year !== null
            ? ([['Cette année', activity.year]] as [string, number][])
            : []),
        ['Fiches complétées aujourd’hui', activity.updatedToday],
    ];

    return (
        <Sheet>
            <SheetTrigger asChild>
                <Button type="button" variant="outline">
                    <ChartColumn aria-hidden="true" />
                    Mon activité
                    {activity.today > 0 && (
                        <span
                            className="rounded-full bg-primary px-1.5 text-xs font-semibold text-primary-foreground tabular-nums"
                            aria-label={`${activity.today} reçu${activity.today > 1 ? 's' : ''} aujourd’hui`}
                        >
                            {activity.today}
                        </span>
                    )}
                </Button>
            </SheetTrigger>
            <SheetContent className="w-full gap-0 overflow-y-auto sm:max-w-md">
                <SheetHeader className="border-b px-6 py-5 pr-12">
                    <SheetTitle className="text-lg">Mon activité</SheetTitle>
                    <SheetDescription>
                        Votre travail à la réception des dossiers.
                    </SheetDescription>
                </SheetHeader>
                <div className="flex-1 space-y-6 px-6 py-5">
                    <dl className="grid grid-cols-2 gap-x-4 gap-y-3">
                        {figures.map(([label, value]) => (
                            <div key={label} className="min-w-0">
                                <dt className="text-xs text-muted-foreground">
                                    {label}
                                </dt>
                                <dd className="font-heading text-2xl font-bold text-foreground tabular-nums">
                                    {value}
                                </dd>
                            </div>
                        ))}
                    </dl>

                    <figure className="space-y-1.5">
                        <figcaption className="text-xs text-muted-foreground">
                            Dossiers reçus par jour
                        </figcaption>
                        <ol className="flex h-20 items-end gap-1.5">
                            {activity.days.map((day) => (
                                <li
                                    key={day.date}
                                    className="flex h-full flex-1 flex-col items-center justify-end gap-1"
                                    title={`${new Date(`${day.date}T00:00:00`).toLocaleDateString('fr-FR')} : ${day.count} dossier${day.count > 1 ? 's' : ''}`}
                                >
                                    <span className="text-[10px] text-muted-foreground tabular-nums">
                                        {day.count > 0 ? day.count : ''}
                                    </span>
                                    <span
                                        className="w-full rounded-sm bg-primary/80"
                                        style={{
                                            height: `${Math.max(day.count > 0 ? 8 : 2, (day.count / max) * 100)}%`,
                                            opacity: day.count > 0 ? 1 : 0.25,
                                        }}
                                        aria-hidden="true"
                                    />
                                    <span className="text-[10px] text-muted-foreground capitalize">
                                        {weekday(day.date)}
                                    </span>
                                </li>
                            ))}
                        </ol>
                    </figure>

                    <div className="space-y-2">
                        <h3 className="text-sm font-semibold text-foreground">
                            Dernières actions
                        </h3>
                        {activity.recent.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Rien pour l’instant. Vos réceptions et les
                                fiches que vous complétez apparaîtront ici.
                            </p>
                        ) : (
                            <ol className="divide-y text-sm">
                                {activity.recent.map((item) => (
                                    <li
                                        key={item.id}
                                        className="flex items-start gap-3 py-2"
                                    >
                                        <span className="w-12 shrink-0 pt-px text-xs text-muted-foreground tabular-nums">
                                            {time(item.at)}
                                        </span>
                                        <div className="min-w-0 flex-1">
                                            <p className="text-foreground">
                                                {item.label}
                                                {item.reference && (
                                                    <span className="text-muted-foreground">
                                                        {' '}
                                                        · N° DDB{' '}
                                                        {item.reference}
                                                    </span>
                                                )}
                                            </p>
                                            {item.student &&
                                                (item.href ? (
                                                    <Link
                                                        href={item.href}
                                                        className="block truncate text-xs text-primary hover:underline"
                                                    >
                                                        {item.student}
                                                    </Link>
                                                ) : (
                                                    <p className="truncate text-xs text-muted-foreground">
                                                        {item.student}
                                                    </p>
                                                ))}
                                            {item.fields.length > 0 && (
                                                <p className="text-xs text-muted-foreground">
                                                    {item.fields
                                                        .map(
                                                            (field) =>
                                                                missingLabels[
                                                                    field
                                                                ] ?? field,
                                                        )
                                                        .join(', ')}
                                                </p>
                                            )}
                                        </div>
                                    </li>
                                ))}
                            </ol>
                        )}
                    </div>
                </div>
                {reportHref && (
                    <div className="border-t px-6 py-4">
                        <Button variant="outline" className="w-full" asChild>
                            <Link href={reportHref}>
                                Voir mon rapport complet
                            </Link>
                        </Button>
                    </div>
                )}
            </SheetContent>
        </Sheet>
    );
}
