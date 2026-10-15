import { Head, Link, router } from '@inertiajs/react';
import {
    Activity as ActivityIcon,
    ArrowLeft,
    BadgeCheck,
    Building2,
    CalendarDays,
    Info,
    KeyRound,
    Lock,
    Mail,
    MailCheck,
    Pencil,
    Power,
    RotateCcw,
    Search,
    ShieldAlert,
    Smartphone,
    Undo2,
    UserRoundCheck,
    UserRoundX,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import type { ReactNode } from 'react';
import { EmptyState } from '@/components/empty-state';
import { PageBody } from '@/components/page-header';
import { formatDate } from '@/components/scholarships/shared';
import { ToneBadge } from '@/components/tone-badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import {
    AccountStatusBadge,
    RoleBadge,
    UserAvatar,
} from '@/components/users/shared';
import type { UserSummary } from '@/components/users/shared';
import { cn } from '@/lib/utils';

type CatalogueRow = {
    name: string;
    label: string;
    description: string;
    group: string;
};
type Rights = {
    role: string[];
    granted: string[];
    denied: string[];
    version: string;
    protected: string[];
};
type AuditEntry = {
    id: number;
    uuid: string;
    at: string | null;
    actor: string;
    module_label: string;
    event_label: string;
};
type Props = {
    user: UserSummary & {
        two_factor_enabled: boolean;
        email_verified_at: string | null;
        created_at: string | null;
    };
    rights: Rights;
    catalogue: CatalogueRow[];
    activity: AuditEntry[] | null;
    can: { update: boolean; managePermissions: boolean; isSelf: boolean };
    invitationMinutes: number;
};
type View = 'all' | 'effective' | 'adjusted';

const normalize = (value: string) =>
    value
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase();

