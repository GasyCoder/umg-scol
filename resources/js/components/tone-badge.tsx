import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

export type Tone =
    | 'primary'
    | 'success'
    | 'warning'
    | 'info'
    | 'danger'
    | 'neutral';

const dots: Record<Tone, string> = {
    primary: 'bg-primary',
    success: 'bg-success',
    warning: 'bg-warning',
    info: 'bg-info',
    danger: 'bg-danger',
    neutral: 'bg-muted-foreground/60',
};

/**
 * Status pill: soft tinted surface plus a coloured dot, so a status reads by colour
 * and by text (never colour alone).
 */
export function ToneBadge({
    tone,
    children,
    className,
}: {
    tone: Tone;
    children: React.ReactNode;
    className?: string;
}) {
    return (
        <Badge variant={tone} className={cn('gap-1.5', className)}>
            <span
                className={cn('size-1.5 shrink-0 rounded-full', dots[tone])}
                aria-hidden="true"
            />
            {children}
        </Badge>
    );
}

/** Shared status → tone mapping for every workflow (dossiers, corrections, imports, liens). */
export const statusTones: Record<string, Tone> = {
    // Scholarship applications
    pending: 'warning',
    // Received file whose situation waits for the establishment's list.
    awaiting_list: 'neutral',
    in_review: 'info',
    needs_correction: 'warning',
    validated: 'success',
    rejected: 'danger',
    // Historical campaign statuses (audit compatibility)
    draft: 'neutral',
    scheduled: 'info',
    open: 'success',
    closed: 'neutral',
    archived: 'neutral',
    // Corrections and links
    approved: 'success',
    cancelled: 'neutral',
    // Imports
    uploaded: 'neutral',
    analyzing: 'info',
    ready: 'primary',
    committing: 'info',
    completed: 'success',
    failed: 'danger',
    // Records
    active: 'success',
    inactive: 'neutral',
};

export function toneFor(status: string): Tone {
    return statusTones[status] ?? 'neutral';
}
