<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('facilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->nullable();      // 事業所コード
            $table->string('address')->nullable();
            $table->string('timezone')->default('Asia/Tokyo');
            $table->json('metadata')->nullable();    // 任意情報
            $table->timestamps();

            $table->index(['organization_id', 'name']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('facilities');
    }
};

