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

        $guard = 'web';

        // 権限（必要に応じて増減）
        $perms = [
            'meters.view', 'meters.create', 'meters.update', 'meters.delete', 'meters.manage',
            'alerts.manage',
            'users.manage',
            'reports.view',
        ];
        foreach ($perms as $p) {
            Permission::findOrCreate($p, $guard);
        }

        // ロール（あなたの4ロール + 将来の閲覧専用）
        $super   = Role::findOrCreate('super-admin',    $guard); // 全許可は Gate::before で処理
        $eng     = Role::findOrCreate('engineer',       $guard); // 電気管理技術者
        $company = Role::findOrCreate('company-admin',  $guard); // 企業の管理者
        $facility= Role::findOrCreate('facility-admin', $guard); // 施設の管理者
        $viewer  = Role::findOrCreate('viewer',         $guard); // （任意）閲覧専用

        // 付与方針：engineer/company-admin/facility-admin は「編集可」（=フル権限）
        $editAll = ['meters.view','meters.create','meters.update','meters.delete','meters.manage','alerts.manage','users.manage','reports.view'];
        $eng->syncPermissions($editAll);
        $company->syncPermissions($editAll);
        $facility->syncPermissions($editAll);

        // viewer は閲覧のみ
        $viewer->syncPermissions(['meters.view','reports.view']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
