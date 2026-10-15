<?php

namespace App\Http\Controllers\Users;

use App\Actions\Users\SaveInternalUser;
use App\Actions\Users\SaveUserPermissions;
use App\Actions\Users\SendUserInvitation;
use App\Actions\Users\SetUserActive;
use App\Http\Controllers\Controller;
use App\Http\Requests\Users\SaveInternalUserRequest;
use App\Http\Requests\Users\SaveUserPermissionsRequest;
use App\Models\Activity;
use App\Models\Establishment;
use App\Models\User;
use App\Support\Audit\AuditPresenter;
use App\Support\Permissions\PermissionCatalogue;
use App\Support\Users\InternalRoles;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;
use Throwable;

class UserController extends Controller
{
    private const DESCRIPTIONS = [
        'superadmin' => 'Administration fonctionnelle globale : référentiels, comptes, droits et campagnes.',
        'central_scholarship_office' => 'Consultation globale des étudiants et traitement des dossiers papier de bourse.',
        'establishment' => 'Étudiants de son établissement : déclarations, imports, corrections et rattachements.',
        'developer' => 'Support technique privilégié, toujours visible dans le journal d’audit.',
    ];

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', User::class);
        $actor = $request->user();
        $roles = InternalRoles::assignableBy($actor);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', Rule::in($roles)],
            'status' => ['nullable', Rule::in(['active', 'suspended'])],
        ]);
        $base = fn (): Builder => User::query()->whereNull('student_id')->whereHas('roles', fn ($q) => $q->whereIn('name', $roles))
            ->whereDoesntHave('roles', fn ($q) => $q->whereNotIn('name', $roles));
        $search = trim((string) ($filters['search'] ?? ''));
        $users = $base()
            ->when($search !== '', function ($q) use ($search) {
                $term = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';
                $q->where(fn ($q) => $q->whereRaw("name LIKE ? ESCAPE '!'", [$term])->orWhereRaw("email LIKE ? ESCAPE '!'", [$term]));
            })
            ->when(filled($filters['role'] ?? null), fn ($q) => $q->role($filters['role']))
            ->when(filled($filters['status'] ?? null), fn ($q) => $q->where('is_active', $filters['status'] === 'active'))
            ->with(['roles:id,name', 'establishment:id,code,name'])->withCount(['permissions', 'deniedPermissions'])
            ->orderByDesc('is_active')->orderBy('name')->paginate(20)->withQueryString()
            ->through(fn (User $user) => [
                ...$this->summary($user),
                'granted_count' => $user->permissions_count, 'denied_count' => $user->denied_permissions_count, 'is_self' => $user->is($actor),
            ]);

        $byRole = Role::query()->whereIn('name', $roles)->withCount(['users' => fn ($q) => $q->whereNull('student_id')])->pluck('users_count', 'name');

        return Inertia::render('users/index', [
            'users' => $users,
            'filters' => $filters,
            'stats' => ['total' => $base()->count(), 'suspended' => $base()->where('is_active', false)->count(), 'by_role' => collect($roles)->mapWithKeys(fn ($role) => [$role => (int) ($byRole[$role] ?? 0)])],
            'roles' => $this->roleOptions($roles),
            'canCreate' => Gate::allows('create', User::class),
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', User::class);

        return Inertia::render('users/form', ['user' => null, ...$this->formOptions($request->user())]);
    }

    public function store(SaveInternalUserRequest $request, SaveInternalUser $action, SendUserInvitation $invitation): RedirectResponse
    {
        $user = $action->handle($request->user(), null, $request->payload());
        $sent = $this->invite($request->user(), $user, $invitation);
        Inertia::flash('toast', $sent
            ? ['type' => 'success', 'message' => 'Compte créé. Une invitation pour définir le mot de passe a été envoyée à '.$user->email.'.']
            : ['type' => 'warning', 'message' => 'Compte créé, mais l’invitation n’a pas pu être envoyée. Réessayez depuis la fiche.']);

        return to_route('users.show', $user);
    }

    public function show(Request $request, User $user): Response
    {
        Gate::authorize('view', $user);
        $user->load(['roles:id,name', 'establishment:id,code,name']);
        $role = InternalRoles::of($user);
        $rolePermissions = $role === null ? [] : Role::findByName($role, config('access.guard'))->permissions->pluck('name')->values()->all();
        $activity = Gate::allows('viewAny', Activity::class)
            ? Activity::query()->where(fn ($q) => $q->where(fn ($q) => $q->where('causer_type', $user->getMorphClass())->where('causer_id', $user->id))->orWhere(fn ($q) => $q->where('subject_type', $user->getMorphClass())->where('subject_id', $user->id)))
                ->with('causer')->latest('id')->limit(8)->get()->map(fn (Activity $a) => AuditPresenter::summary($a))
            : null;

        return Inertia::render('users/show', [
            'user' => [...$this->summary($user), 'two_factor_enabled' => $user->two_factor_confirmed_at !== null, 'email_verified_at' => $user->email_verified_at?->toIso8601String(), 'created_at' => $user->created_at?->toIso8601String()],
            'rights' => [
                'role' => $rolePermissions, 'granted' => SaveUserPermissions::granted($user), 'denied' => SaveUserPermissions::denied($user),
                'version' => SaveUserPermissions::version($user), 'protected' => $role === 'superadmin' ? PermissionCatalogue::PROTECTED : [],
            ],
            'catalogue' => PermissionCatalogue::rows(),
            'activity' => $activity,
            'can' => ['update' => Gate::allows('update', $user), 'managePermissions' => Gate::allows('managePermissions', $user), 'isSelf' => $user->is($request->user())],
            'invitationMinutes' => (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire'),
        ]);
    }

    public function edit(Request $request, User $user): Response
    {
        Gate::authorize('update', $user);
        $user->load(['roles:id,name', 'establishment:id,code,name']);

        return Inertia::render('users/form', ['user' => $this->summary($user), ...$this->formOptions($request->user())]);
    }

    public function update(SaveInternalUserRequest $request, User $user, SaveInternalUser $action): RedirectResponse
    {
        $action->handle($request->user(), $user, $request->payload());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Compte mis à jour.']);

        return to_route('users.show', $user);
    }

    public function status(Request $request, User $user, SetUserActive $action): RedirectResponse
    {
        $active = $request->validate(['active' => ['required', 'boolean']])['active'];
        $action->handle($request->user(), $user, (bool) $active);
        Inertia::flash('toast', ['type' => 'success', 'message' => $active ? 'Compte réactivé.' : 'Compte suspendu : ses sessions ouvertes ont été fermées.']);

        return back();
    }

    public function permissions(SaveUserPermissionsRequest $request, User $user, SaveUserPermissions $action): RedirectResponse
    {
        $action->handle($request->user(), $user, $request->validated('granted'), $request->validated('denied'), $request->validated('version'));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Droits du compte enregistrés.']);

        return to_route('users.show', $user);
    }

    private function invite(User $actor, User $user, SendUserInvitation $invitation): bool
    {
        try {
            $invitation->handle($actor, $user);

            return true;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }

    public function resendInvitation(Request $request, User $user, SendUserInvitation $invitation): RedirectResponse
    {
        Gate::authorize('update', $user);
        abort_unless($user->is_active, 422);
        Inertia::flash('toast', $this->invite($request->user(), $user, $invitation)
            ? ['type' => 'success', 'message' => 'Invitation renvoyée à '.$user->email.'.']
            : ['type' => 'error', 'message' => 'L’invitation n’a pas pu être envoyée.']);

        return back();
    }

    /** @return array<string,mixed> */
    private function summary(User $user): array
    {
        $role = InternalRoles::of($user);

        return [
            'id' => $user->id, 'uuid' => $user->uuid, 'name' => $user->name, 'email' => $user->email, 'is_active' => $user->is_active,
            'role' => $role, 'role_label' => $role === null ? null : PermissionCatalogue::ROLES[$role],
            'establishment' => $user->establishment?->only(['id', 'code', 'name']),
        ];
    }

    /**
     * @param  list<string>  $roles
     * @return list<array{value:string,label:string,description:string,permissions_count:int}>
     */
    private function roleOptions(array $roles): array
    {
        $counts = Role::query()->whereIn('name', $roles)->withCount('permissions')->pluck('permissions_count', 'name');

        return array_map(fn ($role) => ['value' => $role, 'label' => PermissionCatalogue::ROLES[$role], 'description' => self::DESCRIPTIONS[$role], 'permissions_count' => (int) ($counts[$role] ?? 0)], $roles);
    }

    /** @return array<string,mixed> */
    private function formOptions(User $actor): array
    {
        return [
            'roles' => $this->roleOptions(InternalRoles::assignableBy($actor)),
            'establishments' => Establishment::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'invitationMinutes' => (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire'),
        ];
    }
}
