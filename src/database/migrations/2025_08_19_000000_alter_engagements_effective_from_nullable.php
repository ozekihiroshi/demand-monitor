<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // SQLite(=テスト) では ALTER ... MODIFY が使えないのでスキップ
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        // MySQL 用の ALTER
        DB::statement(
            "ALTER TABLE engagements
             MODIFY effective_from DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP"
        );
    }

    public function down(): void
    {
        // SQLite では何もしない
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        // 元に戻す（必要なら）
        DB::statement(
            "ALTER TABLE engagements
             MODIFY effective_from DATETIME NULL DEFAULT NULL"
        );
    }
};
