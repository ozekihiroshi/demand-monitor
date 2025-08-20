<?php
// database/migrations/2025_08_19_000000_alter_engagements_effective_from_nullable.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        // change() には doctrine/dbal が必要。入れないなら raw SQL でもOK
        DB::statement("ALTER TABLE engagements MODIFY effective_from DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP");
        DB::statement("ALTER TABLE engagements MODIFY effective_to   DATETIME NULL");
    }
    public function down(): void {
        // 必要なら元に戻す定義を記載
    }
};
