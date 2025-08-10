<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('organization_user', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // ロール: super_admin / org_admin / manager / viewer など
            $table->string('role')->default('viewer');
            $table->boolean('is_owner')->default(false); // 組織作成者フラグ等

            $table->timestamps();

            $table->unique(['organization_id', 'user_id']);
            $table->index(['user_id', 'role']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('organization_user');
    }
};

