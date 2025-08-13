<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('group_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->nullable(); // org-admin / operator / viewer（将来teams移行可）
            $table->timestamps();
            $table->unique(['group_id','user_id']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('group_user');
    }
};
