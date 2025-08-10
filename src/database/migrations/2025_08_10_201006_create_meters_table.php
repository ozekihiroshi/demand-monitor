<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('meters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->string('name')->nullable();             // 表示名
            $table->string('demand_ip')->nullable()->index(); // 既存システムの識別子(d100346など)
            $table->string('channel')->nullable();          // 物理チャネル/CT番号等
            $table->unsignedInteger('pulse_per_kwh')->default(1000);
            $table->json('config')->nullable();             // 係数・補正・契約電力等
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('meters');
    }
};

