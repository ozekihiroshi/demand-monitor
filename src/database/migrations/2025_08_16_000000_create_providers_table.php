<?php
// database/migrations/2025_08_16_000000_create_providers_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        if (!Schema::hasTable('providers')) {
            Schema::create('providers', function (Blueprint $t) {
                $t->id();
                $t->string('name');
                $t->string('slug')->unique(); // /pro/{slug}
                $t->boolean('active')->default(true);
                $t->timestamps();
            });
        }
    }
    public function down(): void {
        Schema::dropIfExists('providers');
    }
};
