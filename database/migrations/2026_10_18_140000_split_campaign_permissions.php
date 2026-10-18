<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const ACTIONS = ['campaigns.view', 'campaigns.create', 'campaigns.update', 'campaigns.open', 'campaigns.close'];

    /**
     * « campaigns.manage » becomes one permission per action. Who administered campaigns keeps every
     * action; who read them through scholarships.view keeps the reading. Roles and accounts alike.
     */
    public function up(): void
    {
        $tables = config('permission.table_names');
        $guard = config('access.guard', 'web');
        $old = DB::table($tables['permissions'])->where('name', 'campaigns.manage')->where('guard_name', $guard)->value('id');
        $view = DB::table($tables['permissions'])->where('name', 'scholarships.view')->where('guard_name', $guard)->value('id');
        $ids = [];
        foreach (self::ACTIONS as $name) {
            $ids[$name] = DB::table($tables['permissions'])->where('name', $name)->where('guard_name', $guard)->value('id')
                ?? DB::table($tables['permissions'])->insertGetId(['name' => $name, 'guard_name' => $guard, 'created_at' => now(), 'updated_at' => now()]);
        }
        $copy = function (string $table, ?int $from, array $names) use ($ids) {
            if ($from === null) {
                return;
            }
            foreach (DB::table($table)->where('permission_id', $from)->get() as $grant) {
                foreach ($names as $name) {
                    $row = [...(array) $grant, 'permission_id' => $ids[$name]];
                    DB::table($table)->insertOrIgnore($row);
                }
            }
        };
        $copy($tables['role_has_permissions'], $old, self::ACTIONS);
        $copy($tables['model_has_permissions'], $old, self::ACTIONS);
        $copy($tables['role_has_permissions'], $view, ['campaigns.view']);
        if ($old !== null) {
            DB::table($tables['role_has_permissions'])->where('permission_id', $old)->delete();
            DB::table($tables['model_has_permissions'])->where('permission_id', $old)->delete();
            DB::table($tables['permissions'])->where('id', $old)->delete();
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $tables = config('permission.table_names');
        $guard = config('access.guard', 'web');
        $old = DB::table($tables['permissions'])->insertGetId(['name' => 'campaigns.manage', 'guard_name' => $guard, 'created_at' => now(), 'updated_at' => now()]);
        $create = DB::table($tables['permissions'])->where('name', 'campaigns.create')->where('guard_name', $guard)->value('id');
        foreach (DB::table($tables['role_has_permissions'])->where('permission_id', $create)->get() as $grant) {
            DB::table($tables['role_has_permissions'])->insertOrIgnore([...(array) $grant, 'permission_id' => $old]);
        }
        DB::table($tables['permissions'])->whereIn('name', self::ACTIONS)->where('guard_name', $guard)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
