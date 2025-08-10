<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class InitialSeed extends Seeder
{
    public function run(): void
    {
        // ユーザー
        $userId = DB::table('users')->insertGetId([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('secret@Pass123!'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 組織
        $orgId = DB::table('organizations')->insertGetId([
            'name' => 'Sample Org',
            'slug' => 'sample-org',
            'type' => 'electric_manager',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 紐付け（オーナー&管理者）
        DB::table('organization_user')->insert([
            'organization_id' => $orgId,
            'user_id' => $userId,
            'role' => 'org_admin',
            'is_owner' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 事業所・メータ
        $facilityId = DB::table('facilities')->insertGetId([
            'organization_id' => $orgId,
            'name' => '工場A',
            'code' => 'FA-001',
            'address' => 'Tokyo',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $meterId = DB::table('meters')->insertGetId([
            'facility_id' => $facilityId,
            'name' => '主幹メータ',
            'demand_ip' => 'd100346',
            'pulse_per_kwh' => 1000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // デマンド（ダミー1本）
        DB::table('demands')->insert([
            'meter_id' => $meterId,
            'date' => today('Asia/Tokyo')->format('Y-m-d'),
            'slot' => 20,
            'max_data' => 457.81,
            'shikiichi' => 800.00,
            'predicted' => 470.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

