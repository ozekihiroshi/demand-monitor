<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        Schema::create('companies', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('slug', 128)->unique();
            $table->string('name', 191);
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('companies');
    }
};


