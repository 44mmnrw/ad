<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * Seed roles and permissions for platform users.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'dashboard.view',
            'orders.view',
            'orders.manage',
            'counterparties.view',
            'counterparties.manage',
            'settings.integrations.manage',
            'driver.panel.view',
        ];

        foreach ($permissions as $permissionName) {
            Permission::query()->firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web',
            ]);
        }

        $adminRole = Role::query()->firstOrCreate([
            'name' => 'admin',
            'guard_name' => 'web',
        ]);

        $managerRole = Role::query()->firstOrCreate([
            'name' => 'manager',
            'guard_name' => 'web',
        ]);

        $adminRole->syncPermissions($permissions);

        $managerRole->syncPermissions([
            'dashboard.view',
            'orders.view',
            'orders.manage',
            'counterparties.view',
            'counterparties.manage',
            'driver.panel.view',
        ]);

        User::query()->get()->each(function (User $user) {
            $roleName = in_array($user->role, ['admin', 'manager'], true)
                ? $user->role
                : 'manager';

            $user->syncRoles([$roleName]);
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
