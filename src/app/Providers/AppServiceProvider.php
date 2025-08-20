<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // 他に独自の bind/singleton があればここに残してください。
        // ※ 時系列(MeterTimeseriesRepository)のバインドは DemandServiceProvider に集約しました。
    }

    public function boot(): void
    {
        //
    }
}

