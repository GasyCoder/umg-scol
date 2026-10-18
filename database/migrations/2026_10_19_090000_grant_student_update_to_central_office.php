<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /** The central office completes missing identity fields at the reception counter (students.update). */
    public function up(): void
    {
        $tables = config('permission.table_names');
        $guard = config('access.guard', 'web');
        $permission = DB::table($tables['permissions'])->where('name', 'students.update')->where('guard_name', $guard)->value('id');
        $role = DB::table($tables['roles'])->where('name', 'central_scholarship_office')->where('guard_name', $guard)->value('id');
        if ($permission !== null && $role !== null) {
            DB::table($tables['role_has_permissions'])->insertOrIgnore(['permission_id' => $permission, 'role_id' => $role]);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $tables = config('permission.table_names');
        $guard = config('access.guard', 'web');
        $permission = DB::table($tables['permissions'])->where('name', 'students.update')->where('guard_name', $guard)->value('id');
        $role = DB::table($tables['roles'])->where('name', 'central_scholarship_office')->where('guard_name', $guard)->value('id');
        DB::table($tables['role_has_permissions'])->where('permission_id', $permission)->where('role_id', $role)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
