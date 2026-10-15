import { Head, Link, router } from '@inertiajs/react';
import {
    ChevronRight,
    RotateCcw,
    Search,
    UserPlus,
    UserRoundX,
    UsersRound,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { EmptyState } from '@/components/empty-state';
import { PageBody, PageHeader } from '@/components/page-header';
import { formatNumber } from '@/components/reporting/shared';
import { Pagination } from '@/components/scholarships/shared';
import type { PageLink } from '@/components/scholarships/shared';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    AccountStatusBadge,
    metaFor,
    RoleBadge,
    UserAvatar,
} from '@/components/users/shared';
import type { RoleOption, UserSummary } from '@/components/users/shared';
import { cn } from '@/lib/utils';

type Row = UserSummary & {
    granted_count: number;
    denied_count: number;
    is_self: boolean;
};
type Filters = { search?: string; role?: string; status?: string };

export default function UsersIndex({
    users,
    filters,
    stats,
    roles,
    canCreate,
}: {
    users: { data: Row[]; total: number; links: PageLink[] };
    filters: Filters;
    stats: {
        total: number;
        suspended: number;
        by_role: Record<string, number>;
    };
    roles: RoleOption[];
    canCreate: boolean;
}) {
    const [search, setSearch] = useState(filters.search ?? '');
    const apply = (next: Filters) =>
        router.get(
            '/users',
            Object.fromEntries(
                Object.entries({ ...filters, ...next }).filter(
                    ([, value]) => value,
                ),
            ),
            { preserveState: true, preserveScroll: true },
        );
    const submit = (event: FormEvent) => {
        event.preventDefault();
        apply({ search: search.trim() || undefined });
    };
    const filtered = Boolean(filters.search || filters.role || filters.status);
    const pill = (active: boolean) =>
        cn(
            'inline-flex h-9 items-center gap-2 rounded-full border px-3.5 text-sm font-medium whitespace-nowrap transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
            active
                ? 'border-primary bg-primary text-primary-foreground shadow-sm'
                : 'bg-card text-foreground hover:border-primary/40 hover:text-primary',
        );

    return (
        <>
            <Head title="Utilisateurs" />
            <PageBody>
                <PageHeader
                    icon={UsersRound}
                    title="Utilisateurs"
                    description="Comptes du portail interne : chaque compte reçoit un rôle et ses droits par défaut, ajustables compte par compte."
                    actions={
                        canCreate && (
                            <Button asChild>
                                <Link href="/users/create">
                                    <UserPlus aria-hidden="true" />
                                    Nouveau compte
                                </Link>
                            </Button>
                        )
                    }
                />

                <div className="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4 xl:grid-cols-6">
                    <button
                        type="button"
                        onClick={() =>
                            apply({ role: undefined, status: undefined })
                        }
                        className="surface flex flex-col items-start gap-1 p-4 text-left transition-all hover:-translate-y-0.5 hover:shadow-elevated focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    >
                        <span className="flex size-9 items-center justify-center rounded-lg bg-accent text-primary">
                            <UsersRound
                                className="size-[18px]"
                                aria-hidden="true"
                            />
                        </span>
                        <span className="mt-1 font-heading text-2xl font-extrabold tabular-nums">
                            {formatNumber(stats.total)}
                        </span>
                        <span className="text-xs text-muted-foreground">
                            Comptes internes
                        </span>
                    </button>
                    {roles.map((role) => {
                        const meta = metaFor(role.value);
                        const Icon = meta.icon;
                        const active = filters.role === role.value;
                        return (
                            <button
                                key={role.value}
                                type="button"
                                aria-pressed={active}
                                onClick={() =>
                                    apply({
                                        role: active ? undefined : role.value,
                                    })
                                }
                                className={cn(
                                    'surface flex flex-col items-start gap-1 p-4 text-left transition-all hover:-translate-y-0.5 hover:shadow-elevated focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                    active &&
                                        'border-primary ring-2 ring-primary/20',
                                )}
                            >
                                <span
                                    className={cn(
                                        'flex size-9 items-center justify-center rounded-lg',
                                        meta.tile,
                                    )}
                                >
                                    <Icon
                                        className="size-[18px]"
                                        aria-hidden="true"
                                    />
                                </span>
                                <span className="mt-1 font-heading text-2xl font-extrabold tabular-nums">
                                    {formatNumber(
                                        stats.by_role[role.value] ?? 0,
                                    )}
                                </span>
                                <span className="text-xs text-muted-foreground">
                                    {role.label}
                                </span>
                            </button>
                        );
                    })}
                    <button
                        type="button"
                        aria-pressed={filters.status === 'suspended'}
                        onClick={() =>
                            apply({
                                status:
                                    filters.status === 'suspended'
                                        ? undefined
                                        : 'suspended',
                            })
                        }
                        className={cn(
                            'surface flex flex-col items-start gap-1 p-4 text-left transition-all hover:-translate-y-0.5 hover:shadow-elevated focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                            filters.status === 'suspended' &&
                                'border-danger ring-2 ring-danger/20',
                        )}
                    >
                        <span className="flex size-9 items-center justify-center rounded-lg bg-danger-soft text-danger">
                            <UserRoundX
                                className="size-[18px]"
                                aria-hidden="true"
                            />
                        </span>
                        <span className="mt-1 font-heading text-2xl font-extrabold tabular-nums">
                            {formatNumber(stats.suspended)}
                        </span>
                        <span className="text-xs text-muted-foreground">
                            Suspendus
                        </span>
                    </button>
                </div>

                <section className="surface overflow-hidden">
                    <div className="flex flex-col gap-3 border-b p-4 lg:flex-row lg:items-center">
                        <form
                            onSubmit={submit}
                            role="search"
                            className="flex flex-1 gap-2"
                        >
                            <div className="relative flex-1">
                                <Search
                                    className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                    aria-hidden="true"
                                />
                                <Input
                                    aria-label="Rechercher un compte"
                                    placeholder="Nom ou adresse email"
                                    className="h-10 pl-9"
                                    value={search}
                                    maxLength={100}
                                    onChange={(event) =>
                                        setSearch(event.target.value)
                                    }
                                />
                            </div>
                            <Button type="submit" className="h-10">
                                Rechercher
                            </Button>
                        </form>
                        <div
                            className="flex gap-2 overflow-x-auto pb-1 lg:pb-0"
                            role="group"
                            aria-label="Statut du compte"
                        >
                            {[
                                { value: undefined, label: 'Tous' },
                                { value: 'active', label: 'Actifs' },
                                { value: 'suspended', label: 'Suspendus' },
                            ].map((option) => (
                                <button
                                    key={option.label}
                                    type="button"
                                    aria-pressed={
                                        filters.status === option.value
                                    }
                                    onClick={() =>
                                        apply({ status: option.value })
                                    }
                                    className={pill(
                                        filters.status === option.value,
                                    )}
                                >
                                    {option.label}
                                </button>
                            ))}
                            {filtered && (
                                <Button
                                    variant="ghost"
                                    className="h-9 rounded-full"
                                    onClick={() => {
                                        setSearch('');
                                        router.get('/users');
                                    }}
                                >
                                    <RotateCcw aria-hidden="true" />
                                    Réinitialiser
                                </Button>
                            )}
                        </div>
                    </div>

                    {users.data.length === 0 ? (
                        <EmptyState
                            illustration={filtered ? 'search' : 'students'}
                            title={
                                filtered
                                    ? 'Aucun compte ne correspond'
                                    : 'Aucun compte interne'
                            }
                            description={
                                filtered
                                    ? 'Modifiez la recherche ou les filtres.'
                                    : 'Créez le premier compte du personnel.'
                            }
                        />
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="data-table min-w-[52rem]">
                                <thead>
                                    <tr>
                                        <th>Compte</th>
                                        <th>Rôle</th>
                                        <th>Établissement</th>
                                        <th>Droits</th>
                                        <th>Statut</th>
                                        <th className="text-right">
                                            <span className="sr-only">
                                                Actions
                                            </span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {users.data.map((user) => (
                                        <tr
                                            key={user.id}
                                            className={cn(
                                                !user.is_active && 'opacity-70',
                                            )}
                                        >
                                            <td>
                                                <Link
                                                    href={`/users/${user.uuid}`}
                                                    className="group flex items-center gap-3"
                                                >
                                                    <UserAvatar user={user} />
                                                    <span className="min-w-0">
                                                        <span className="flex items-center gap-2 font-semibold text-foreground group-hover:text-primary">
                                                            <span className="truncate">
                                                                {user.name}
                                                            </span>
                                                            {user.is_self && (
                                                                <span className="rounded-full bg-accent px-2 text-[11px] font-semibold text-accent-foreground">
                                                                    Vous
                                                                </span>
                                                            )}
                                                        </span>
                                                        <span className="block truncate text-xs text-muted-foreground">
                                                            {user.email}
                                                        </span>
                                                    </span>
                                                </Link>
                                            </td>
                                            <td>
                                                <RoleBadge
                                                    role={user.role}
                                                    label={user.role_label}
                                                />
                                            </td>
                                            <td className="text-sm">
                                                {user.establishment ? (
                                                    <span className="flex items-center gap-2">
                                                        <span className="rounded-md border bg-muted/60 px-1.5 py-0.5 font-mono text-[11px] font-semibold whitespace-nowrap">
                                                            {
                                                                user
                                                                    .establishment
                                                                    .code
                                                            }
                                                        </span>
                                                        <span className="truncate">
                                                            {
                                                                user
                                                                    .establishment
                                                                    .name
                                                            }
                                                        </span>
                                                    </span>
                                                ) : (
                                                    <span className="text-muted-foreground">
                                                        Accès global
                                                    </span>
                                                )}
                                            </td>
                                            <td>
                                                <RightsSummary
                                                    granted={user.granted_count}
                                                    denied={user.denied_count}
                                                />
                                            </td>
                                            <td>
                                                <AccountStatusBadge
                                                    active={user.is_active}
                                                />
                                            </td>
                                            <td className="text-right">
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    asChild
                                                >
                                                    <Link
                                                        href={`/users/${user.uuid}`}
                                                        aria-label={`Gérer le compte de ${user.name}`}
                                                    >
                                                        Gérer
                                                        <ChevronRight aria-hidden="true" />
                                                    </Link>
                                                </Button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <p
                        className="text-sm text-muted-foreground"
                        aria-live="polite"
                    >
                        {formatNumber(users.total)} compte(s)
                    </p>
                    <Pagination links={users.links} />
                </div>
            </PageBody>
        </>
    );
}

function RightsSummary({
    granted,
    denied,
}: {
    granted: number;
    denied: number;
}) {
    if (granted === 0 && denied === 0)
        return (
            <span className="text-xs text-muted-foreground">
                Droits du rôle
            </span>
        );
    return (
        <span className="flex flex-wrap gap-1.5 text-xs font-semibold">
            {granted > 0 && (
                <span className="rounded-full bg-success-soft px-2 py-0.5 text-success-foreground">
                    +{granted} ajouté{granted > 1 ? 's' : ''}
                </span>
            )}
            {denied > 0 && (
                <span className="rounded-full bg-danger-soft px-2 py-0.5 text-danger-foreground">
                    −{denied} retiré{denied > 1 ? 's' : ''}
                </span>
            )}
        </span>
    );
}

UsersIndex.layout = {
    breadcrumbs: [{ title: 'Utilisateurs', href: '/users' }],
};
