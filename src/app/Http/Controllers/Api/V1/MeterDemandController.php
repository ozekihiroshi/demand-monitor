<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Meter;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MeterDemandController extends Controller
{
    /**
     * GET /api/v1/meters/{code}/demand
     * 当日の現在30分枠について：
     *  - 瞬時（instant）
     *  - 積算（accumulation, 0起点の右肩上がり）
     *  - 予測（predict, 実績の終点から枠終端まで）
     * を毎回フル返却します。
     */
    public function demand(Request $req, string $code)
    {
        $tz  = 'Asia/Tokyo';
        $now = Carbon::now($tz)->second(0)->millisecond(0);

        // 現在のJST 30分枠 [slotStart, slotEnd)
        $slotStart = $now->copy();
        $slotStart->minute($slotStart->minute < 30 ? 0 : 30)->second(0)->millisecond(0);
        $slotEnd = $slotStart->copy()->addMinutes(30);

        // メーター（あれば利用：しきい値など）。無くても動作可。
        $meter = Meter::where('code', $code)->orWhere('demand_ip', $code)->first();

        // しきい値（?threshold > meter既定 > null）
        $threshold = $req->filled('threshold')
            ? (float) $req->query('threshold')
            : ($meter->effective_threshold ?? null);

        // demand 検索キー（メーター不在時は code を demand_ip とみなす）
        $key = $meter?->demand_ip ?? trim($code);

        // 既定接続（＝ demand_monitor）で demand テーブルを参照
        $rows = DB::table('demand')
            ->select(['date','data'])
            ->where('demand_ip', $key)
            ->where('delete_flag', 0)
            ->where('date', '>=', $slotStart->timestamp)
            ->where('date', '<',  $slotEnd->timestamp + 2) // 端点誤差に2秒だけ余裕
            ->orderBy('date')
            ->get();

        $toIso = fn(int $sec) => Carbon::createFromTimestamp($sec, $tz)->toIso8601String();

        // 瞬時
        $instant = [];
        foreach ($rows as $r) {
            $instant[] = [$toIso((int)$r->date), (float)$r->data];
        }

        // データ無し：枠情報だけ返す
        if (empty($instant)) {
            return response()->json([
                'meter'        => $meter->code ?? $code,
                'meter_name'   => $meter->name ?? $code,
                'demand_ip'    => $key,
                'tz'           => $tz,
                'now'          => $now->toIso8601String(),
                'window'       => ['start'=>$slotStart->toIso8601String(), 'end'=>$slotEnd->toIso8601String()],
                'threshold'    => $threshold,
                'cadence_sec'  => 60,
                'series'       => ['instant'=>[], 'accumulation'=>[], 'predict'=>[]],
                'predict_last' => null,
            ])->header('Cache-Control', 'no-store');
        }

        // サンプル周期（中央値）
        $secs = array_map(fn($p) => Carbon::parse($p[0], $tz)->timestamp, $instant);
        $diffs = [];
        for ($i = 1; $i < count($secs); $i++) $diffs[] = $secs[$i] - $secs[$i-1];
        sort($diffs);
        $cadence = (!empty($diffs) ? max(1, (int) round($diffs[(int) floor(count($diffs)/2)])) : 60);

        // 積算（台形法, 0起点→30分平均kW換算で右肩上がり）
        $accum   = [[ $slotStart->toIso8601String(), 0.0 ]];
        $prevT   = $slotStart->timestamp;
        $prevKW  = (float) ($rows[0]->data ?? 0);
        $cumKWh  = 0.0;

        foreach ($rows as $r) {
            $t  = (int) $r->date;
            $kw = (float) $r->data;
            $dt = max(0, $t - $prevT);
            $cumKWh += (($prevKW + $kw) / 2) * ($dt / 3600.0); // 台形法
            $accum[] = [ $toIso($t), round(2 * $cumKWh, 1) ];  // 30分平均kW換算
            $prevT   = $t;
            $prevKW  = $kw;
        }

        // 予測（last → anchor → end で滑らかに継ぐ）
        $lastTs        = end($secs);
        $demandAtLast  = end($accum)[1];
        $nextSample    = $lastTs + $cadence;
        $anchor        = min($now->timestamp, $nextSample, $slotEnd->timestamp);

        $pred = [];
        $pred[] = [ $toIso($lastTs), $demandAtLast ];

        $demandAtAnchor = $demandAtLast;
        if ($anchor > $lastTs) {
            $demandAtAnchor = round($demandAtLast + ($prevKW * (($anchor - $lastTs) / 1800.0)), 1);
            if ($anchor < $slotEnd->timestamp) {
                $pred[] = [ $toIso($anchor), $demandAtAnchor ];
            }
        }

        $demandAtEnd = round($demandAtAnchor + ($prevKW * (($slotEnd->timestamp - max($anchor, $lastTs)) / 1800.0)), 1);
        $pred[] = [ $slotEnd->toIso8601String(), $demandAtEnd ];

        return response()->json([
            'meter'        => $meter->code ?? $code,
            'meter_name'   => $meter->name ?? $code,
            'demand_ip'    => $key,
            'tz'           => $tz,
            'now'          => $now->toIso8601String(),
            'window'       => ['start'=>$slotStart->toIso8601String(), 'end'=>$slotEnd->toIso8601String()],
            'threshold'    => $threshold,
            'cadence_sec'  => $cadence,
            'series'       => [
                'instant'      => $instant,
                'accumulation' => $accum,
                'predict'      => $pred,
            ],
            'predict_last' => [ $slotEnd->toIso8601String(), $demandAtEnd ],
        ])->header('Cache-Control', 'no-store');
    }
}
