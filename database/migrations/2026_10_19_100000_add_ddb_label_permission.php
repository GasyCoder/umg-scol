<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const PERMISSION = 'scholarships.labels';

    /** Sheet of blank DDB labels: superadmin (and the technical developer role) only, other roles through the matrix. */
    public function up(): void
    {
        $tables = config('permission.table_names');
        $guard = config('access.guard', 'web');
        $permission = DB::table($tables['permissions'])->where('name', self::PERMISSION)->where('guard_name', $guard)->value('id')
            ?? DB::table($tables['permissions'])->insertGetId(['name' => self::PERMISSION, 'guard_name' => $guard, 'created_at' => now(), 'updated_at' => now()]);
        foreach (DB::table($tables['roles'])->whereIn('name', ['superadmin', 'developer'])->where('guard_name', $guard)->pluck('id') as $role) {
            DB::table($tables['role_has_permissions'])->insertOrIgnore(['permission_id' => $permission, 'role_id' => $role]);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $tables = config('permission.table_names');
        $permission = DB::table($tables['permissions'])->where('name', self::PERMISSION)->where('guard_name', config('access.guard', 'web'))->value('id');
        if ($permission !== null) {
            DB::table($tables['role_has_permissions'])->where('permission_id', $permission)->delete();
            DB::table($tables['model_has_permissions'])->where('permission_id', $permission)->delete();
            DB::table($tables['permissions'])->where('id', $permission)->delete();
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
