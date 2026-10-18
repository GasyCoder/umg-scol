import { Head, useForm } from '@inertiajs/react';
import {
    AlertTriangle,
    Building2,
    Check,
    KeyRound,
    Mail,
    Save,
    Search,
    ShieldCheck,
    UserPlus,
    UserRound,
    UserRoundPen,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import {
    ErrorSummary,
    FormActions,
    FormField,
    FormSection,
} from '@/components/forms/form-kit';
import { PageBody, PageHeader } from '@/components/page-header';
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
import { metaFor } from '@/components/users/shared';
import type { RoleOption, UserSummary } from '@/components/users/shared';
import { cn } from '@/lib/utils';

type Establishment = { id: number; code: string; name: string };

const labels = {
    name: 'Nom complet',
    email: 'Adresse email',
    role: 'Rôle',
    establishment_id: 'Établissement',
};

export default function UserForm({
    user,
    roles,
    establishments,
    invitationMinutes,
}: {
    user: UserSummary | null;
    roles: RoleOption[];
    establishments: Establishment[];
    invitationMinutes: number;
}) {
    const editing = user !== null;
    const form = useForm({
        name: user?.name ?? '',
        email: user?.email ?? '',
        role: user?.role ?? '',
        establishment_id: user?.establishment?.id
            ? String(user.establishment.id)
            : '',
    });
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            establishment_id:
                data.role === 'establishment' && data.establishment_id
                    ? Number(data.establishment_id)
                    : null,
        }));
        if (editing) form.put(`/users/${user.uuid}`);
        else form.post('/users');
    };
    const roleChanged = editing && user.role !== form.data.role;

    // Establishment of an establishment account, chosen in a dialog with a search.
    const [pickerOpen, setPickerOpen] = useState(false);
    const [pending, setPending] = useState('');
    const [query, setQuery] = useState('');
    const chosen =
        establishments.find(
            (item) => String(item.id) === form.data.establishment_id,
        ) ?? null;
    const needle = query.trim().toLowerCase();
    const visible = needle
        ? establishments.filter((item) =>
              `${item.code} ${item.name}`.toLowerCase().includes(needle),
          )
        : establishments;
    const openPicker = () => {
        setPending(form.data.establishment_id);
        setQuery('');
        setPickerOpen(true);
    };
    const confirmPicker = (value: string) => {
        if (!value) return;
        form.setData('establishment_id', value);
        form.clearErrors('establishment_id');
        setPickerOpen(false);
    };
    const cancel = editing ? `/users/${user.uuid}` : '/users';

    return (
        <>
            <Head
                title={editing ? `Modifier ${user.name}` : 'Nouveau compte'}
            />
            <PageBody>
                <PageHeader
                    icon={editing ? UserRoundPen : UserPlus}
                    title={editing ? `Modifier le compte` : 'Nouveau compte'}
                    description={
                        editing
                            ? `${user.name} · ${user.email}`
                            : 'Le compte reçoit les droits par défaut de son rôle ; vous pourrez les ajuster ensuite depuis sa fiche.'
                    }
                />
                <form
                    onSubmit={submit}
                    className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_20rem]"
                    noValidate
                >
                    <div className="min-w-0 space-y-6">
                        <ErrorSummary errors={form.errors} labels={labels} />

                        <FormSection
                            icon={UserRound}
                            title="Identité du compte"
                            description="Le nom affiché dans le portail et dans le journal d’audit."
                        >
                            <FormField
                                id="name"
                                label={labels.name}
                                required
                                error={form.errors.name}
                            >
                                <Input
                                    id="name"
                                    value={form.data.name}
                                    onChange={(e) =>
                                        form.setData('name', e.target.value)
                                    }
                                    maxLength={255}
                                    autoComplete="off"
                                    placeholder="Prénom NOM"
                                    aria-invalid={Boolean(form.errors.name)}
                                />
                            </FormField>
                            <FormField
                                id="email"
                                label={labels.email}
                                required
                                hint={
                                    editing
                                        ? undefined
                                        : 'L’invitation à définir le mot de passe est envoyée à cette adresse.'
                                }
                                error={form.errors.email}
                            >
                                <Input
                                    id="email"
                                    type="email"
                                    inputMode="email"
                                    value={form.data.email}
                                    onChange={(e) =>
                                        form.setData('email', e.target.value)
                                    }
                                    maxLength={255}
                                    autoComplete="off"
                                    placeholder="prenom.nom@exemple.mg"
                                    aria-invalid={Boolean(form.errors.email)}
                                />
                            </FormField>
                        </FormSection>

                        <FormSection
                            id="role"
                            icon={ShieldCheck}
                            title="Rôle"
                            description="Un seul rôle par compte. Il fixe le périmètre et les droits par défaut."
                            columns={1}
                        >
                            <div
                                role="radiogroup"
                                aria-label="Rôle du compte"
                                aria-invalid={Boolean(form.errors.role)}
                                className="grid gap-2 md:auto-cols-fr md:grid-flow-col"
                            >
                                {roles.map((role) => {
                                    const meta = metaFor(role.value);
                                    const Icon = meta.icon;
                                    const selected =
                                        form.data.role === role.value;
                                    return (
                                        <button
                                            key={role.value}
                                            type="button"
                                            role="radio"
                                            aria-checked={selected}
                                            onClick={() => {
                                                form.setData(
                                                    'role',
                                                    role.value,
                                                );
                                                // An establishment account needs its establishment: chosen in a dialog.
                                                if (
                                                    role.value ===
                                                    'establishment'
                                                )
                                                    openPicker();
                                            }}
                                            title={role.description}
                                            className={cn(
                                                'relative flex items-start gap-2.5 rounded-lg border bg-card p-3 text-left transition-all focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                                selected
                                                    ? 'border-primary bg-accent/50 ring-2 ring-primary/20'
                                                    : 'hover:border-primary/40 hover:shadow-card',
                                            )}
                                        >
                                            <span
                                                className={cn(
                                                    'flex size-8 shrink-0 items-center justify-center rounded-lg',
                                                    meta.tile,
                                                )}
                                                aria-hidden="true"
                                            >
                                                <Icon className="size-4" />
                                            </span>
                                            <span className="min-w-0 flex-1 pr-6">
                                                <span className="block truncate text-sm font-semibold text-foreground">
                                                    {role.label}
                                                </span>
                                                <span className="mt-0.5 line-clamp-2 text-xs text-muted-foreground">
                                                    {role.description}
                                                </span>
                                                <span className="mt-1.5 inline-flex items-center gap-1 text-[11px] font-medium text-muted-foreground">
                                                    <KeyRound
                                                        className="size-3"
                                                        aria-hidden="true"
                                                    />
                                                    {role.permissions_count}{' '}
                                                    droits par défaut
                                                </span>
                                                {role.value ===
                                                    'establishment' &&
                                                    selected && (
                                                        <span className="mt-1.5 flex items-center gap-1.5 text-xs">
                                                            <Building2
                                                                className="size-3.5 shrink-0 text-primary"
                                                                aria-hidden="true"
                                                            />
                                                            {chosen ? (
                                                                <span className="min-w-0 truncate font-semibold text-foreground">
                                                                    {
                                                                        chosen.code
                                                                    }{' '}
                                                                    ·{' '}
                                                                    {
                                                                        chosen.name
                                                                    }
                                                                </span>
                                                            ) : (
                                                                <span className="font-medium text-danger-foreground">
                                                                    Établissement
                                                                    à choisir
                                                                </span>
                                                            )}
                                                            <span className="shrink-0 font-medium text-primary underline-offset-2 hover:underline">
                                                                {chosen
                                                                    ? 'Changer'
                                                                    : 'Choisir'}
                                                            </span>
                                                        </span>
                                                    )}
                                            </span>
                                            <span
                                                className={cn(
                                                    'absolute top-2.5 right-2.5 flex size-4 items-center justify-center rounded-full border transition-colors',
                                                    selected
                                                        ? 'border-primary bg-primary text-primary-foreground'
                                                        : 'bg-card',
                                                )}
                                                aria-hidden="true"
                                            >
                                                {selected && (
                                                    <Check className="size-2.5" />
                                                )}
                                            </span>
                                        </button>
                                    );
                                })}
                            </div>
                            {form.errors.role && (
                                <p className="text-sm font-medium text-danger-foreground">
                                    {form.errors.role}
                                </p>
                            )}
                            {form.errors.establishment_id && (
                                <p className="text-sm font-medium text-danger-foreground">
                                    {form.errors.establishment_id}
                                </p>
                            )}
                            {roleChanged && (
                                <p
                                    role="status"
                                    className="flex items-start gap-2 rounded-lg border border-warning/30 bg-warning-soft p-3 text-sm text-warning-foreground"
                                >
                                    <AlertTriangle
                                        className="mt-0.5 size-4 shrink-0"
                                        aria-hidden="true"
                                    />
                                    Le compte recevra les droits par défaut du
                                    nouveau rôle. Les ajustements devenus sans
                                    objet (droit retiré absent du rôle, droit
                                    ajouté déjà inclus) seront supprimés.
                                </p>
                            )}
                        </FormSection>

                        <Dialog open={pickerOpen} onOpenChange={setPickerOpen}>
                            <DialogContent className="gap-0 p-0 sm:max-w-lg">
                                <DialogHeader className="border-b px-6 py-5">
                                    <DialogTitle>
                                        Établissement du compte
                                    </DialogTitle>
                                    <DialogDescription>
                                        Le compte ne verra et ne traitera que
                                        les étudiants de cet établissement.
                                    </DialogDescription>
                                </DialogHeader>
                                <div className="space-y-3 px-6 py-4">
                                    <div className="relative">
                                        <Search
                                            className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                            aria-hidden="true"
                                        />
                                        <Input
                                            value={query}
                                            onChange={(event) =>
                                                setQuery(event.target.value)
                                            }
                                            placeholder="Rechercher par code ou nom"
                                            aria-label="Rechercher un établissement"
                                            className="pl-9"
                                            autoFocus
                                        />
                                    </div>
                                    <div
                                        role="radiogroup"
                                        aria-label="Établissements actifs"
                                        className="max-h-80 divide-y overflow-y-auto rounded-lg border"
                                    >
                                        {visible.length === 0 ? (
                                            <p className="px-3.5 py-6 text-center text-sm text-muted-foreground">
                                                Aucun établissement actif ne
                                                correspond.
                                            </p>
                                        ) : (
                                            visible.map((item) => {
                                                const active =
                                                    pending === String(item.id);
                                                return (
                                                    <button
                                                        key={item.id}
                                                        type="button"
                                                        role="radio"
                                                        aria-checked={active}
                                                        onClick={() =>
                                                            setPending(
                                                                String(item.id),
                                                            )
                                                        }
                                                        onDoubleClick={() =>
                                                            confirmPicker(
                                                                String(item.id),
                                                            )
                                                        }
                                                        className={cn(
                                                            'flex w-full items-center gap-3 px-3.5 py-2.5 text-left text-sm transition-colors focus-visible:bg-accent focus-visible:outline-none',
                                                            active
                                                                ? 'bg-accent'
                                                                : 'hover:bg-accent/50',
                                                        )}
                                                    >
                                                        <span className="w-20 shrink-0 font-mono text-xs font-semibold text-muted-foreground">
                                                            {item.code}
                                                        </span>
                                                        <span className="min-w-0 flex-1 truncate text-foreground">
                                                            {item.name}
                                                        </span>
                                                        {active && (
                                                            <Check
                                                                className="size-4 shrink-0 text-primary"
                                                                aria-hidden="true"
                                                            />
                                                        )}
                                                    </button>
                                                );
                                            })
                                        )}
                                    </div>
                                </div>
                                <DialogFooter className="border-t px-6 py-4">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => setPickerOpen(false)}
                                    >
                                        Annuler
                                    </Button>
                                    <Button
                                        type="button"
                                        disabled={!pending}
                                        onClick={() => confirmPicker(pending)}
                                    >
                                        Valider
                                    </Button>
                                </DialogFooter>
                            </DialogContent>
                        </Dialog>

                        <FormActions
                            processing={form.processing}
                            submitLabel={
                                editing
                                    ? 'Enregistrer les modifications'
                                    : 'Créer et inviter'
                            }
                            submitIcon={editing ? Save : Mail}
                            cancelHref={cancel}
                        />
                    </div>

                    <aside className="hidden xl:block">
                        <div className="surface sticky top-24 space-y-4 p-5">
                            <p className="font-heading font-bold text-foreground">
                                {editing ? 'Bon à savoir' : 'Comment ça marche'}
                            </p>
                            <ol className="space-y-4 text-sm">
                                {[
                                    {
                                        icon: ShieldCheck,
                                        title: 'Un rôle, des droits par défaut',
                                        text: 'Les droits du rôle sont réglés dans Permissions et s’appliquent à tous ses comptes.',
                                    },
                                    {
                                        icon: Mail,
                                        title: 'Invitation par email',
                                        text: `La personne choisit son mot de passe via un lien valable ${invitationMinutes} minutes, renvoyable depuis sa fiche.`,
                                    },
                                    {
                                        icon: KeyRound,
                                        title: 'Ajustements par compte',
                                        text: 'Depuis la fiche, ajoutez ou retirez des droits pour ce seul compte.',
                                    },
                                ].map(({ icon: Icon, title, text }, index) => (
                                    <li key={title} className="flex gap-3">
                                        <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-accent text-sm font-bold text-primary">
                                            {index + 1}
                                        </span>
                                        <span>
                                            <span className="flex items-center gap-1.5 font-semibold text-foreground">
                                                <Icon
                                                    className="size-4 text-primary"
                                                    aria-hidden="true"
                                                />
                                                {title}
                                            </span>
                                            <span className="mt-0.5 block text-muted-foreground">
                                                {text}
                                            </span>
                                        </span>
                                    </li>
                                ))}
                            </ol>
                        </div>
                    </aside>
                </form>
            </PageBody>
        </>
    );
}

UserForm.layout = {
    breadcrumbs: [{ title: 'Utilisateurs', href: '/users' }],
};
