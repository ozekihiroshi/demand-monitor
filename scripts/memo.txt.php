<?php


docker compose exec -w /var/www/html app php artisan tinker

use App\Models\User;
use App\Models\Group;
use App\Models\Meter;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/* 1) 役割の用意（guard は web） */
foreach (['super-admin','org-admin','operator','viewer'] as $r) {
    Role::firstOrCreate(['name'=>$r,'guard_name'=>'web']);
}

/* 2) テスト用メール（必要なら書き換えOK） */
$emails = [
  'super'    => 'dev-super@local.test',
  'org'      => 'dev-org@local.test',
  'operator' => 'dev-operator@local.test',
  'viewer'   => 'dev-viewer@local.test',
];

/* 古い同名ユーザーを掃除（あれば） */
User::whereIn('email', array_values($emails))->delete();

/* 3) 少なくとも1つはグループを用意 */
$group = Group::first() ?: Group::create(['name'=>'DEMO']);
$gid = $group->id;

/* 4) ユーザー作成（メール認証済み・同一パスワード） */
$pw = Hash::make('Password1!');
$uSuper    = User::create(['name'=>'Dev Super','email'=>$emails['super'],'password'=>$pw,'email_verified_at'=>now()]);
$uOrg      = User::create(['name'=>'Dev Org','email'=>$emails['org'],'password'=>$pw,'email_verified_at'=>now()]);
$uOperator = User::create(['name'=>'Dev Operator','email'=>$emails['operator'],'password'=>$pw,'email_verified_at'=>now()]);
$uViewer   = User::create(['name'=>'Dev Viewer','email'=>$emails['viewer'],'password'=>$pw,'email_verified_at'=>now()]);

/* 5) ロール付与 */
$uSuper->syncRoles(['super-admin']);
$uOrg->syncRoles(['org-admin']);
$uOperator->syncRoles(['operator']);
$uViewer->syncRoles(['viewer']);

/* 6) グループ所属（org-admin/operator/viewer は所属必須） */
$uOrg->groups()->syncWithoutDetaching([$gid]);
$uOperator->groups()->syncWithoutDetaching([$gid]);
$uViewer->groups()->syncWithoutDetaching([$gid]);

/* 7) Spatieのキャッシュをクリア */
cache()->forget('spatie.permission.cache');

/* 8) 動作確認（true が返ればOK） */
$uSuper->hasRole('super-admin');     // true
$uOrg->getRoleNames();               // ["org-admin"]

database/seeders/DevTestUsersSeeder.php
<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Group;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DevTestUsersSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['super-admin','org-admin','operator','viewer'] as $r) {
            Role::firstOrCreate(['name'=>$r,'guard_name'=>'web']);
        }

        $emails = [
            'super'    => 'dev-super@local.test',
            'org'      => 'dev-org@local.test',
            'operator' => 'dev-operator@local.test',
            'viewer'   => 'dev-viewer@local.test',
        ];
        User::whereIn('email', array_values($emails))->delete();

        $group = Group::first() ?: Group::create(['name'=>'DEMO']);
        $pw = Hash::make('Password1!');

        $uSuper    = User::firstOrCreate(['email'=>$emails['super']],    ['name'=>'Dev Super','password'=>$pw,'email_verified_at'=>now()]);
        $uOrg      = User::firstOrCreate(['email'=>$emails['org']],      ['name'=>'Dev Org','password'=>$pw,'email_verified_at'=>now()]);
        $uOperator = User::firstOrCreate(['email'=>$emails['operator']], ['name'=>'Dev Operator','password'=>$pw,'email_verified_at'=>now()]);
        $uViewer   = User::firstOrCreate(['email'=>$emails['viewer']],   ['name'=>'Dev Viewer','password'=>$pw,'email_verified_at'=>now()]);

        $uSuper->syncRoles(['super-admin']);
        $uOrg->syncRoles(['org-admin']);
        $uOperator->syncRoles(['operator']);
        $uViewer->syncRoles(['viewer']);

        $uOrg->groups()->syncWithoutDetaching([$group->id]);
        $uOperator->groups()->syncWithoutDetaching([$group->id]);
        $uViewer->groups()->syncWithoutDetaching([$group->id]);

        cache()->forget('spatie.permission.cache');
    }
}
