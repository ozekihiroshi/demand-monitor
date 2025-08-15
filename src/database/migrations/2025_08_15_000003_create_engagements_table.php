<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('engagements', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete()->restrictOnUpdate();
            $table->string('role', 64); // org-admin / company-viewer 等
            $table->enum('status', ['active','suspended','ended'])->default('active');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('contract_ref', 64)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id','status','effective_from']);
            $table->index(['company_id','status','effective_from']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('engagements');
    }
};