<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if (!Schema::hasTable('meters')) {
            // 新規作成（SQLite でも安全）
            Schema::create('meters', function (Blueprint $table) use ($driver) {
                $table->id();
                $table->string('code'); // 一旦 NOT NULL 指定はしない（後で unique 付与）
                $table->unsignedBigInteger('legacy_uid')->nullable()->index();
                $table->string('name')->nullable();

                if ($driver === 'sqlite') {
                    $table->unsignedBigInteger('group_id')->nullable(); // SQLite は後付けFKが苦手
                } else {
                    $table->foreignId('group_id')->nullable()->constrained()->nullOnDelete();
                }

                $table->decimal('rate_override', 10, 4)->nullable();
                $table->decimal('threshold_override', 10, 4)->nullable();
                $table->timestamps();
            });

            // code のユニーク制約は別建て（try/catchで安全に）
            try {
                Schema::table('meters', function (Blueprint $table) {
                    $table->unique('code', 'meters_code_unique');
                });
            } catch (\Throwable $e) { /* ignore for sqlite */ }

        } else {
            // 既存テーブルがある場合は“追加のみ”（SQLite で MODIFY/CHANGE を回避）
            Schema::table('meters', function (Blueprint $table) use ($driver) {
                if (!Schema::hasColumn('meters','code')) {
                    $table->string('code')->nullable()->after('id');
                }
                if (!Schema::hasColumn('meters','legacy_uid')) {
                    $table->unsignedBigInteger('legacy_uid')->nullable()->index();
                }
                if (!Schema::hasColumn('meters','name')) {
                    $table->string('name')->nullable();
                }
                if (!Schema::hasColumn('meters','group_id')) {
                    if ($driver === 'sqlite') {
                        $table->unsignedBigInteger('group_id')->nullable();
                    } else {
                        $table->foreignId('group_id')->nullable()->constrained()->nullOnDelete();
                    }
                }
                if (!Schema::hasColumn('meters','rate_override')) {
                    $table->decimal('rate_override', 10, 4)->nullable();
                }
                if (!Schema::hasColumn('meters','threshold_override')) {
                    $table->decimal('threshold_override', 10, 4)->nullable();
                }
            });

            // ユニーク制約（存在しなければ追加）
            try {
                $hasUnique = false;
                if ($driver !== 'sqlite') {
                    // MySQL 等：information_schema から確認しても良いがコスト高なので try/catch に寄せる
                }
                if (!$hasUnique) {
                    Schema::table('meters', function (Blueprint $table) {
                        $table->unique('code', 'meters_code_unique');
                    });
                }
            } catch (\Throwable $e) { /* 既に存在 or sqlite で無視 */ }
        }
    }

    public function down(): void
    {
        // 破壊的 rollback は避ける（他マイグレーションとの整合優先）
        if (Schema::hasTable('meters')) {
            try {
                Schema::table('meters', function (Blueprint $table) {
                    // SQLite でも一応 try/catch
                    $table->dropUnique('meters_code_unique');
                });
            } catch (\Throwable $e) { /* ignore */ }
            // テーブル自体は他マイグレーションと衝突しうるので、ここでは drop しない
        }
    }
};
