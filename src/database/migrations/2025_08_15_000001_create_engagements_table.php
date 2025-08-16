<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('engagements')) return;

        Schema::create('engagements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            // enum ではなく string で（SQLite 互換）
            $table->string('role', 50)->index();    // 例: 'org-admin', 'engineer'
            $table->string('status', 20)->default('active')->index(); // 'active' / 'inactive'
            $table->date('effective_from')->index();
            $table->date('effective_to')->nullable()->index();
            $table->timestamps();

            $table->index(['user_id', 'company_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('engagements');
    }
};



