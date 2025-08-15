<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DemoMetaSeeder extends Seeder
{
    // ★必要ならここを書き換え（ログイン確認用）
    const ORG_ADMIN_EMAIL     = 'engineer@example.com';
    const FACILITY_OPERATOR_EMAIL = 'operator@example.com';
    const DEFAULT_PASSWORD    = 'password';

    public function run(): void
    {
        DB::beginTransaction();

        // 1) 会社
        $companyId = DB::table('companies')->updateOrInsert(
            ['slug' => 'demo-co'],
            ['name' => 'デモ会社', 'updated_at' => now(), 'created_at' => now()]
        );
        $company = DB::table('companies')->where('slug','demo-co')->first();

        // 2) 施設
        $facilityId = DB::table('facilities')->updateOrInsert(
            ['slug' => 'plant-1'],
            [
                'company_id'       => $company->id,
                'name'             => '第1工場',
                'timezone'         => 'Asia/Tokyo',
                'default_threshold'=> 800,
                'default_rate'     => json_encode(['version'=>1,'currency'=>'JPY']),
                'updated_at'       => now(),
                'created_at'       => now(),
            ]
        );
        $facility = DB::table('facilities')->where('slug','plant-1')->first();

        // 3) メーター（既存テーブル想定）
        $ensureMeter = function(string $code, string $name) use ($facility) {
            $exists = DB::table('meters')->where('code', $code)->first();
            if (!$exists) {
                DB::table('meters')->insert([
                    'facility_id'        => $facility->id,
                    'code'               => $code,
                    'name'               => $name,
                    'kind'               => 'consumption',
                    'pulse_per_kwh'      => 1000,
                    'threshold_override' => null,
                    'rate_override'      => null,
                    'created_at'         => now(),
                    'updated_at'         => now(),
                ]);
            } else {
                DB::table('meters')->where('id',$exists->id)->update([
                    'facility_id'   => $facility->id,
                    'updated_at'    => now(),
                ]);
            }
        };
        $ensureMeter('d100318','主幹メーター');
        $ensureMeter('d100346','ライン1');

        // 4) ユーザー（存在しなければ作成：開発/検証用）
        $ensureUser = function(string $email, string $name) {
            $user = DB::table('users')->where('email',$email)->first();
            if (!$user) {
                DB::table('users')->insert([
                    'name'       => $name,
                    'email'      => $email,
                    'password'   => bcrypt(self::DEFAULT_PASSWORD),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $user = DB::table('users')->where('email',$email)->first();
            }
            return $user;
        };
        $orgAdmin   = $ensureUser(self::ORG_ADMIN_EMAIL, '技術者デモ');
        $operator   = $ensureUser(self::FACILITY_OPERATOR_EMAIL, '工場担当デモ');

        // 5) 会社スコープ付与（engagements）
        $hasEng = DB::table('engagements')
            ->where('user_id',$orgAdmin->id)
            ->where('company_id',$company->id)
            ->where('status','active')
            ->exists();

        if (!$hasEng) {
            DB::table('engagements')->insert([
                'user_id'        => $orgAdmin->id,
                'company_id'     => $company->id,
                'role'           => 'org-admin',
                'status'         => 'active',
                'effective_from' => now()->toDateString(),
                'effective_to'   => null,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);
        }

        // 6) 施設スコープ付与（facility_user：更新のみの担当）
        $hasFu = DB::table('facility_user')
            ->where('user_id',$operator->id)
            ->where('facility_id',$facility->id)
            ->exists();

        if (!$hasFu) {
            DB::table('facility_user')->insert([
                'user_id'        => $operator->id,
                'facility_id'    => $facility->id,
                'role'           => 'facility-operator',
                'effective_from' => now()->toDateString(),
                'effective_to'   => null,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);
        }

        DB::commit();

        $this->command->info('Demo meta seeded.');
        $this->command->warn('ログイン例:');
        $this->command->line('- 技術者(会社スコープ): '.self::ORG_ADMIN_EMAIL.' / '.self::DEFAULT_PASSWORD);
        $this->command->line('- 施設担当(施設スコープ): '.self::FACILITY_OPERATOR_EMAIL.' / '.self::DEFAULT_PASSWORD);
    }
}