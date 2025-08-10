<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name');                 // 組織名
            $table->string('slug')->unique();       // URL等に使える一意キー
            $table->string('type')->nullable();     // electric_manager / business_owner 等、将来拡張用
            $table->json('settings')->nullable();   // 組織単位の設定(JSON)
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('organizations');
    }
};

