<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->nullable()->constrained()->nullOnDelete();
            $table->string('platform')->nullable();     // kindle / android / ios / web 等
            $table->string('hardware')->nullable();     // mt8173 など
            $table->string('identifier')->unique();     // 端末ID（UUID等）
            $table->string('version')->nullable();      // アプリ版
            $table->json('settings')->nullable();       // しきい値/音量など端末ローカル設定
            $table->timestamps();

            $table->index(['facility_id', 'platform']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('devices');
    }
};

