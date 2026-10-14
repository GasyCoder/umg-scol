<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        try {
            DB::transaction(function (): void {
                $guard = config('access.guard');

                $newPermissions = [];
                foreach (config()->array('access.permissions') as $name) {
                    $permission = Permission::query()->firstOrCreate(['name' => $name, 'guard_name' => $guard]);
                    if ($permission->wasRecentlyCreated) {
                        $newPermissions[] = $name;
                    }
                }
                foreach (array_keys(config()->array('access.roles')) as $name) {
                    $role = Role::query()->firstOrCreate(['name' => $name, 'guard_name' => $guard]);
                    $defaults = config()->array('access.roles.'.$name);
                    $grants = $role->wasRecentlyCreated ? $defaults : array_values(array_intersect($defaults, $newPermissions));
                    if ($grants !== []) {
                        $role->givePermissionTo($grants);
                    }
                }
            });
        } finally {
            $registrar->forgetCachedPermissions();
        }
    }
}
