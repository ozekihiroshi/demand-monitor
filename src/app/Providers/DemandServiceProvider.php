<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Domain\Timeseries\MeterTimeseriesRepository;
use App\Infra\Sql\SqlMeterTimeseriesRepository;

class DemandServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Repository の実装を現行DB版（Sql）に固定
        $this->app->singleton(MeterTimeseriesRepository::class, function ($app) {
            return new SqlMeterTimeseriesRepository(config(demand.cache_ttl, 30));
        });
    }

    public function boot(): void
    {
        //
    }
}
