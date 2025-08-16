<?php
// database/seeders/DemoUsersSeeder.php
namespace Database\Seeders;

use App\Models\User;
use App\Models\Company;
use App\Models\Facility;
use App\Models\Engagement;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DemoUsersSeeder extends Seeder
{
    public function run(): void
    {
        // 会社/施設（既にあるなら流用）
        $company = Company::firstOrCreate(
            ['slug' => 'demo-co'],
            ['name' => 'デモ株式会社']
        );
        $facility = Facility::firstOrCreate(
            ['slug' => 'plant-1'],
            ['company_id' => $company->id, 'name' => '第1工場', 'timezone' => 'Asia/Tokyo']
        );

        // ユーザー作成（idempotent）
        $super = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            ['name'=>'Super Admin','password'=>Hash::make('password'),'email_verified_at'=>now()]
        );
        $super->assignRole('super-admin');

        $engineer = User::firstOrCreate(
            ['email' => 'engineer@example.com'],
            ['name'=>'Engineer','password'=>Hash::make('password'),'email_verified_at'=>now()]
        );
        $engineer->assignRole('org-admin'); // UI上のロール
        // 会社への委託（スコープ）
        Engagement::updateOrCreate(
            ['user_id'=>$engineer->id,'company_id'=>$company->id,'role'=>'org-admin'],
            ['status'=>'active','effective_from'=>now()->toDateString()]
        );

        $operator = User::firstOrCreate(
            ['email' => 'operator@example.com'],
            ['name'=>'Operator','password'=>Hash::make('password'),'email_verified_at'=>now()]
        );
        // 施設割当（facility_user）
        DB::table('facility_user')->updateOrInsert(
            ['user_id'=>$operator->id,'facility_id'=>$facility->id],
            [
                'role'=>'facility-operator',
                'effective_from'=>now()->toDateString(),
                'created_at'=>now(),'updated_at'=>now(),
            ]
        );
    }
}


