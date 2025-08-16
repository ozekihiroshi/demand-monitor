<?php
// database/seeders/DemoDatabaseSeeder.php
namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DemoDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            DemoMetaSeeder::class,   // 会社/施設など（既に作成済みならそのまま）
            DemoUsersSeeder::class,  // ↑の新規
        ]);
    }
}


