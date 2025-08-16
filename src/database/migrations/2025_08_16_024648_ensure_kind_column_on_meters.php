<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('meters', 'kind')) {
            Schema::table('meters', function (Blueprint $table) {
                $table->string('kind', 20)->default('consumption')->after('name');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('meters', 'kind')) {
            Schema::table('meters', function (Blueprint $table) {
                $table->dropColumn('kind');
            });
        }
    }
};
