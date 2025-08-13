<?php
// database/migrations/2025_08_14_000000_alter_meters_for_crud.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('meters', function (Blueprint $table) {
            if (!Schema::hasColumn('meters', 'name')) {
                $table->string('name', 100);
            }
            if (!Schema::hasColumn('meters', 'group_id')) {
                $table->foreignId('group_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            }
            if (!Schema::hasColumn('meters', 'threshold_override')) {
                $table->unsignedInteger('threshold_override')->nullable()->comment('kW');
            }
            if (!Schema::hasColumn('meters', 'rate_override')) {
                // JSON（将来の料金構造にも拡張しやすい）
                $table->json('rate_override')->nullable();
            }
            if (!Schema::hasColumn('meters', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }
    public function down(): void {
        Schema::table('meters', function (Blueprint $table) {
            if (Schema::hasColumn('meters', 'deleted_at')) $table->dropSoftDeletes();
            foreach (['name','group_id','threshold_override','rate_override'] as $c) {
                if (Schema::hasColumn('meters', $c)) $table->dropColumn($c);
            }
        });
    }
};

