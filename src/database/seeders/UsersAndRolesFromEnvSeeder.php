<?php
// database/seeders/UsersAndRolesFromEnvSeeder.php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class UsersAndRolesFromEnvSeeder extends Seeder
{
    public function run(): void
    {
        $defs = [
            ['env_email'=>'SUPERADMIN_EMAIL', 'env_pass'=>'SUPERADMIN_PASS', 'name'=>'Super Admin',   'role'=>'super-admin'],
            ['env_email'=>'ENGINEER_EMAIL',   'env_pass'=>'ENGINEER_PASS',   'name'=>'Engineer',      'role'=>'engineer'],
            ['env_email'=>'COMPANY_EMAIL',    'env_pass'=>'COMPANY_PASS',    'name'=>'Company Admin', 'role'=>'company-admin'],
            ['env_email'=>'OPERATOR_EMAIL',   'env_pass'=>'OPERATOR_PASS',   'name'=>'Facility Admin','role'=>'facility-admin'],
            // 将来の閲覧専用が必要なら:
            // ['env_email'=>'PRO_VIEWER_EMAIL','env_pass'=>'PRO_VIEWER_PASS','name'=>'Viewer','role'=>'viewer'],
        ];

        foreach ($defs as $d) {
            $email = env($d['env_email']);
            $pass  = env($d['env_pass']);
            if (!$email || !$pass) continue;

            $user = User::updateOrCreate(
                ['email' => $email],
                ['name' => $d['name'], 'password' => Hash::make($pass)]
            );
            $user->syncRoles([$d['role']]);
        }
    }
}

