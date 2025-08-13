<?php
namespace Database\Seeders;

use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndAdminSeeder extends Seeder
{
    public function run(): void
    {
        // 権限
        $perms = [
            'meters.view','meters.manage',
            'alerts.manage','users.manage','reports.view',
        ];
        foreach ($perms as $p) {
            Permission::firstOrCreate(['name'=>$p]);
        }

        // ロール
        $roles = [
            'super-admin' => ['*'], // 全権（Gate::beforeで処理）
            'org-admin'   => ['meters.view','meters.manage','alerts.manage','reports.view'],
            'operator'    => ['meters.view','meters.manage','reports.view'],
            'viewer'      => ['meters.view','reports.view'],
        ];
        foreach ($roles as $name => $allowed) {
            $role = Role::firstOrCreate(['name'=>$name]);
            if ($allowed !== ['*']) {
                $role->syncPermissions($allowed);
            }
        }

        // 管理者ユーザ作成（存在しなければ）
        $admin = User::firstOrCreate(
            ['email' => env('ADMIN_EMAIL','admin@example.com')],
            [
                'name' => 'System Admin',
                'password' => Hash::make(env('ADMIN_PASSWORD','password')),
            ]
        );
        $admin->assignRole('super-admin');

        // デフォルトグループ
        $group = Group::firstOrCreate(['name' => 'Default']);
        $group->users()->syncWithoutDetaching([$admin->id => ['role' => 'org-admin']]);

        // 既存ユーザーを暫定で Default に所属
        User::query()->where('id','!=',$admin->id)->get()->each(function($u) use ($group){
            $group->users()->syncWithoutDetaching([$u->id => ['role' => 'viewer']]);
            if (!$u->hasAnyRole(['org-admin','operator','viewer','super-admin'])) {
                $u->assignRole('viewer');
            }
        });
    }
}
