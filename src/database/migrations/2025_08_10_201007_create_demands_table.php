<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('demands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meter_id')->constrained()->cascadeOnDelete();

            // タイムスロット（30分粒度想定）
            $table->date('date');                  // 2025-08-10
            $table->unsignedTinyInteger('slot');   // 0..47 (1日48スロット)

            // 値
            $table->decimal('max_data', 10, 2)->nullable();  // 実績最大値 (kW)
            $table->decimal('shikiichi', 10, 2)->nullable(); // しきい値 (kW)
            $table->decimal('predicted', 10, 2)->nullable(); // 予測値 (kW)

            $table->json('raw')->nullable();       // 生データ保持(任意)

            $table->timestamps();

            $table->unique(['meter_id', 'date', 'slot']);    // メータ×日×スロットで一意
            $table->index(['date', 'slot']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('demands');
    }
};