export default function UserShow({
    user,
    rights,
    catalogue,
    activity,
    can,
    invitationMinutes,
}: Props) {
    const [granted, setGranted] = useState<string[]>(rights.granted);
    const [denied, setDenied] = useState<string[]>(rights.denied);
    const [query, setQuery] = useState('');
    const [view, setView] = useState<View>('all');
    const [confirmRights, setConfirmRights] = useState(false);
    const [confirmStatus, setConfirmStatus] = useState(false);
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const inRole = (name: string) => rights.role.includes(name);
    const effective = (name: string) =>
        inRole(name) ? !denied.includes(name) : granted.includes(name);
    const adjusted = (name: string) =>
        granted.includes(name) || denied.includes(name);
    const locked = (name: string) => rights.protected.includes(name);
    const editable = can.managePermissions;

    const toggle = (name: string) => {
        if (!editable || locked(name)) return;
        if (inRole(name))
            setDenied((list) =>
                list.includes(name)
                    ? list.filter((item) => item !== name)
                    : [...list, name],
            );
        else
            setGranted((list) =>
                list.includes(name)
                    ? list.filter((item) => item !== name)
                    : [...list, name],
            );
    };

    const diff = useMemo(
        () => ({
            added: granted.filter((name) => !rights.granted.includes(name)),
            unadded: rights.granted.filter((name) => !granted.includes(name)),
            withdrawn: denied.filter((name) => !rights.denied.includes(name)),
            restored: rights.denied.filter((name) => !denied.includes(name)),
        }),
        [granted, denied, rights],
    );
    const changes =
        diff.added.length +
        diff.unadded.length +
        diff.withdrawn.length +
        diff.restored.length;
    const labelOf = (name: string) =>
        catalogue.find((row) => row.name === name)?.label ?? name;
    const effectiveCount = catalogue.filter((row) =>
        effective(row.name),
    ).length;

    const groups = (() => {
        const term = normalize(query.trim());
        const map = new Map<string, CatalogueRow[]>();
        for (const row of catalogue) {
            if (
                term &&
                !normalize(`${row.label} ${row.name} ${row.group}`).includes(
                    term,
                )
            )
                continue;
            if (view === 'effective' && !effective(row.name)) continue;
            if (view === 'adjusted' && !adjusted(row.name)) continue;
            map.set(row.group, [...(map.get(row.group) ?? []), row]);
        }
        return [...map.entries()];
    })();

    const saveRights = () => {
        setProcessing(true);
        setError(null);
        router.put(
            `/users/${user.uuid}/permissions`,
            { granted, denied, version: rights.version },
            {
                preserveScroll: true,
                onSuccess: () => setConfirmRights(false),
                onError: (errors) =>
                    setError(
                        Object.values(errors)[0] ??
                            'Enregistrement impossible.',
                    ),
                onFinish: () => setProcessing(false),
            },
        );
    };
    const setStatus = () => {
        setProcessing(true);
        router.patch(
            `/users/${user.uuid}/status`,
            { active: !user.is_active },
            {
                preserveScroll: true,
                onSuccess: () => setConfirmStatus(false),
                onError: (errors) =>
                    setError(Object.values(errors)[0] ?? 'Action impossible.'),
                onFinish: () => setProcessing(false),
            },
        );
    };

    return (
        <>
            <Head title={user.name} />
            <PageBody>
                <header className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div className="flex min-w-0 items-center gap-4">
                        <UserAvatar user={user} size="lg" />
                        <div className="min-w-0 space-y-1.5">
                            <h1 className="truncate text-2xl leading-tight font-bold tracking-tight text-foreground lg:text-[1.65rem]">
                                {user.name}
                            </h1>
                            <p className="truncate text-sm text-muted-foreground">
                                {user.email}
                            </p>
                            <div className="flex flex-wrap gap-1.5">
                                <RoleBadge
                                    role={user.role}
                                    label={user.role_label}
                                />
                                <AccountStatusBadge active={user.is_active} />
                                {user.two_factor_enabled && (
                                    <ToneBadge tone="info">
                                        Double authentification
                                    </ToneBadge>
                                )}
                                {can.isSelf && (
                                    <ToneBadge tone="primary">
                                        Votre compte
                                    </ToneBadge>
                                )}
                            </div>
                        </div>
                    </div>
                    <div className="flex shrink-0 flex-wrap gap-2">
                        <Button variant="ghost" asChild>
                            <Link href="/users">
                                <ArrowLeft aria-hidden="true" />
                                Tous les comptes
                            </Link>
                        </Button>
                        {can.update && (
                            <Button asChild>
                                <Link href={`/users/${user.uuid}/edit`}>
                                    <Pencil aria-hidden="true" />
                                    Modifier
                                </Link>
                            </Button>
                        )}
                    </div>
                </header>

                {can.isSelf && (
                    <Alert tone="info" icon={Info}>
                        C’est votre compte : votre rôle, votre statut et vos
                        droits ne peuvent être modifiés que par un autre
                        administrateur.
                    </Alert>
                )}
                {!user.is_active && (
                    <Alert tone="danger" icon={UserRoundX}>
                        Ce compte est suspendu : il ne peut plus se connecter et
                        n’a plus aucun accès. Ses données et son historique sont
                        conservés.
                    </Alert>
                )}

                <div className="grid gap-6 lg:grid-cols-3">
                    <section className="surface min-w-0 lg:col-span-2">
                        <div className="flex flex-col gap-4 border-b p-5 sm:p-6">
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div className="flex items-start gap-3">
                                    <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-accent text-primary">
                                        <KeyRound
                                            className="size-[18px]"
                                            aria-hidden="true"
                                        />
                                    </span>
                                    <div>
                                        <h2 className="text-base font-bold text-foreground">
                                            Droits du compte
                                        </h2>
                                        <p className="text-sm text-muted-foreground">
                                            Droits par défaut du rôle{' '}
                                            {user.role_label ?? ''}, ajustables
                                            pour ce seul compte.
                                        </p>
                                    </div>
                                </div>
                                {editable &&
                                    (granted.length > 0 ||
                                        denied.length > 0) && (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() => {
                                                setGranted([]);
                                                setDenied([]);
                                            }}
                                        >
                                            <RotateCcw aria-hidden="true" />
                                            Revenir aux droits du rôle
                                        </Button>
                                    )}
                            </div>
                            <div className="flex flex-wrap gap-2 text-xs font-semibold">
                                <span className="rounded-full bg-accent px-3 py-1 text-accent-foreground">
                                    {effectiveCount} droit(s) effectif(s)
                                </span>
                                <span className="rounded-full bg-secondary px-3 py-1 text-secondary-foreground">
                                    {rights.role.length} par le rôle
                                </span>
                                {granted.length > 0 && (
                                    <span className="rounded-full bg-success-soft px-3 py-1 text-success-foreground">
                                        +{granted.length} ajouté(s)
                                    </span>
                                )}
                                {denied.length > 0 && (
                                    <span className="rounded-full bg-danger-soft px-3 py-1 text-danger-foreground">
                                        −{denied.length} retiré(s)
                                    </span>
                                )}
                            </div>
                            {!editable && !can.isSelf && (
                                <Alert tone="info" icon={Lock}>
                                    Lecture seule : ajuster les droits d’un
                                    compte exige aussi la permission de gérer
                                    les permissions.
                                </Alert>
                            )}
                            <div className="flex flex-col gap-2 sm:flex-row">
                                <div className="relative flex-1">
                                    <Search
                                        className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                        aria-hidden="true"
                                    />
                                    <Input
                                        aria-label="Filtrer les droits"
                                        placeholder="Rechercher un droit ou un module…"
                                        className="h-10 pl-9"
                                        value={query}
                                        onChange={(e) =>
                                            setQuery(e.target.value)
                                        }
                                    />
                                </div>
                                <div
                                    className="flex gap-1 rounded-full border bg-muted/60 p-1"
                                    role="group"
                                    aria-label="Afficher"
                                >
                                    {(
                                        [
                                            ['all', 'Tous'],
                                            ['effective', 'Effectifs'],
                                            ['adjusted', 'Ajustés'],
                                        ] as [View, string][]
                                    ).map(([value, label]) => (
                                        <button
                                            key={value}
                                            type="button"
                                            aria-pressed={view === value}
                                            onClick={() => setView(value)}
                                            className={cn(
                                                'h-8 rounded-full px-3 text-sm font-medium transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                                view === value
                                                    ? 'bg-card text-foreground shadow-card'
                                                    : 'text-muted-foreground hover:text-foreground',
                                            )}
                                        >
                                            {label}
                                        </button>
                                    ))}
                                </div>
                            </div>
                        </div>

                        {groups.length === 0 ? (
                            <EmptyState
                                compact
                                illustration="search"
                                title="Aucun droit à afficher"
                                description={
                                    view === 'adjusted'
                                        ? 'Ce compte utilise exactement les droits de son rôle.'
                                        : 'Modifiez la recherche.'
                                }
                            />
                        ) : (
                            <div className="divide-y">
                                {groups.map(([group, rows]) => (
                                    <div key={group}>
                                        <div className="flex items-center justify-between bg-muted/50 px-5 py-2 sm:px-6">
                                            <h3 className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                                {group}
                                            </h3>
                                            <span className="text-xs font-semibold text-muted-foreground tabular-nums">
                                                {
                                                    rows.filter((row) =>
                                                        effective(row.name),
                                                    ).length
                                                }
                                                /{rows.length}
                                            </span>
                                        </div>
                                        <ul>
                                            {rows.map((row) => (
                                                <RightRow
                                                    key={row.name}
                                                    row={row}
                                                    on={effective(row.name)}
                                                    state={
                                                        locked(row.name)
                                                            ? 'locked'
                                                            : granted.includes(
                                                                    row.name,
                                                                )
                                                              ? 'added'
                                                              : denied.includes(
                                                                      row.name,
                                                                  )
                                                                ? 'withdrawn'
                                                                : inRole(
                                                                        row.name,
                                                                    )
                                                                  ? 'role'
                                                                  : 'off'
                                                    }
                                                    editable={editable}
                                                    onToggle={() =>
                                                        toggle(row.name)
                                                    }
                                                />
                                            ))}
                                        </ul>
                                    </div>
                                ))}
                            </div>
                        )}

                        {editable && changes > 0 && (
                            <div className="sticky bottom-0 z-10 rounded-b-xl border-t bg-card/90 px-5 py-3 shadow-elevated backdrop-blur-md sm:px-6">
                                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                    <p className="flex items-center gap-2 text-sm font-medium text-warning-foreground">
                                        <span
                                            className="size-2 rounded-full bg-warning"
                                            aria-hidden="true"
                                        />
                                        {changes} modification(s) non
                                        enregistrée(s)
                                    </p>
                                    <div className="flex gap-2">
                                        <Button
                                            variant="outline"
                                            onClick={() => {
                                                setGranted(rights.granted);
                                                setDenied(rights.denied);
                                            }}
                                        >
                                            <Undo2 aria-hidden="true" />
                                            Annuler
                                        </Button>
                                        <Button
                                            onClick={() =>
                                                setConfirmRights(true)
                                            }
                                        >
                                            Enregistrer les droits
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        )}
                    </section>

                    <aside className="space-y-6">
                        <section className="surface p-5">
                            <h2 className="mb-4 font-heading text-base font-bold text-foreground">
                                Compte
                            </h2>
                            <dl className="space-y-3 text-sm">
                                <Detail icon={Mail} label="Adresse email">
                                    {user.email}
                                </Detail>
                                <Detail icon={Building2} label="Périmètre">
                                    {user.establishment
                                        ? `${user.establishment.code} · ${user.establishment.name}`
                                        : 'Accès global'}
                                </Detail>
                                <Detail icon={MailCheck} label="Email vérifié">
                                    {user.email_verified_at
                                        ? formatDate(user.email_verified_at)
                                        : 'Non vérifié'}
                                </Detail>
                                <Detail
                                    icon={Smartphone}
                                    label="Double authentification"
                                >
                                    {user.two_factor_enabled
                                        ? 'Activée'
                                        : 'Non activée'}
                                </Detail>
                                <Detail icon={CalendarDays} label="Créé le">
                                    {formatDate(user.created_at)}
                                </Detail>
                            </dl>
                        </section>

                        {can.update && (
                            <section className="surface space-y-3 p-5">
                                <h2 className="font-heading text-base font-bold text-foreground">
                                    Accès
                                </h2>
                                {user.is_active && (
                                    <div className="rounded-lg border bg-muted/40 p-3">
                                        <p className="text-sm font-semibold text-foreground">
                                            Invitation
                                        </p>
                                        <p className="mt-0.5 text-xs text-muted-foreground">
                                            Envoie un lien pour (re)définir le
                                            mot de passe, valable{' '}
                                            {invitationMinutes} minutes.
                                        </p>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            className="mt-3 w-full"
                                            onClick={() =>
                                                router.post(
                                                    `/users/${user.uuid}/invitation`,
                                                    {},
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            <Mail aria-hidden="true" />
                                            Renvoyer l’invitation
                                        </Button>
                                    </div>
                                )}
                                <div
                                    className={cn(
                                        'rounded-lg border p-3',
                                        user.is_active
                                            ? 'border-danger/30 bg-danger-soft/50'
                                            : 'border-success/30 bg-success-soft/50',
                                    )}
                                >
                                    <p className="text-sm font-semibold text-foreground">
                                        {user.is_active
                                            ? 'Suspendre le compte'
                                            : 'Réactiver le compte'}
                                    </p>
                                    <p className="mt-0.5 text-xs text-muted-foreground">
                                        {user.is_active
                                            ? 'Bloque la connexion et ferme les sessions ouvertes. Réversible.'
                                            : 'Rend l’accès au portail avec le rôle et les droits actuels.'}
                                    </p>
                                    <Button
                                        variant={
                                            user.is_active
                                                ? 'destructive'
                                                : 'default'
                                        }
                                        size="sm"
                                        className="mt-3 w-full"
                                        onClick={() => {
                                            setError(null);
                                            setConfirmStatus(true);
                                        }}
                                    >
                                        {user.is_active ? (
                                            <Power aria-hidden="true" />
                                        ) : (
                                            <UserRoundCheck aria-hidden="true" />
                                        )}
                                        {user.is_active
                                            ? 'Suspendre'
                                            : 'Réactiver'}
                                    </Button>
                                </div>
                            </section>
                        )}

                        {activity && (
                            <section className="surface p-5">
                                <div className="mb-4 flex items-center justify-between gap-2">
                                    <h2 className="flex items-center gap-2 font-heading text-base font-bold text-foreground">
                                        <ActivityIcon
                                            className="size-4 text-primary"
                                            aria-hidden="true"
                                        />
                                        Activité récente
                                    </h2>
                                </div>
                                {activity.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        Aucune activité enregistrée.
                                    </p>
                                ) : (
                                    <ol className="relative space-y-1 text-sm before:absolute before:top-2 before:bottom-2 before:left-[7px] before:w-px before:bg-border">
                                        {activity.map((item) => (
                                            <li
                                                key={item.id}
                                                className="relative py-1.5 pl-7"
                                            >
                                                <span
                                                    className="absolute top-3 left-0 size-[15px] rounded-full border-[3px] border-card bg-primary ring-1 ring-primary/30"
                                                    aria-hidden="true"
                                                />
                                                <Link
                                                    href={`/audits/${item.uuid}`}
                                                    className="font-semibold text-foreground hover:text-primary"
                                                >
                                                    {item.event_label}
                                                </Link>
                                                <span className="block text-xs text-muted-foreground">
                                                    {item.module_label} ·{' '}
                                                    {item.actor} ·{' '}
                                                    {formatDate(item.at, true)}
                                                </span>
                                            </li>
                                        ))}
                                    </ol>
                                )}
                            </section>
                        )}
                    </aside>
                </div>
            </PageBody>

            <Dialog
                open={confirmRights}
                onOpenChange={(open) => !processing && setConfirmRights(open)}
            >
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>
                            Enregistrer les droits de {user.name} ?
                        </DialogTitle>
                        <DialogDescription>
                            Les changements s’appliquent immédiatement, dans le
                            périmètre du rôle du compte.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="max-h-[50vh] space-y-3 overflow-y-auto text-sm">
                        <ChangeList
                            title="Droits ajoutés"
                            tone="success"
                            items={diff.added.map(labelOf)}
                        />
                        <ChangeList
                            title="Ajouts annulés"
                            tone="neutral"
                            items={diff.unadded.map(labelOf)}
                        />
                        <ChangeList
                            title="Droits du rôle retirés"
                            tone="danger"
                            items={diff.withdrawn.map(labelOf)}
                        />
                        <ChangeList
                            title="Droits du rôle rétablis"
                            tone="info"
                            items={diff.restored.map(labelOf)}
                        />
                    </div>
                    {error && (
                        <p
                            role="alert"
                            className="rounded-lg bg-danger-soft p-3 text-sm text-danger-foreground"
                        >
                            {error}
                        </p>
                    )}
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setConfirmRights(false)}
                            disabled={processing}
                        >
                            Annuler
                        </Button>
                        <Button onClick={saveRights} disabled={processing}>
                            {processing && <Spinner />}
                            Confirmer
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog
                open={confirmStatus}
                onOpenChange={(open) => !processing && setConfirmStatus(open)}
            >
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <span
                            className={cn(
                                'mb-2 flex size-11 items-center justify-center rounded-full',
                                user.is_active
                                    ? 'bg-danger-soft text-danger'
                                    : 'bg-success-soft text-success',
                            )}
                            aria-hidden="true"
                        >
                            {user.is_active ? (
                                <ShieldAlert className="size-5" />
                            ) : (
                                <BadgeCheck className="size-5" />
                            )}
                        </span>
                        <DialogTitle>
                            {user.is_active
                                ? `Suspendre ${user.name} ?`
                                : `Réactiver ${user.name} ?`}
                        </DialogTitle>
                        <DialogDescription>
                            {user.is_active
                                ? 'La personne sera déconnectée immédiatement et ne pourra plus se connecter. Vous pourrez réactiver le compte à tout moment.'
                                : 'La personne pourra de nouveau se connecter avec son mot de passe actuel.'}
                        </DialogDescription>
                    </DialogHeader>
                    {error && (
                        <p
                            role="alert"
                            className="rounded-lg bg-danger-soft p-3 text-sm text-danger-foreground"
                        >
                            {error}
                        </p>
                    )}
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setConfirmStatus(false)}
                            disabled={processing}
                        >
                            Annuler
                        </Button>
                        <Button
                            variant={user.is_active ? 'destructive' : 'default'}
                            onClick={setStatus}
                            disabled={processing}
                        >
                            {processing && <Spinner />}
                            {user.is_active ? 'Suspendre' : 'Réactiver'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

type RowState = 'role' | 'added' | 'withdrawn' | 'off' | 'locked';

const stateChip: Record<RowState, ReactNode> = {
    role: (
        <span className="rounded-full bg-secondary px-2 py-0.5 text-[11px] font-semibold text-secondary-foreground">
            Rôle
        </span>
    ),
    added: (
        <span className="rounded-full bg-success-soft px-2 py-0.5 text-[11px] font-semibold text-success-foreground">
            Ajouté
        </span>
    ),
    withdrawn: (
        <span className="rounded-full bg-danger-soft px-2 py-0.5 text-[11px] font-semibold text-danger-foreground">
            Retiré
        </span>
    ),
    off: null,
    locked: (
        <span className="inline-flex items-center gap-1 rounded-full bg-secondary px-2 py-0.5 text-[11px] font-semibold text-muted-foreground">
            <Lock className="size-3" aria-hidden="true" />
            Protégé
        </span>
    ),
};

function RightRow({
    row,
    on,
    state,
    editable,
    onToggle,
}: {
    row: CatalogueRow;
    on: boolean;
    state: RowState;
    editable: boolean;
    onToggle: () => void;
}) {
    const disabled = !editable || state === 'locked';
    return (
        <li
            className={cn(
                'flex items-center gap-4 px-5 py-3 transition-colors sm:px-6',
                state === 'added' && 'bg-success-soft/40',
                state === 'withdrawn' && 'bg-danger-soft/40',
            )}
        >
            <div className="min-w-0 flex-1">
                <p className="flex flex-wrap items-center gap-2 text-sm font-medium text-foreground">
                    <span
                        className={cn(
                            state === 'withdrawn' &&
                                'text-muted-foreground line-through',
                        )}
                    >
                        {row.label}
                    </span>
                    {stateChip[state]}
                </p>
                <p className="mt-0.5 font-mono text-[11px] text-muted-foreground">
                    {row.name}
                </p>
            </div>
            <button
                type="button"
                role="switch"
                aria-checked={on}
                aria-label={`${row.label} : ${on ? 'accordé' : 'non accordé'}`}
                disabled={disabled}
                onClick={onToggle}
                className={cn(
                    'relative inline-flex h-6 w-11 shrink-0 items-center rounded-full border-2 border-transparent transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-card focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-60',
                    on
                        ? state === 'added'
                            ? 'bg-success'
                            : 'bg-primary'
                        : state === 'withdrawn'
                          ? 'bg-danger/60'
                          : 'bg-input',
                )}
            >
                <span
                    className={cn(
                        'pointer-events-none block size-5 rounded-full bg-white shadow-sm transition-transform',
                        on ? 'translate-x-5' : 'translate-x-0',
                    )}
                />
            </button>
        </li>
    );
}

function ChangeList({
    title,
    tone,
    items,
}: {
    title: string;
    tone: 'success' | 'danger' | 'info' | 'neutral';
    items: string[];
}) {
    if (items.length === 0) return null;
    return (
        <div>
            <p className="mb-1.5 flex items-center gap-2 font-semibold text-foreground">
                <ToneBadge tone={tone}>{items.length}</ToneBadge>
                {title}
            </p>
            <ul className="space-y-1 pl-1 text-muted-foreground">
                {items.map((item) => (
                    <li key={item}>· {item}</li>
                ))}
            </ul>
        </div>
    );
}

function Detail({
    icon: Icon,
    label,
    children,
}: {
    icon: typeof Mail;
    label: string;
    children: ReactNode;
}) {
    return (
        <div className="flex gap-3">
            <span
                className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-muted text-muted-foreground"
                aria-hidden="true"
            >
                <Icon className="size-4" />
            </span>
            <div className="min-w-0">
                <dt className="text-xs text-muted-foreground">{label}</dt>
                <dd className="truncate font-medium text-foreground">
                    {children}
                </dd>
            </div>
        </div>
    );
}

function Alert({
    tone,
    icon: Icon,
    children,
}: {
    tone: 'info' | 'danger';
    icon: typeof Info;
    children: ReactNode;
}) {
    return (
        <p
            role="status"
            className={cn(
                'flex items-start gap-2.5 rounded-lg border p-3 text-sm',
                tone === 'info'
                    ? 'border-info/30 bg-info-soft text-info-foreground'
                    : 'border-danger/30 bg-danger-soft text-danger-foreground',
            )}
        >
            <Icon className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
            <span>{children}</span>
        </p>
    );
}

UserShow.layout = {
    breadcrumbs: [{ title: 'Utilisateurs', href: '/users' }],
};
