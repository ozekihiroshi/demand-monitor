<?php
// database/seeders/DemoOrgSeeder.php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Company;
use App\Models\Facility;
use App\Models\Meter;
use Spatie\Permission\Models\Role;

class DemoOrgSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['super-admin','power-manager','company-manager','facility-manager','viewer'] as $r) {
            Role::findOrCreate($r);
        }

        $company  = Company::firstOrCreate(['slug'=>'demo-co'], ['name'=>'Demo Company']);
        $facility = Facility::firstOrCreate(['company_id'=>$company->id, 'name'=>'Main Site'], []);
        $meter    = Meter::firstOrCreate(['code'=>'d100318'], ['facility_id'=>$facility->id]);

        $super = User::firstOrCreate(['email'=>'super@example.com'],   ['name'=>'Super',       'password'=>'password']);
        $pm    = User::firstOrCreate(['email'=>'pm@example.com'],      ['name'=>'PowerMgr',    'password'=>'password']);
        $co    = User::firstOrCreate(['email'=>'co@example.com'],      ['name'=>'CompanyMgr',  'password'=>'password']);
        $fm    = User::firstOrCreate(['email'=>'fm@example.com'],      ['name'=>'FacilityMgr', 'password'=>'password']);

        $super->assignRole('super-admin');
        $pm->assignRole('power-manager');
        $co->assignRole('company-manager');
        $fm->assignRole('facility-manager');

        // ← ここが今回のポイント：pivotに effective_from を入れる
        if (method_exists($co, 'companies')) {
            $co->companies()->syncWithoutDetaching([
                $company->id => [
                    'role'           => 'admin',
                    'status'         => 'active',
                    'effective_from' => now(),   // ★ 追加
                    'effective_to'   => null,    // ★ 任意（NOT NULLでなければnullでOK）
                ],
            ]);
        }

        // 施設紐付けは環境の設計次第。リレーションが未定なら一旦保留でOK
        if (method_exists($fm, 'facilities')) {
            $fm->facilities()->syncWithoutDetaching([$facility->id]);
        }
    }
}
