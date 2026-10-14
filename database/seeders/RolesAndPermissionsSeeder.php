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

                foreach (config()->array('access.permissions') as $name) {
                    Permission::findOrCreate($name, $guard);
                }

                foreach (array_keys(config()->array('access.roles')) as $name) {
                    $permissions = config()->array('access.roles.'.$name);
                    $role = Role::findOrCreate($name, $guard);
                    $current = $role->permissions()->pluck('name')->sort()->values()->all();
                    $expected = collect($permissions)->sort()->values()->all();

                    if ($current !== $expected) {
                        $role->syncPermissions($permissions);
                    }
                }
            });
        } finally {
            $registrar->forgetCachedPermissions();
        }
    }
}
