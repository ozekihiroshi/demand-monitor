<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Guard は web 前提（config/auth.php のデフォルト）
        $guard = 'web';

        // Permissions
        $perms = [
            'meters.view',
            'meters.create',
            'meters.update',
            'meters.delete',
            'meters.manage', // 総合管理（任意）
        ];
        foreach ($perms as $p) {
            Permission::findOrCreate($p, $guard);
        }

        // Roles
        $super   = Role::findOrCreate('super-admin', $guard);
        $org     = Role::findOrCreate('org-admin',    $guard);
        $op      = Role::findOrCreate('operator',     $guard);
        $viewer  = Role::findOrCreate('viewer',       $guard);

        // 付与ポリシー（テストの意図に合わせて）
        $viewer->givePermissionTo(['meters.view']);
        $op->givePermissionTo(['meters.view', 'meters.update']);
        $org->givePermissionTo(['meters.view', 'meters.create', 'meters.update', 'meters.delete']);

        // super-admin は Policy::before() で全部許可する設計だが、
        // 念のため全付与しても良い（下行は任意）
        $super->givePermissionTo($perms);
    }
}
