<?php
namespace App\Domain\Timeseries;

interface MeterTimeseriesRepository
{
    /** 30分/1分オーバーレイ用 */
    public function getSeries(string $code, string $bucket = '30m', int $days = 8, int $offset = 0, array $opts = []): array;
    /** 当日 瞬時/積算/線形予測 用 */
    public function getDemandToday(string $code, array $opts = []): array;
}


