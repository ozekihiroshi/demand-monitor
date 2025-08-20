<?php
// database/migrations/2025_08_20_000001_add_organization_id_to_companies_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('companies', 'organization_id')) {
            Schema::table('companies', function (Blueprint $table) {
                // SQLite でも動く形（after() は使わない）
                $table->foreignId('organization_id')
                    ->constrained()      // organizations(id) を想定
                    ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('companies', 'organization_id')) {
            Schema::table('companies', function (Blueprint $table) {
                // 外部キー → カラムの順で落とす（存在チェックはドライバに任せる）
                $table->dropConstrainedForeignId('organization_id');
            });
        }
    }
};


