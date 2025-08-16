<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('facilities', 'organization_id')) {
            return; // 列が無ければ何もしない
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            // MySQL/MariaDB だけ実行
            DB::statement('ALTER TABLE `facilities` MODIFY `organization_id` BIGINT UNSIGNED NULL');
        } else {
            // SQLite / PostgreSQL は何もしない（テストでは org_id NOT NULL のままでOK）
            // ※ テストのFactory/セットアップ側で organization_id を埋めているため支障なし
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('facilities', 'organization_id')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE `facilities` MODIFY `organization_id` BIGINT UNSIGNED NOT NULL');
        } else {
            // no-op
        }
    }
};


