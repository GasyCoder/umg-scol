import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { ToneBadge, toneFor } from '@/components/tone-badge';
import { cn } from '@/lib/utils';

export type Option = { value: string; label: string };
export type PageLink = { url: string | null; label: string; active: boolean };

export function StatusBadge({
    status,
    label,
}: {
    status: string;
    label: string;
}) {
    return <ToneBadge tone={toneFor(status)}>{label}</ToneBadge>;
}

export function formatDate(value: string | null, withTime = false): string {
    if (!value) return '—';
    const date = new Date(value);
    return withTime
        ? date.toLocaleString('fr-FR')
        : date.toLocaleDateString('fr-FR');
}

export function Pagination({ links }: { links: PageLink[] }) {
    if (links.length <= 3 && links.every((link) => !link.url || link.active))
        return null;
    const item =
        'inline-flex h-9 min-w-9 items-center justify-center gap-1 rounded-lg px-3 text-sm font-medium transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none';
    return (
        <nav
            aria-label="Pagination"
            className="flex flex-wrap items-center gap-1"
        >
            {links.map((link, i) => {
                const first = i === 0;
                const last = i === links.length - 1;
                const label = first ? (
                    <>
                        <ChevronLeft className="size-4" aria-hidden="true" />
                        <span className="hidden sm:inline">Précédent</span>
                    </>
                ) : last ? (
                    <>
                        <span className="hidden sm:inline">Suivant</span>
                        <ChevronRight className="size-4" aria-hidden="true" />
                    </>
                ) : (
                    link.label
                );
                if (!link.url)
                    return (
                        <span
                            key={i}
                            className={cn(item, 'text-muted-foreground/60')}
                            aria-disabled="true"
                        >
                            {label}
                        </span>
                    );
                return (
                    <Link
                        key={i}
                        href={link.url}
                        preserveState
                        aria-current={link.active ? 'page' : undefined}
                        aria-label={
                            first
                                ? 'Page précédente'
                                : last
                                  ? 'Page suivante'
                                  : undefined
                        }
                        className={cn(
                            item,
                            link.active
                                ? 'bg-primary text-primary-foreground shadow-sm'
                                : 'border bg-card text-foreground hover:border-primary/40 hover:text-primary',
                        )}
                    >
                        {label}
                    </Link>
                );
            })}
        </nav>
    );
}
