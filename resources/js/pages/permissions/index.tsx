import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
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
    return (
        <div className="space-y-6 p-4 md:p-6">
            <Head title="Permissions" />
            <Heading
                title="Administration / Permissions"
                description="Les cinq rôles sont fixes. Les permissions accordées sont enregistrées en base et conservées lors des déploiements."
            />
            <Input
                aria-label="Rechercher une permission"
                placeholder="Rechercher une permission ou un module…"
                value={search}
                onChange={(event) => setSearch(event.target.value)}
            />
            <div className="overflow-x-auto rounded-lg border bg-card">
                <table className="w-full text-sm">
                    <thead className="border-b bg-muted/40">
                        <tr>
                            <th className="min-w-64 p-4 text-left">
                                Permission
                            </th>
                            {roles.map((role) => (
                                <th
                                    key={role.name}
                                    className="min-w-32 p-3 text-center"
                                >
                                    <p>{role.label}</p>
                                    <p className="font-normal text-muted-foreground">
                                        {(draft[role.name] ?? []).length} droits
                                    </p>
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {groups.length === 0 ? (
                            <tr>
                                <td className="p-4" colSpan={roles.length + 1}>
                                    Aucune permission ne correspond à la
                                    recherche.
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
                                                      ...(current[role] ?? []),
                                                      name,
                                                  ]
                                                : (current[role] ?? []).filter(
                                                      (item) => item !== name,
                                                  ),
                                        }))
                                    }
                                />
                            ))
                        )}
                    </tbody>
                </table>
            </div>
            <div className="sticky bottom-0 flex flex-wrap items-center justify-between gap-3 rounded-lg border bg-card p-4 shadow-sm">
                <p>
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
                    {roles
                        .filter((role) => {
                            const diff = differences(role);
                            return (
                                diff.granted.length + diff.revoked.length > 0
                            );
                        })
                        .map((role) => (
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
                        <div className="max-h-64 space-y-3 overflow-y-auto">
                            {[
                                ['Accorder', diff.granted],
                                ['Retirer', diff.revoked],
                            ].map(([label, names]) => (
                                <div key={String(label)}>
                                    <p className="font-medium">
                                        {String(label)}
                                    </p>
                                    <ul className="list-inside list-disc">
                                        {(names as string[]).map((name) => (
                                            <li key={name}>
                                                {catalogue.find(
                                                    (item) =>
                                                        item.name === name,
                                                )?.label ?? name}
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
        </div>
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
            <tr className="border-y bg-muted/40">
                <th
                    colSpan={roles.length + 1}
                    className="px-4 py-3 text-left font-semibold"
                >
                    {group}
                </th>
            </tr>
            {permissions.map((permission) => (
                <tr key={permission.name} className="border-b last:border-b-0">
                    <td className="p-4">
                        <p className="font-medium">{permission.label}</p>
                        <p className="text-xs text-muted-foreground">
                            {permission.description}
                        </p>
                    </td>
                    {roles.map((role) => {
                        const locked = reason(role, permission.name);
                        return (
                            <td key={role.name} className="p-3 text-center">
                                <span title={locked ?? permission.label}>
                                    <Checkbox
                                        aria-label={`${role.label} : ${permission.label}`}
                                        checked={(
                                            draft[role.name] ?? []
                                        ).includes(permission.name)}
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
                                    <p className="mt-1 text-xs text-muted-foreground">
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
