import { ToneBadge } from '@/components/tone-badge';
import type { Tone } from '@/components/tone-badge';

const tones: Record<string, Tone> = {
    pending: 'warning',
    approved: 'success',
    rejected: 'danger',
    cancelled: 'neutral',
};

export function CorrectionStatusBadge({
    status,
    label,
}: {
    status: string;
    label: string;
}) {
    return <ToneBadge tone={tones[status] ?? 'neutral'}>{label}</ToneBadge>;
}
