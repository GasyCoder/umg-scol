import { Badge } from '@/components/ui/badge';

const variants: Record<
    string,
    'default' | 'secondary' | 'destructive' | 'outline'
> = {
    pending: 'default',
    approved: 'secondary',
    rejected: 'destructive',
    cancelled: 'outline',
};

export function CorrectionStatusBadge({
    status,
    label,
}: {
    status: string;
    label: string;
}) {
    return <Badge variant={variants[status] ?? 'outline'}>{label}</Badge>;
}
