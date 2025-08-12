<?php
// app/Http/Controllers/Api/V1/SeriesController.php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\LegacyUser;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SeriesController extends Controller
{
    /** rate と 目標値（閾値） */
    private function meterParams(string $code, Request $req): array
    {
        $u    = LegacyUser::find($code);
        $rate = (float) ($req->query('rate') ?? $u?->rate ?? 0); // 必須想定だが保険で0
        if ($rate < 0) {
            $rate = 0;
        }

        $goal = (int) ($req->query('goal') ?? $u?->shikiichi ?? 800);
        return [$rate, $goal];
    }

    /** 1分パルス → 瞬時kW（旧 w() 相当） */
    private function kwFromMinutePulses(float $pulses, float $rate): float
    {
        return round(($pulses * $rate * 60.0) / 50000.0, 3);
    }

    /** 積算パルス（30分エネルギー想定）→ kW（旧 r() 相当） */
    private function kwFromCumPulses(float $cumPulses, float $rate): float
    {
        return round(($cumPulses * $rate * 2.0) / 50000.0, 3);
    }

    // 1) 当日＋過去N日のオーバーレイ（30m or 1m）
    public function series(Request $req, string $code)
    {
        [$rate, $goal] = $this->meterParams($code, $req);
        $bucket        = $req->query('bucket', '30m'); // '30m' or '1m'
        $days          = max(1, min(14, (int) $req->query('days', 8)));
        $offset        = max(0, (int) $req->query('offset', 0));

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
                // 30分窓の終了時刻ラベル（:30 / :00）
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
                // 1分の終了時刻ラベル（…:01 の生秒を “分終端” に揃える）
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

        return response()->json([
            'meter'   => $code,
            'bucket'  => $bucket,
            'days'    => $days,
            'offset'  => $offset,
            'rate'    => $rate,
            'goal_kw' => $goal,
            'tz'      => 'Asia/Tokyo',
            'series'  => $out,
        ]);
    }

    // 2) 当日リアルタイム（瞬間・積算・予測）
    public function demand(Request $req, string $code)
    {
        $u    = LegacyUser::find($code);
        $rate = (float) ($req->query('rate') ?? $u?->rate ?? 0);
        if ($rate < 0) {
            $rate = 0;
        }

        $threshold = (int) ($req->query('threshold') ?? 800);

        $db = DB::connection('legacy');

        // 現在の30分枠（JST）— [segStart, segEnd)
        $nowJst      = Carbon::now('Asia/Tokyo');
        $segStartJst = $nowJst->copy()->minute($nowJst->minute < 30 ? 0 : 30)->second(0);
        $segEndJst   = $segStartJst->copy()->addMinutes(30);

        $segStartUtc = $segStartJst->copy()->utc()->timestamp;
        $segEndUtc   = $segEndJst->copy()->utc()->timestamp;
        $nowUtcTs    = Carbon::now('UTC')->timestamp;

        // 30分枠内の生データを“分終端”で取得
        $rows = $db->table('demand')
            ->selectRaw('(FLOOR((`date`-1)/60)*60 + 60) AS t_end, `data`')
            ->where('demand_ip', $code)
            ->whereBetween('date', [$segStartUtc, min($nowUtcTs, $segEndUtc)])
            ->where('delete_flag', 0)
            ->orderBy('t_end')->get();

        // 実績（瞬間/積算）
        $seriesInstant = [];
        $seriesAccum   = [];
        $cum           = 0;

// 0スタート用アンカー（積算のみ）
        $segStartIso   = $segStartJst->toIso8601String();
        $seriesAccum[] = [$segStartIso, 0.0];
        foreach ($rows as $r) {
            $cum += (int) $r->data;
            $tsIso           = Carbon::createFromTimestampUTC((int) $r->t_end)->setTimezone('Asia/Tokyo')->toIso8601String();
            $seriesInstant[] = [$tsIso, $this->kwFromMinutePulses((float) $r->data, $rate)];
            $seriesAccum[]   = [$tsIso, $this->kwFromCumPulses((float) $cum, $rate)];
        }

        // 単回帰（枠内のみ、t_end/累積パルス）
        $xs      = [];
        $ys      = [];
        $running = 0;
        foreach ($rows as $r) {
            $running += (int) $r->data;
            $xs[] = (int) $r->t_end;
            $ys[] = (int) $running;
        }

        $a = 0.0;
        $b = 0.0;
        $n = count($xs);
        if ($n > 1) {
            $sx  = 0;
            $sy  = 0;
            $sxx = 0;
            $sxy = 0;
            for ($i = 0; $i < $n; $i++) {$sx += $xs[$i];
                $sy += $ys[$i];
                $sxx += $xs[$i] * $xs[$i];
                $sxy += $xs[$i] * $ys[$i];}
            $den = $n * $sxx - $sx * $sx;
            if ($den != 0) {$a = ($n * $sxy - $sx * $sy) / $den;
                $b                            = ($sy - $a * $sx) / $n;}
        }

        // 予測（最後の実績分終端から枠終端まで、1分刻み・分終端ラベル）
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

        return response()->json([
            'meter'                 => $code,
            'threshold'             => $threshold,
            'tz'                    => 'Asia/Tokyo',
            'window'                => [
                'start' => $segStartJst->toIso8601String(),
                'end'   => $segEndJst->toIso8601String(),
            ],
            'series'                => [
                'instant'      => $seriesInstant, // フロントでは visible:false 推奨
                'accumulation' => $seriesAccum,   // 緑の太線
                'predict'      => $predict,       // 赤の点線
            ],
            'predict_last'          => $predictLast,
            'will_exceed_threshold' => $willExceed,
            'max_point'             => $maxPoint,
        ]);
    }
}
