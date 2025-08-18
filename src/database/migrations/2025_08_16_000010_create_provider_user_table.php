<?php

// database/migrations/2025_08_16_000010_create_provider_user_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        if (!Schema::hasTable('provider_user')) {
            Schema::create('provider_user', function (Blueprint $t) {
                $t->id();
                $t->foreignId('provider_id')->constrained()->cascadeOnDelete();
                $t->foreignId('user_id')->constrained()->cascadeOnDelete();
                $t->string('role')->default('viewer'); // viewer|admin など
                $t->dateTime('valid_from')->nullable();
                $t->dateTime('valid_until')->nullable();
                $t->timestamps();
                $t->unique(['provider_id', 'user_id']);
                $t->index(['provider_id','role']);
            });
        }
    }
    public function down(): void {
        Schema::dropIfExists('provider_user');
    }
};

