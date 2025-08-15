<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        Schema::table('facilities', function (Blueprint $table) {
            if (!Schema::hasColumn('facilities', 'company_id')) {
                $table->unsignedBigInteger('company_id')->nullable()->after('id');
            }
            if (!Schema::hasColumn('facilities', 'slug')) {
                $table->string('slug', 128)->nullable()->after('company_id');
            }
            if (!Schema::hasColumn('facilities', 'timezone')) {
                $table->string('timezone', 64)->nullable()->after('slug');
            }
            if (!Schema::hasColumn('facilities', 'default_threshold')) {
                $table->unsignedInteger('default_threshold')->nullable()->after('timezone');
            }
            if (!Schema::hasColumn('facilities', 'default_rate')) {
                $table->json('default_rate')->nullable()->after('default_threshold');
            }
        });
        // CHECKはMySQL8なら有効。JSONの妥当性チェック（存在時のみ付与）
        try {
            DB::statement("ALTER TABLE facilities ADD CONSTRAINT chk_facilities_default_rate_json CHECK (JSON_VALID(COALESCE(default_rate, JSON_OBJECT())))");
        } catch (\Throwable $e) { /* 既に存在・非対応は無視 */ }
    }

    public function down(): void {
        Schema::table('facilities', function (Blueprint $table) {
            if (Schema::hasColumn('facilities', 'default_rate')) $table->dropColumn('default_rate');
            if (Schema::hasColumn('facilities', 'default_threshold')) $table->dropColumn('default_threshold');
            if (Schema::hasColumn('facilities', 'timezone')) $table->dropColumn('timezone');
            if (Schema::hasColumn('facilities', 'slug')) $table->dropColumn('slug');
            if (Schema::hasColumn('facilities', 'company_id')) $table->dropColumn('company_id');
        });
        try { DB::statement("ALTER TABLE facilities DROP CONSTRAINT chk_facilities_default_rate_json"); } catch (\Throwable $e) {}
    }
};
