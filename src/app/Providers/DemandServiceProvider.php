<?php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Domain\Timeseries\MeterTimeseriesRepository;
use App\Domain\Timeseries\SwitchingMeterTimeseriesRepository;
use App\Infra\Legacy\LegacyMeterTimeseriesRepository;

class DemandServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LegacyMeterTimeseriesRepository::class, fn($app) =>
            new LegacyMeterTimeseriesRepository(config('demand.cache_ttl', 30))
        );

        $this->app->singleton(MeterTimeseriesRepository::class, function ($app) {
            return $app->make(SwitchingMeterTimeseriesRepository::class);
        });
    }

    public function boot(): void {}
}



