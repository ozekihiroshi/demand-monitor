<?php
namespace App\Infra\Sql;

use App\Domain\Timeseries\MeterTimeseriesRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SqlMeterTimeseriesRepository implements MeterTimeseriesRepository
{
    public function __construct(private readonly int $ttl = 30) {}

    /** 30分/1分オーバーレイ */
    public function getSeries(
        string $code,
        string $bucket = '30m',
        int $days = 8,
        int $offset = 0,
        array $opts = []
    ): array {
        // 登録主義：未登録なら 404
        $meta = $this->ensureRegistered($code);

        // パラメータ決定（opts > meters override > 旧DB）
        [$rate, $goal] = $this->resolveParams($code, $opts, mode: 'series');

        // キャッシュキー（クエリを含める）
        $key = sprintf(
            'series:%s:%s:d%d:o%d:r%s:g%s',
            $code, $bucket, $days, $offset,
            array_key_exists('rate', $opts) ? $opts['rate'] : '-',
            array_key_exists('goal', $opts) ? $opts['goal'] : '-'
        );

        return Cache::remember($key, $this->ttl(), function () use ($code, $bucket, $days, $offset, $rate, $goal) {
            $db      = DB::connection('legacy');
            $baseJst = Carbon::now('Asia/Tokyo')->startOfDay()->subDays($offset);
            $colors  = ['#FF6347', '#1E90FF', '#00FF7F', '#6B8E23', '#FFD700', '#FF3399', '#EE82EE', '#00CC00'];
            $out     = [];

            for ($i = 0; $i < $days; $i++) {
                $dayStartJst = $baseJst->copy()->subDays($i);
                $from        = $dayStartJst->copy()->utc()->timestamp;
                $to          = $dayStartJst->copy()->addDay()->utc()->timestamp;

                $points = [];
                if ($bucket === '30m') {
                    // 30分窓の終了時刻（:30 / :00）
                    $rows = $db->table('demand')
                        ->selectRaw('(FLOOR((`date`-1)/1800)*1800 + 1800) AS t_end, AVG(`data`) AS pulses_avg')
                        ->where('demand_ip', $code)
                        ->whereBetween('date', [$from, $to])
                        ->where('delete_flag', 0)
                        ->groupBy('t_end')->orderBy('t_end')->get();

                    foreach ($rows as $r) {
                        $kw       = $this->kwFromMinutePulses((float) $r->pulses_avg, $rate);
                        $ts       = Carbon::createFromTimestampUTC((int) $r->t_end)->setTimezone('Asia/Tokyo')->toIso8601String();
                        $points[] = [$ts, $kw];
                    }
                } else {
                    // 1分窓の終了時刻
                    $rows = $db->table('demand')
                        ->selectRaw('(FLOOR((`date`-1)/60)*60 + 60) AS t_end, `data` AS pulses')
                        ->where('demand_ip', $code)
                        ->whereBetween('date', [$from, $to])
                        ->where('delete_flag', 0)
                        ->orderBy('t_end')->get();

                    foreach ($rows as $r) {
                        $kw       = $this->kwFromMinutePulses((float) $r->pulses, $rate);
                        $ts       = Carbon::createFromTimestampUTC((int) $r->t_end)->setTimezone('Asia/Tokyo')->toIso8601String();
                        $points[] = [$ts, $kw];
                    }
                }

                $labelDay = $dayStartJst->format('m/d');
                $visible  = ($i === 0 || $i % 7 === 0);

                $maxPoint  = null;
                $firstOver = null;
                foreach ($points as $p) {
                    if ($maxPoint === null || $p[1] > $maxPoint[1]) {
                        $maxPoint = $p;
                    }
                    if ($firstOver === null && $p[1] >= $goal) {
                        $firstOver = $p;
                    }
                }

                $out[] = [
                    'label'      => $i === 0 ? "当日[$labelDay]" : "[$labelDay]",
                    'visible'    => $visible,
                    'color'      => $colors[$i % count($colors)],
                    'max_point'  => $maxPoint,
                    'over_goal'  => $firstOver !== null,
                    'first_over' => $firstOver,
                    'data'       => $points,
                ];
            }

            return [
                'meter'   => $code,
                'bucket'  => $bucket,
                'days'    => $days,
                'offset'  => $offset,
                'rate'    => $rate,
                'goal_kw' => $goal,
                'tz'      => 'Asia/Tokyo',
                'series'  => $out,
            ];
        });
    }

    /** 当日 瞬時/積算/線形予測 */
    public function getDemandToday(string $code, array $opts = []): array
    {
        // 登録主義
        $meta = $this->ensureRegistered($code);

        // パラメータ決定（opts > meters override > 旧DB）
        [$rate, $threshold] = $this->resolveParams($code, $opts, mode: 'demand');

        $key = sprintf(
            'demand:%s:r%s:t%s',
            $code,
            array_key_exists('rate', $opts) ? $opts['rate'] : '-',
            array_key_exists('threshold', $opts) ? $opts['threshold'] : '-'
        );

        return Cache::remember($key, $this->ttl(), function () use ($code, $rate, $threshold) {
            $db = DB::connection('legacy');

            // 現在の30分枠（JST）
            $nowJst      = Carbon::now('Asia/Tokyo');
            $segStartJst = $nowJst->copy()->minute($nowJst->minute < 30 ? 0 : 30)->second(0);
            $segEndJst   = $segStartJst->copy()->addMinutes(30);

            $segStartUtc = $segStartJst->copy()->utc()->timestamp;
            $segEndUtc   = $segEndJst->copy()->utc()->timestamp;
            $nowUtcTs    = Carbon::now('UTC')->timestamp;

            // 30分枠内の実績（分終端）
            $rows = $db->table('demand')
                ->selectRaw('(FLOOR((`date`-1)/60)*60 + 60) AS t_end, `data`')
                ->where('demand_ip', $code)
                ->whereBetween('date', [$segStartUtc, min($nowUtcTs, $segEndUtc)])
                ->where('delete_flag', 0)
                ->orderBy('t_end')->get();

            $seriesInstant = [];
            $seriesAccum   = [];
            $cum           = 0;

            // アンカー（積算0スタート）
            $segStartIso   = $segStartJst->toIso8601String();
            $seriesAccum[] = [$segStartIso, 0.0];

            foreach ($rows as $r) {
                $cum += (int) $r->data;
                $tsIso           = Carbon::createFromTimestampUTC((int) $r->t_end)->setTimezone('Asia/Tokyo')->toIso8601String();
                $seriesInstant[] = [$tsIso, $this->kwFromMinutePulses((float) $r->data, $rate)];
                $seriesAccum[]   = [$tsIso, $this->kwFromCumPulses((float) $cum, $rate)];
            }

            // 単回帰（t_end vs 累積パルス）
            $xs = []; $ys = []; $running = 0;
            foreach ($rows as $r) {
                $running += (int) $r->data;
                $xs[] = (int) $r->t_end;
                $ys[] = (int) $running;
            }

            $a = 0.0; $b = 0.0; $n = count($xs);
            if ($n > 1) {
                $sx = 0; $sy = 0; $sxx = 0; $sxy = 0;
                for ($i = 0; $i < $n; $i++) {
                    $sx  += $xs[$i];
                    $sy  += $ys[$i];
                    $sxx += $xs[$i] * $xs[$i];
                    $sxy += $xs[$i] * $ys[$i];
                }
                $den = $n * $sxx - $sx * $sx;
                if ($den != 0) {
                    $a = ($n * $sxy - $sx * $sy) / $den;
                    $b = ($sy - $a * $sx) / $n;
                }
            }

            // 予測（最後の実績分終端から枠終端まで1分刻み）
            $predict  = [];
            $maxPoint = null;
            if ($n > 1) {
                $t = max($xs[$n - 1], $segStartUtc);
                while ($t <= $segEndUtc) {
                    $y         = $a * $t + $b;
                    $kw        = $this->kwFromCumPulses((float) $y, $rate);
                    $ts        = Carbon::createFromTimestampUTC($t)->setTimezone('Asia/Tokyo')->toIso8601String();
                    $pt        = [$ts, $kw];
                    $predict[] = $pt;
                    if (! $maxPoint || $pt[1] > $maxPoint[1]) {
                        $maxPoint = $pt;
                    }
                    $t += 60;
                }
            }

            $predictLast = $predict ? $predict[count($predict) - 1] : null;
            $willExceed  = $predictLast ? ($predictLast[1] >= $threshold) : false;

            return [
                'meter'                 => $code,
                'threshold'             => $threshold,
                'tz'                    => 'Asia/Tokyo',
                'window'                => [
                    'start' => $segStartJst->toIso8601String(),
                    'end'   => $segEndJst->toIso8601String(),
                ],
                'series'                => [
                    'instant'      => $seriesInstant,
                    'accumulation' => $seriesAccum,
                    'predict'      => $predict,
                ],
                'predict_last'          => $predictLast,
                'will_exceed_threshold' => $willExceed,
                'max_point'             => $maxPoint,
            ];
        });
    }

    /** ---- helpers ---- */

    /** 新DBにメーターが登録されていることを保証（未登録は404） */
    private function ensureRegistered(string $code): object
    {
        $row = DB::table('meters')
            ->where('code', $code)
            ->first(['legacy_uid', 'rate_override', 'threshold_override']);

        if (! $row) {
            abort(404, 'meter not registered');
        }
        return $row;
    }

    /**
     * rate/goal(threshold) の決定
     * mode: 'series' => goal, 'demand' => threshold
     */
    private function resolveParams(string $code, array $opts = [], string $mode = 'series'): array
    {
        // 新DB override
        $over = DB::table('meters')->where('code', $code)
            ->first(['rate_override', 'threshold_override']);

        // 旧DB fallback
        $u = DB::connection('legacy')->table('user')->where('uid', $code)
            ->first(['rate', 'shikiichi']);

        // rate
        $rate = isset($opts['rate']) ? (float) $opts['rate'] :
               ((float) ($over->rate_override ?? $u->rate ?? 0));
        if ($rate < 0) $rate = 0;

        if ($mode === 'demand') {
            $threshold = isset($opts['threshold']) ? (int) $opts['threshold'] :
                        ((int) ($over->threshold_override ?? $u->shikiichi ?? 800));
            return [$rate, $threshold];
        } else {
            $goal = isset($opts['goal']) ? (int) $opts['goal'] :
                   ((int) ($over->threshold_override ?? $u->shikiichi ?? 800));
            return [$rate, $goal];
        }
    }

    /** 1分パルス→瞬時kW */
    private function kwFromMinutePulses(float $pulses, float $rate): float
    {
        return round(($pulses * $rate * 60.0) / 50000.0, 3);
    }

    /** 30分累積パルス→kW */
    private function kwFromCumPulses(float $cumPulses, float $rate): float
    {
        return round(($cumPulses * $rate * 2.0) / 50000.0, 3);
    }

    private function ttl(): int
    {
        return (int) config('demand.cache_ttl', $this->ttl);
    }
}
