import { Head, router } from '@inertiajs/react';
import {
    Building2,
    CheckCircle2,
    Code2,
    GraduationCap,
    Landmark,
    Lock,
    Minus,
    Plus,
    Search,
    ShieldCheck,
    UserRound,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useState } from 'react';
import { EmptyState } from '@/components/empty-state';
import InputError from '@/components/input-error';
import { PageBody, PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { cn } from '@/lib/utils';
type Role = {
    name: string;
    label: string;
    permissions: string[];
    version: string;
};
type Permission = {
    name: string;
    label: string;
    description: string;
    group: string;
};

const roleIcons: Record<string, LucideIcon> = {
    superadmin: ShieldCheck,
    developer: Code2,
    central_scholarship_office: Landmark,
    establishment: Building2,
    student: GraduationCap,
};

export default function Permissions({
    roles,
    catalogue,
    protectedPermissions,
    studentPermissions,
}: {
    roles: Role[];
    catalogue: Permission[];
    protectedPermissions: string[];
    studentPermissions: string[];
}) {
    const [draft, setDraft] = useState<Record<string, string[]>>(() =>
        Object.fromEntries(roles.map((role) => [role.name, role.permissions])),
    );
    const [versions, setVersions] = useState<Record<string, string>>(() =>
        Object.fromEntries(roles.map((role) => [role.name, role.version])),
    );
    const [search, setSearch] = useState('');
    const [selected, setSelected] = useState<Role | null>(null);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');
    const differences = (role: Role) => ({
        granted: (draft[role.name] ?? []).filter(
            (name) => !role.permissions.includes(name),
        ),
        revoked: role.permissions.filter(
            (name) => !(draft[role.name] ?? []).includes(name),
        ),
    });
    const changes = roles.reduce((total, role) => {
        const diff = differences(role);
        return total + diff.granted.length + diff.revoked.length;
    }, 0);
    const changedRoles = roles.filter((role) => {
        const diff = differences(role);
        return diff.granted.length + diff.revoked.length > 0;
    });
    const filtered = catalogue.filter((item) =>
        `${item.name} ${item.label} ${item.group}`
            .toLocaleLowerCase('fr')
            .includes(search.toLocaleLowerCase('fr')),
    );
    const groups = [...new Set(filtered.map((item) => item.group))];
    const reset = () => {
        setDraft(
            Object.fromEntries(
                roles.map((role) => [role.name, role.permissions]),
            ),
        );
        setVersions(
            Object.fromEntries(roles.map((role) => [role.name, role.version])),
        );
        setError('');
    };
    const reason = (role: Role, name: string) =>
        role.name === 'superadmin' && protectedPermissions.includes(name)
            ? 'Ce droit protège l’accès du superadmin à l’administration.'
            : role.name === 'student' && !studentPermissions.includes(name)
              ? 'Le rôle étudiant est limité aux permissions de son portail.'
              : null;
    const save = () => {
        if (!selected) return;
        setBusy(true);
        setError('');
        router.put(
            `/permissions/roles/${selected.name}`,
            {
                permissions: draft[selected.name],
                version: versions[selected.name],
            },
            {
                preserveScroll: true,
                onError: (errors) =>
                    setError(
                        Object.values(errors)[0] ??
                            'Échec de l’enregistrement.',
                    ),
                onSuccess: (page) => {
                    const updated = page.props.roles as Role[];
                    if (Array.isArray(updated)) {
                        const saved = updated.find(
                            (role) => role.name === selected.name,
                        );
                        if (saved) {
                            setDraft((current) => ({
                                ...current,
                                [saved.name]: saved.permissions,
                            }));
                            setVersions((current) => ({
                                ...current,
                                [saved.name]: saved.version,
                            }));
                        }
                    }
                    setSelected(null);
                },
                onFinish: () => setBusy(false),
            },
        );
    };
    const diff = selected ? differences(selected) : null;
    const label = (name: string) =>
        catalogue.find((item) => item.name === name)?.label ?? name;
    return (
        <>
            <Head title="Permissions" />
            <PageBody>
                <PageHeader
                    icon={ShieldCheck}
                    title="Permissions"
                    description="Les cinq rôles sont fixes. Les permissions accordées sont enregistrées en base et conservées lors des déploiements."
                    meta={
                        <span className="inline-flex items-center rounded-full bg-accent px-3 py-1 text-sm font-medium text-accent-foreground tabular-nums">
                            {roles.length} rôles · {catalogue.length}{' '}
                            permissions
                        </span>
                    }
                />
                <div className="surface flex flex-col gap-3 p-4 md:flex-row md:items-center md:justify-between">
                    <div className="relative w-full md:max-w-md">
                        <Search
                            className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <Input
                            aria-label="Rechercher une permission"
                            placeholder="Rechercher une permission ou un module…"
                            className="pl-9"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                        />
                    </div>
                    <ul className="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted-foreground">
                        <li className="flex items-center gap-1.5">
                            <span
                                className="size-3 rounded-sm bg-warning-soft ring-1 ring-warning/50"
                                aria-hidden="true"
                            />
                            Modifié, non enregistré
                        </li>
                        <li className="flex items-center gap-1.5">
                            <Lock className="size-3.5" aria-hidden="true" />
                            Non modifiable
                        </li>
                    </ul>
                </div>
                <div className="surface overflow-hidden">
                    <div className="max-h-[calc(100svh-14rem)] min-h-64 overflow-auto">
                        <table className="w-full border-separate border-spacing-0 text-sm">
                            <caption className="sr-only">
                                Matrice des permissions par rôle
                            </caption>
                            <thead>
                                <tr>
                                    <th
                                        scope="col"
                                        className="sticky top-0 left-0 z-30 min-w-56 border-r border-b bg-muted px-4 py-3 text-left text-xs font-semibold tracking-wide text-muted-foreground uppercase sm:min-w-72"
                                    >
                                        Permission
                                    </th>
                                    {roles.map((role) => {
                                        const Icon =
                                            roleIcons[role.name] ?? UserRound;
                                        const dirty =
                                            changedRoles.includes(role);
                                        return (
                                            <th
                                                key={role.name}
                                                scope="col"
                                                className="sticky top-0 z-20 min-w-32 border-b bg-muted px-3 py-3 text-center font-normal"
                                            >
                                                <span className="flex flex-col items-center gap-1.5">
                                                    <span
                                                        className="relative flex size-8 items-center justify-center rounded-lg bg-card text-primary shadow-card"
                                                        aria-hidden="true"
                                                    >
                                                        <Icon className="size-4" />
                                                        {dirty && (
                                                            <span className="absolute -top-1 -right-1 size-2.5 rounded-full bg-warning ring-2 ring-muted" />
                                                        )}
                                                    </span>
                                                    <span className="text-sm leading-tight font-semibold text-foreground">
                                                        {role.label}
                                                    </span>
                                                    <span className="text-xs text-muted-foreground tabular-nums">
                                                        {
                                                            (
                                                                draft[
                                                                    role.name
                                                                ] ?? []
                                                            ).length
                                                        }{' '}
                                                        droits
                                                        {dirty && (
                                                            <span className="sr-only">
                                                                {' '}
                                                                (modifié)
                                                            </span>
                                                        )}
                                                    </span>
                                                </span>
                                            </th>
                                        );
                                    })}
                                </tr>
                            </thead>
                            <tbody>
                                {groups.length === 0 ? (
                                    <tr>
                                        <td colSpan={roles.length + 1}>
                                            <EmptyState
                                                compact
                                                illustration="search"
                                                title="Aucune permission ne correspond à la recherche."
                                                action={
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={() =>
                                                            setSearch('')
                                                        }
                                                    >
                                                        Effacer la recherche
                                                    </Button>
                                                }
                                            />
                                        </td>
                                    </tr>
                                ) : (
                                    groups.map((group) => (
                                        <GroupedRows
                                            key={group}
                                            group={group}
                                            permissions={filtered.filter(
                                                (item) => item.group === group,
                                            )}
                                            roles={roles}
                                            draft={draft}
                                            busy={busy}
                                            reason={reason}
                                            onToggle={(role, name, checked) =>
                                                setDraft((current) => ({
                                                    ...current,
                                                    [role]: checked
                                                        ? [
                                                              ...(current[
                                                                  role
                                                              ] ?? []),
                                                              name,
                                                          ]
                                                        : (
                                                              current[role] ??
                                                              []
                                                          ).filter(
                                                              (item) =>
                                                                  item !== name,
                                                          ),
                                                }))
                                            }
                                        />
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
                <div
                    className={cn(
                        'sticky bottom-4 z-30 flex flex-col gap-3 rounded-xl border p-4 shadow-elevated transition-colors sm:flex-row sm:items-center sm:justify-between',
                        changes > 0
                            ? 'border-warning/40 bg-card'
                            : 'bg-card/95 backdrop-blur',
                    )}
                >
                    <p
                        className="flex items-center gap-2 text-sm font-medium text-foreground"
                        aria-live="polite"
                    >
                        {changes > 0 ? (
                            <span
                                className="size-2.5 shrink-0 rounded-full bg-warning"
                                aria-hidden="true"
                            />
                        ) : (
                            <CheckCircle2
                                className="size-4 shrink-0 text-success"
                                aria-hidden="true"
                            />
                        )}
                        {changes} modification{changes > 1 ? 's' : ''} non
                        enregistrée{changes > 1 ? 's' : ''}
                    </p>
                    <div className="flex flex-wrap gap-2">
                        <Button
                            variant="outline"
                            disabled={!changes || busy}
                            onClick={reset}
                        >
                            Annuler les modifications
                        </Button>
                        {changedRoles.map((role) => (
                            <Button
                                key={role.name}
                                disabled={busy}
                                onClick={() => {
                                    setError('');
                                    setSelected(role);
                                }}
                            >
                                Enregistrer : {role.label}
                            </Button>
                        ))}
                    </div>
                </div>
            </PageBody>
            <Dialog
                open={selected !== null}
                onOpenChange={(open) => {
                    if (!open && !busy) setSelected(null);
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            Modifier les droits : {selected?.label}
                        </DialogTitle>
                        <DialogDescription>
                            Ces droits s’appliquent à tous les comptes de ce
                            rôle. Les contrôles de portail et de périmètre
                            restent obligatoires.
                        </DialogDescription>
                    </DialogHeader>
                    {diff && (
                        <div className="max-h-64 space-y-4 overflow-y-auto">
                            {(
                                [
                                    ['Accorder', diff.granted, 'grant'],
                                    ['Retirer', diff.revoked, 'revoke'],
                                ] as const
                            )
                                .filter(([, names]) => names.length > 0)
                                .map(([title, names, kind]) => (
                                    <div key={title} className="space-y-2">
                                        <p className="text-sm font-semibold text-foreground">
                                            {title} ({names.length})
                                        </p>
                                        <ul className="space-y-1.5">
                                            {names.map((name) => (
                                                <li
                                                    key={name}
                                                    className={cn(
                                                        'flex items-center gap-2 rounded-md px-2.5 py-1.5 text-sm',
                                                        kind === 'grant'
                                                            ? 'bg-success-soft text-success-foreground'
                                                            : 'bg-danger-soft text-danger-foreground',
                                                    )}
                                                >
                                                    {kind === 'grant' ? (
                                                        <Plus
                                                            className="size-4 shrink-0"
                                                            aria-hidden="true"
                                                        />
                                                    ) : (
                                                        <Minus
                                                            className="size-4 shrink-0"
                                                            aria-hidden="true"
                                                        />
                                                    )}
                                                    {label(name)}
                                                </li>
                                            ))}
                                        </ul>
                                    </div>
                                ))}
                        </div>
                    )}
                    <InputError message={error} />
                    <DialogFooter>
                        <Button
                            variant="outline"
                            disabled={busy}
                            onClick={() => setSelected(null)}
                        >
                            Annuler
                        </Button>
                        <Button disabled={busy} onClick={save}>
                            {busy
                                ? 'Enregistrement…'
                                : 'Confirmer les modifications'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
function GroupedRows({
    group,
    permissions,
    roles,
    draft,
    busy,
    reason,
    onToggle,
}: {
    group: string;
    permissions: Permission[];
    roles: Role[];
    draft: Record<string, string[]>;
    busy: boolean;
    reason: (role: Role, name: string) => string | null;
    onToggle: (role: string, name: string, checked: boolean) => void;
}) {
    return (
        <>
            <tr>
                <th
                    scope="colgroup"
                    colSpan={roles.length + 1}
                    className="border-b bg-secondary px-0 py-2 text-left"
                >
                    <span className="sticky left-0 inline-flex items-center gap-2 px-4 text-xs font-bold tracking-wide text-secondary-foreground uppercase">
                        <span
                            className="size-1.5 rounded-full bg-primary"
                            aria-hidden="true"
                        />
                        {group}
                        <span className="font-medium text-muted-foreground normal-case">
                            · {permissions.length}
                        </span>
                    </span>
                </th>
            </tr>
            {permissions.map((permission) => (
                <tr key={permission.name} className="group">
                    <th
                        scope="row"
                        className="sticky left-0 z-10 border-r border-b bg-card px-4 py-3 text-left font-normal transition-colors group-hover:bg-muted"
                    >
                        <p className="font-medium text-foreground">
                            {permission.label}
                        </p>
                        <p className="text-xs text-muted-foreground">
                            {permission.description}
                        </p>
                    </th>
                    {roles.map((role) => {
                        const locked = reason(role, permission.name);
                        const checked = (draft[role.name] ?? []).includes(
                            permission.name,
                        );
                        const changed =
                            checked !==
                            role.permissions.includes(permission.name);
                        return (
                            <td
                                key={role.name}
                                className={cn(
                                    'border-b px-3 py-3 text-center transition-colors',
                                    changed
                                        ? 'bg-warning-soft'
                                        : 'group-hover:bg-muted/50',
                                )}
                            >
                                <span
                                    title={locked ?? permission.label}
                                    className="inline-flex"
                                >
                                    <Checkbox
                                        aria-label={`${role.label} : ${permission.label}`}
                                        className="size-5 bg-card"
                                        checked={checked}
                                        disabled={busy || locked !== null}
                                        onCheckedChange={(value) =>
                                            onToggle(
                                                role.name,
                                                permission.name,
                                                value === true,
                                            )
                                        }
                                    />
                                </span>
                                {locked && (
                                    <p className="mt-1 flex items-center justify-center gap-1 text-xs whitespace-nowrap text-muted-foreground">
                                        <Lock
                                            className="size-3"
                                            aria-hidden="true"
                                        />
                                        {role.name === 'student'
                                            ? 'Hors portail'
                                            : 'Droit protégé'}
                                    </p>
                                )}
                            </td>
                        );
                    })}
                </tr>
            ))}
        </>
    );
}
Permissions.layout = {
    breadcrumbs: [{ title: 'Permissions', href: '/permissions' }],
};
