<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'dashboard.view',
            'inbox.view',
            'message.send',
            'conversation.assign',
            'conversation.resolve',
            'contact.edit',
            'contact.merge',
            'line_account.manage',
            'user.manage',
            'audit.view',
            'settings.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }

        $super = Role::findOrCreate('Super Admin');
        $admin = Role::findOrCreate('Admin');
        $staff = Role::findOrCreate('Staff');
        $viewer = Role::findOrCreate('Viewer');

        $super->syncPermissions($permissions);
        $admin->syncPermissions($permissions);

        $staff->syncPermissions([
            'dashboard.view',
            'inbox.view',
            'message.send',
            'conversation.assign',
            'conversation.resolve',
            'contact.edit',
            'contact.merge',
        ]);

        $viewer->syncPermissions([
            'dashboard.view',
            'inbox.view',
        ]);
    }
}
