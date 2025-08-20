<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('facilities', 'main_meter_code')) {
            Schema::table('facilities', function (Blueprint $table) {
                // SQLite でも通る形で追加（FK は張らない）
                $table->string('main_meter_code', 64)->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('facilities', 'main_meter_code')) {
            Schema::table('facilities', function (Blueprint $table) {
                $table->dropColumn('main_meter_code');
            });
        }
    }
};

