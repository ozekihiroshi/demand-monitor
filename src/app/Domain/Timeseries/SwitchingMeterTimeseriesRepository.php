<?php
namespace App\Domain\Timeseries;

use App\Infra\Legacy\LegacyMeterTimeseriesRepository;
use Illuminate\Support\Carbon;

class SwitchingMeterTimeseriesRepository implements MeterTimeseriesRepository
{
    public function __construct(
        private readonly LegacyMeterTimeseriesRepository $legacy,
        // private readonly NewStoreMeterTimeseriesRepository $newStore, // 将来用
    ) {}

    // ★ IF と同じシグネチャに合わせる（days/offset 追加）
    public function getSeries(string $code, string $bucket = '30m', int $days = 8, int $offset = 0, array $opts = []): array
    {
        return $this->reader()->getSeries($code, $bucket, $days, $offset, $opts);
    }

    public function getDemandToday(string $code, array $opts = []): array
    {
        return $this->reader()->getDemandToday($code, $opts);
    }

    private function reader(): MeterTimeseriesRepository
    {
        $cutover = config('demand.cutover_at');
        if (! $cutover) {
            return $this->legacy;
        }

        $at = Carbon::parse($cutover);
        // 当面 legacy 固定。切替時に $this->newStore へ
        return now()->lt($at) ? $this->legacy : $this->legacy;
    }
}
