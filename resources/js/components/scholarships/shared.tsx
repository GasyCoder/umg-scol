import { Link } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

export type Option = { value: string; label: string };
export type PageLink = { url: string | null; label: string; active: boolean };

const variants: Record<
    string,
    'default' | 'secondary' | 'destructive' | 'outline'
> = {
    pending: 'outline',
    in_review: 'default',
    needs_correction: 'default',
    validated: 'secondary',
    rejected: 'destructive',
    draft: 'outline',
    scheduled: 'outline',
    open: 'default',
    closed: 'secondary',
    archived: 'outline',
};

export function StatusBadge({
    status,
    label,
}: {
    status: string;
    label: string;
}) {
    return <Badge variant={variants[status] ?? 'outline'}>{label}</Badge>;
}

export function formatDate(value: string | null, withTime = false): string {
    if (!value) return '—';
    const date = new Date(value);
    return withTime
        ? date.toLocaleString('fr-FR')
        : date.toLocaleDateString('fr-FR');
}

export function Pagination({ links }: { links: PageLink[] }) {
    return (
        <nav aria-label="Pagination" className="flex flex-wrap gap-2">
            {links.map((link, i) =>
                link.url ? (
                    <Button
                        key={i}
                        variant={link.active ? 'default' : 'outline'}
                        size="sm"
                        asChild
                    >
                        <Link href={link.url} preserveState>
                            {i === 0
                                ? 'Précédent'
                                : i === links.length - 1
                                  ? 'Suivant'
                                  : link.label}
                        </Link>
                    </Button>
                ) : null,
            )}
        </nav>
    );
}
