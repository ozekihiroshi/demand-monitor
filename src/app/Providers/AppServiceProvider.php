<?php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL; // ← これだけ
// use Illuminate\Support\Facades\Vite; は Provider では使わない

use App\Domain\Timeseries\MeterTimeseriesRepository;
use App\Domain\Timeseries\SwitchingMeterTimeseriesRepository;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Repository をここで 1本に束ねる（管理UIからも公開グラフからも同じ経路になる）
        $this->app->bind(MeterTimeseriesRepository::class, SwitchingMeterTimeseriesRepository::class);
    }

    public function boot(): void
    {
        // 逆プロキシ（traefik）配下で https を強制
        if (str_starts_with(config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }
    }
}
