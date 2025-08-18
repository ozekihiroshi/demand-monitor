<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('mem_demand', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->integer('data')->nullable();
            $t->integer('data_tmp0')->default(0);
            $t->integer('data_tmp1')->default(0);
            $t->integer('data_tmp2')->default(0);
            $t->integer('data_tmp3')->nullable();
            $t->integer('date')->index();                    // UNIX秒
            $t->string('demand_ip', 15)->index();
            $t->tinyInteger('flag')->default(0);
            $t->integer('stamp')->nullable();
            $t->integer('battery')->default(0);
            $t->integer('rssi')->default(0);
            $t->tinyInteger('data_type')->default(0);        // 0/6 を主に使用
            $t->tinyInteger('delete_flag')->default(0);
            $t->dateTime('create_date')->nullable();
            $t->integer('file_date')->nullable();
            $t->integer('time_correction')->nullable();
            $t->string('serial', 32)->nullable();

            // 旧来の INSERT IGNORE を活かすならユニークキーが必要（重複を自然に弾く）
            $t->unique(['demand_ip','date'], 'uniq_mem_minute');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mem_demand');
    }
};

