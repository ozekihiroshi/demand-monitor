<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Meter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SeriesController extends Controller
{
    /**
     * GET /api/v1/meters/{code}/series
     *   bucket=30m|1m   （既定 30m）
     *   days=1..31      （既定 8）
     *   goal=number     （任意）
     *   view=overlay|timeline （1mのみ拡張。既定 overlay）
     *   since=epochSec  （任意・差分。1m限定。overlay/timeline 両対応）
     *
     * 差分応答（sinceあり）:
     *  { mode:'delta', view:'overlay'|'timeline', target:'YYYY-MM-DD'|'timeline',
     *    tz, since, cursor, append:[[iso, val|null],...] }
     */
    public function series(Request $req, string $code)
    {
        $tz     = 'Asia/Tokyo';
        $bucket = strtolower($req->query('bucket', '30m'));
        if (!in_array($bucket, ['30m','1m'], true)) $bucket = '30m';

        $days   = max(1, min(31, (int)$req->query('days', 8)));
        $goal   = $req->has('goal') ? (float)$req->query('goal') : null;
        $view   = strtolower($req->query('view', 'overlay'));
        if (!in_array($view, ['overlay','timeline'], true)) $view = 'overlay';

        // メーター（無くてもOK。codeをdemand_ip扱い）
        $meter = Meter::where('code',$code)->orWhere('demand_ip',$code)->first();
        $key   = $meter?->demand_ip ?? trim($code);

        /* ===================== 差分モード（1mのみ） ===================== */
        if ($bucket === '1m' && $req->filled('since')) {
            $sinceSec = max(0, (int)$req->query('since'));
            $now      = Carbon::now($tz);
            $cursor   = $now->copy()->startOfMinute(); // 応答の終端

            // 置換開始点：sinceの分頭 - 1分（重複1分で平均の再計算を許す）
            $sinceMin = Carbon::createFromTimestamp($sinceSec, $tz)->startOfMinute()->subMinute();

            // 表示レンジ（timeline=7日連続 / overlay=当日）
            if ($view === 'timeline') {
                $spanStart = $now->copy()->startOfDay()->subDays(max(0, $days-1));
            } else { // overlay
                $spanStart = $now->copy()->startOfDay();
            }
            if ($sinceMin->lt($spanStart)) $sinceMin = $spanStart->copy();

            // 取得
            $rows = DB::table('demand')
                ->select(['date','data'])
                ->where('demand_ip', $key)
                ->where('delete_flag', 0)
                ->whereBetween('date', [$sinceMin->timestamp, $cursor->timestamp])
                ->orderBy('date')
                ->get()
                ->map(fn($r)=>['t'=>(int)$r->date,'kw'=>(float)$r->data])
                ->all();

            // 差分の再サンプリング（1分粒度）
            $append = $this->resample1m_range($sinceMin, $cursor, $rows, $tz);

            return response()->json([
                'mode'   => 'delta',
                'view'   => $view,
                'target' => ($view === 'timeline') ? 'timeline' : $now->format('Y-m-d'),
                'tz'     => $tz,
                'since'  => $sinceMin->toIso8601String(),
                'cursor' => $cursor->toIso8601String(),
                'append' => $append,
            ])->header('Cache-Control', 'no-store');
        }
        /* ================================================================= */

        /* ======================= フルモード ============================== */
        $today = Carbon::now($tz)->startOfDay();
        $start = $today->copy()->subDays($days - 1)->startOfDay();
        $end   = $today->copy()->endOfDay();

        // 30分は境界補間あり → ±1h 余白。1分は不要だが揃えても軽量。
        $padBefore = 3600; $padAfter = 3600;

        $rows = DB::table('demand')
            ->select(['date','data'])
            ->where('demand_ip', $key)
            ->where('delete_flag', 0)
            ->whereBetween('date', [$start->timestamp - $padBefore, $end->timestamp + $padAfter])
            ->orderBy('date')
            ->get()
            ->map(fn($r)=>['t'=>(int)$r->date,'kw'=>(float)$r->data])
            ->all();

        $palette = ['#4F81BD','#C0504D','#9BBB59','#8064A2','#4BACC6','#F79646','#2E8B57','#C0392B','#7F8C8D','#2980B9','#8E44AD','#16A085'];
        $series  = [];

        if ($bucket === '1m' && $view === 'timeline') {
            // 7日連続の単一系列（または days 指定レンジ）
            $data = $this->resample1m_span($start, $end, $rows, $tz);
            $series[] = [
                'label'   => $start->format('Y-m-d') . ' .. ' . $end->format('Y-m-d'),
                'data'    => $data,
                'color'   => '#2980B9',
                'visible' => true,
            ];
        } else {
            // 従来：日別オーバーレイ（1m/30mとも）
            // 日ごとに分配（余白込み）
            $byDay = [];
            for ($i=0; $i<$days; $i++) {
                $dStart = $start->copy()->addDays($i)->startOfDay()->timestamp - $padBefore;
                $dEnd   = $start->copy()->addDays($i)->endOfDay()->timestamp   + $padAfter;
                $byDay[$i] = array_values(array_filter($rows, fn($x)=>$x['t'] >= $dStart && $x['t'] <= $dEnd));
            }

            for ($i=0; $i<$days; $i++) {
                $day = $start->copy()->addDays($i);
                if ($bucket === '1m') {
                    $data = $this->resample1m($day, $byDay[$i], $tz);
                } else { // 30m
                    $data = $this->aggregate30m_trapezoid($day, $byDay[$i], $tz);
                }
                $series[] = [
                    'label'   => $day->format('Y-m-d'),
                    'data'    => $data,
                    'color'   => $palette[$i % count($palette)],
                    'visible' => $i >= $days - 2,
                ];
            }
        }

        return response()->json([
            'meter'      => $meter->code ?? $code,
            'meter_name' => $meter->name ?? $code,
            'bucket'     => $bucket,
            'view'       => $view,
            'tz'         => $tz,
            'goal_kw'    => $goal,
            'series'     => $series,
        ])->header('Cache-Control','no-store');
    }

    /* ===== 1分（当日フル） ===== */
    private function resample1m(Carbon $day, array $points, string $tz): array
    {
        usort($points, fn($a,$b)=>$a['t']<=>$b['t']);
        $start = $day->copy()->startOfDay()->timestamp;
        $end   = $day->copy()->endOfDay()->timestamp;
        $iso   = fn(int $sec)=>Carbon::createFromTimestamp($sec,$tz)->toIso8601String();

        $b = [];
        foreach ($points as $p) {
            if ($p['t'] < $start || $p['t'] > $end) continue;
            $m = $p['t'] - ($p['t'] % 60);
            if (!isset($b[$m])) $b[$m] = [0.0, 0];
            $b[$m][0] += $p['kw']; $b[$m][1] += 1;
        }
        $out=[];
        for ($m=$start; $m<=$end; $m+=60) {
            $out[] = isset($b[$m])
                ? [$iso($m), round($b[$m][0]/max(1,$b[$m][1]), 2)]
                : [$iso($m), null];
        }
        return $out;
    }

    /* ===== 1分（任意範囲：差分用） ===== */
    private function resample1m_range(Carbon $sinceMin, Carbon $endMin, array $points, string $tz): array
    {
        usort($points, fn($a,$b)=>$a['t']<=>$b['t']);
        $iso = fn(int $sec)=>Carbon::createFromTimestamp($sec,$tz)->toIso8601String();

        $b=[];
        foreach ($points as $p) {
            if ($p['t'] < $sinceMin->timestamp || $p['t'] > $endMin->timestamp) continue;
            $m = $p['t'] - ($p['t'] % 60);
            if (!isset($b[$m])) $b[$m] = [0.0, 0];
            $b[$m][0] += $p['kw']; $b[$m][1] += 1;
        }

        $out=[];
        for ($m=$sinceMin->timestamp; $m<=$endMin->timestamp; $m+=60) {
            $out[] = isset($b[$m])
                ? [$iso($m), round($b[$m][0]/max(1,$b[$m][1]), 2)]
                : [$iso($m), null];
        }
        return $out;
    }

    /* ===== 1分（連続スパン：7日連続表示用） ===== */
    private function resample1m_span(Carbon $start, Carbon $end, array $points, string $tz): array
    {
        usort($points, fn($a,$b)=>$a['t']<=>$b['t']);
        $iso = fn(int $sec)=>Carbon::createFromTimestamp($sec,$tz)->toIso8601String();

        $b=[];
        foreach ($points as $p) {
            if ($p['t'] < $start->timestamp || $p['t'] > $end->timestamp) continue;
            $m = $p['t'] - ($p['t'] % 60);
            if (!isset($b[$m])) $b[$m] = [0.0, 0];
            $b[$m][0] += $p['kw']; $b[$m][1] += 1;
        }

        $out=[];
        for ($m=$start->timestamp; $m<=$end->timestamp; $m+=60) {
            $out[] = isset($b[$m])
                ? [$iso($m), round($b[$m][0]/max(1,$b[$m][1]), 2)]
                : [$iso($m), null];
        }
        return $out;
    }

    /* ===== 30分（台形法＋境界補間：従来どおり） ===== */
    private function aggregate30m_trapezoid(Carbon $day, array $points, string $tz): array
    {
        usort($points, fn($a,$b)=>$a['t']<=>$b['t']);
        $n = count($points);
        $d0 = $day->copy()->startOfDay()->timestamp;
        $d1 = $day->copy()->endOfDay()->timestamp + 1;
        $iso = fn(int $sec)=>Carbon::createFromTimestamp($sec,$tz)->toIso8601String();

        $ends=[]; for ($e=$d0+1800; $e<=$d1; $e+=1800) $ends[]=$e;

        $interp = function(int $t) use ($points,$n): ?float {
            if ($n===0) return null;
            if ($t < $points[0]['t'] || $t > $points[$n-1]['t']) return null;
            $prev=null;
            foreach ($points as $p) {
                if ($p['t']===$t) return (float)$p['kw'];
                if ($p['t']>$t) {
                    if ($prev===null) return null;
                    $dt=$p['t']-$prev['t']; if ($dt<=0) return null;
                    $ratio=($t-$prev['t'])/$dt;
                    return (float)($prev['kw']+($p['kw']-$prev['kw'])*$ratio);
                }
                $prev=$p;
            }
            return null;
        };

        $out=[];
        foreach ($ends as $e) {
            $s=$e-1800;
            $vS=$interp($s); $vE=$interp($e);
            if ($vS===null || $vE===null) { $out[] = [$iso($e), null]; continue; }
            $inside=array_values(array_filter($points, fn($p)=>$p['t']>$s && $p['t']<$e));
            $knots=[ ['t'=>$s,'kw'=>$vS] ];
            foreach($inside as $p) $knots[]=['t'=>$p['t'],'kw'=>(float)$p['kw']];
            $knots[]=['t'=>$e,'kw'=>$vE];
            $area=0.0;
            for($i=1;$i<count($knots);$i++){
                $t0=$knots[$i-1]['t']; $v0=$knots[$i-1]['kw'];
                $t1=$knots[$i]['t'];   $v1=$knots[$i]['kw'];
                $dt=max(0,$t1-$t0)/3600.0;
                $area += (($v0+$v1)/2.0) * $dt;
            }
            $out[] = [$iso($e), round($area*2.0, 2)];
        }
        return $out;
    }
}
