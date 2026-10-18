<?php

namespace App\Http\Controllers\Permissions;

use App\Actions\Permissions\SaveRolePermissions;
use App\Http\Controllers\Controller;
use App\Http\Requests\Permissions\SaveRolePermissionsRequest;
use App\Support\Permissions\PermissionCatalogue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('permissions.manage-ui');
        // The developer role is shown to developers only.
        $roles = Role::query()->whereIn('name', PermissionCatalogue::visibleRoles($request->user()))->where('guard_name', config('access.guard'))->with('permissions:id,name')->get()->map(fn ($role) => ['name' => $role->name, 'label' => PermissionCatalogue::ROLES[$role->name], 'permissions' => $role->permissions->pluck('name')->values()->all(), 'version' => PermissionCatalogue::version($role->permissions->map(fn (Permission $p): string => $p->name)->all())]);

        return Inertia::render('permissions/index', ['roles' => $roles, 'catalogue' => PermissionCatalogue::rows(), 'protectedPermissions' => PermissionCatalogue::PROTECTED, 'studentPermissions' => PermissionCatalogue::STUDENT]);
    }

    public function update(SaveRolePermissionsRequest $request, string $roleName, SaveRolePermissions $action): RedirectResponse
    {
        $action->handle($request->user(), $roleName, $request->validated('permissions'), $request->validated('version'));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Permissions enregistrées.']);

        return to_route(Gate::allows('permissions.manage-ui') ? 'permissions.index' : 'dashboard');
    }
}
