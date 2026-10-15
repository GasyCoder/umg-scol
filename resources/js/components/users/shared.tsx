import {
    Building2,
    Code2,
    Landmark,
    ShieldCheck,
    UserRound,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { ToneBadge } from '@/components/tone-badge';
import type { Tone } from '@/components/tone-badge';
import { cn } from '@/lib/utils';

export type UserSummary = {
    id: number;
    uuid: string;
    name: string;
    email: string;
    is_active: boolean;
    role: string | null;
    role_label: string | null;
    establishment: { id: number; code: string; name: string } | null;
};

export type RoleOption = {
    value: string;
    label: string;
    description: string;
    permissions_count: number;
};

export const roleMeta: Record<
    string,
    { icon: LucideIcon; tone: Tone; tile: string }
> = {
    superadmin: {
        icon: ShieldCheck,
        tone: 'primary',
        tile: 'bg-accent text-primary',
    },
    central_scholarship_office: {
        icon: Landmark,
        tone: 'info',
        tile: 'bg-info-soft text-info',
    },
    establishment: {
        icon: Building2,
        tone: 'success',
        tile: 'bg-success-soft text-success',
    },
    developer: {
        icon: Code2,
        tone: 'warning',
        tile: 'bg-warning-soft text-warning',
    },
};

const fallback = {
    icon: UserRound,
    tone: 'neutral' as Tone,
    tile: 'bg-secondary text-muted-foreground',
};

export const metaFor = (role: string | null) =>
    role ? (roleMeta[role] ?? fallback) : fallback;

export function initials(name: string): string {
    return name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase())
        .join('');
}

/** Initials tile tinted by role; greyed out when the account is suspended. */
export function UserAvatar({
    user,
    size = 'md',
}: {
    user: Pick<UserSummary, 'name' | 'role' | 'is_active'>;
    size?: 'md' | 'lg';
}) {
    return (
        <span
            className={cn(
                'flex shrink-0 items-center justify-center rounded-full font-heading font-bold',
                size === 'lg'
                    ? 'size-16 text-xl ring-4 ring-card'
                    : 'size-10 text-sm',
                user.is_active
                    ? metaFor(user.role).tile
                    : 'bg-muted text-muted-foreground',
            )}
            aria-hidden="true"
        >
            {initials(user.name)}
        </span>
    );
}

export function RoleBadge({
    role,
    label,
}: {
    role: string | null;
    label: string | null;
}) {
    const meta = metaFor(role);
    const Icon = meta.icon;
    return (
        <ToneBadge tone={meta.tone}>
            <Icon className="size-3" aria-hidden="true" />
            {label ?? 'Sans rôle'}
        </ToneBadge>
    );
}

export function AccountStatusBadge({ active }: { active: boolean }) {
    return (
        <ToneBadge tone={active ? 'success' : 'danger'}>
            {active ? 'Actif' : 'Suspendu'}
        </ToneBadge>
    );
}
