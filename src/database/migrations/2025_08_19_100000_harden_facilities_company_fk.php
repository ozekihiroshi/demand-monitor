
<?php
// database/migrations/2025_08_19_100000_harden_facilities_company_fk.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('facilities', function (Blueprint $table) {
            // 既に列がある前提
            $table->unsignedBigInteger('company_id')->nullable(false)->change();
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
        });
    }
    public function down(): void {
        Schema::table('facilities', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->unsignedBigInteger('company_id')->nullable()->change();
        });
    }
};

