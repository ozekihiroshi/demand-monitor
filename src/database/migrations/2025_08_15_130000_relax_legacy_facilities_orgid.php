<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // NOTE: doctrine/dbal 無しで生SQLにしています
        // FKが付いていても、列を NULL 許可にするだけなら基本OKです
        DB::statement('ALTER TABLE facilities MODIFY organization_id BIGINT UNSIGNED NULL');
    }

    public function down(): void
    {
        // 元に戻すなら NOT NULL へ（必要なら）
        DB::statement('ALTER TABLE facilities MODIFY organization_id BIGINT UNSIGNED NOT NULL');
    }
};

