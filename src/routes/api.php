<?php
// routes/api.php

use App\Models\Meter;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('throttle:120,1')->group(function () {

    /**
     * ユーティリティ：{キー} → demand_ip を解決
     * 1) meters.code = {キー} にヒット → $meter->demand_ip ?? $meter->code
     * 2) それ以外 → {キー} を demand_ip として使用
     */
    $resolveDemandIp = function (string $key): array {
        $meter = Meter::where('code', $key)->first()
        ?: Meter::where('demand_ip', $key)->first();

        // demand_ip と「表示用メーター名」を返す
        $ip        = $meter?->demand_ip ?? $meter?->code ?? $key;
        $name      = $meter?->name ?? $key;
        $threshold = $meter?->threshold_override;

        return [$ip, $name, $threshold, $meter];
    };

    /**
     * ユーティリティ：数値・ISO文字列→UNIX秒
     */
    $parseTs = function ($v, string $tz = 'Asia/Tokyo'): int {
        if ($v === null || $v === '') {
            return 0;
        }

        if (is_numeric($v)) {
            $n = (int) $v;
            // ミリ秒なら秒に
            return $n > 1_000_000_000_000 ? intdiv($n, 1000) : $n;
        }
        return Carbon::parse((string) $v, $tz)->timestamp;
    };

    /**
     * /api/v1/meters/{key}/demand
     * - 既定：直近30分（1分粒度想定）
     * - クエリ: from, to（UNIX秒 or ISO可）
     * - 返却: series.instant = [[ISO8601, value], ...]
     */
    Route::get('meters/{key}/demand', function (Request $req, string $key) use ($resolveDemandIp) {
        $tz                               = 'Asia/Tokyo';
        [$ip, $name, $thresholdFromMeter] = $resolveDemandIp($key);

        // --- 閾値（API優先: ?threshold=xxx -> meter設定 -> null）
        $threshold = $req->has('threshold')
        ? (int) $req->query('threshold')
        : ($thresholdFromMeter ?? null);

        // === いま含む30分枠を決定（JST基準） ===
        $now       = Carbon::now($tz);
        $slotStart = $now->copy()->second(0)->millisecond(0);
        $slotStart->minute($slotStart->minute < 30 ? 0 : 30); // :00 か :30 に丸め
        $slotEnd = $slotStart->copy()->addMinutes(30);

        // 取得範囲は枠全体（実績は slotStart〜min(now, slotEnd)）
        $from = $slotStart->timestamp;
        $to   = $slotEnd->timestamp;

        // === demandテーブルから枠内データ（秒タイムスタンプ想定） ===
        $rows = DB::table('demand')
            ->select(['date', 'data'])
            ->where('demand_ip', $ip)
            ->whereBetween('date', [$from, $to])
            ->where('delete_flag', 0)
            ->orderBy('date')
            ->get();

        // ミニ関数
        $secToIso = fn(int $sec) => Carbon::createFromTimestamp($sec, $tz)->toIso8601String();

        // === 瞬間（参考表示用）: 枠内だけ返す ===
        $instant = [];
        foreach ($rows as $r) {
            $sec       = (int) $r->date;
            $instant[] = [$secToIso($sec), (float) $r->data];
        }

                                                      // === 1分グリッド化して「ゼロ開始の積算（右肩上がり）」を計算 ===
                                                      // 欠測は直近値キャリー。kW -> 1分のエネルギー[kWh]=kW/60 を積算し、30分平均kW= 2 * 累積kWh。
        $minuteStart     = $from;                     // 枠始点（秒）
        $minuteEndActual = min($now->timestamp, $to); // 実績の終点（秒）
        $minuteEndFull   = $to;                       // 枠終端（秒）
        $lastKw          = 0.0;

        // rows を時間順に舐めるポインタ
        $i      = 0; $n      = count($rows);
        $cumKWh = 0.0;
        $accum  = []; // 現時点実績（積算）
        for ($t = $minuteStart; $t <= $minuteEndActual; $t += 60) {
            // この分までに観測された最新値を反映
            while ($i < $n && (int) $rows[$i]->date <= $t) {
                $lastKw = (float) $rows[$i]->data;
                $i++;
            }
            // 1分ぶんのエネルギーを積算
            $cumKWh += $lastKw / 60.0;
            // 30分平均kW換算（ゼロ開始で右肩上がり）
            $demandKwEq = 2.0 * $cumKWh;
            $accum[]    = [$secToIso($t), round($demandKwEq, 1)];
        }

        // === 予測：現在の瞬時値で残りを埋めて枠終端まで ===
        $predict     = [];
        $predictLast = null;
        if ($minuteEndActual < $minuteEndFull) {
            $remainMinutes = (int) floor(($minuteEndFull - $minuteEndActual) / 60);
            for ($m = 1; $m <= $remainMinutes; $m++) {
                $t = $minuteEndActual + 60 * $m;
                // 1分進むごとに 2*(lastKw/60)= lastKw/30 を加算
                $demandKwEq = (count($accum) ? (float) end($accum)[1] : 0.0) + ($lastKw / 30.0) * $m;
                $predict[]  = [$secToIso($t), round($demandKwEq, 1)];
            }
            if (! empty($predict)) {
                $predictLast = end($predict); // [ISO, value]
            }
        } else {
            // もう枠の終端に達している場合、最終は実績の最後
            if (! empty($accum)) {
                $predictLast = end($accum);
            }

        }

        // === 返却 ===
        return response()->json([
            'meter'        => $key,
            'meter_name'   => $name,
            'demand_ip'    => $ip,
            'tz'           => $tz,
            'window'       => [
                'start' => $secToIso($from),
                'end'   => $secToIso($to),
            ],
            'threshold'    => $threshold,
            'series'       => [
                'instant'      => $instant, // 参考：枠内の瞬時kW
                'accumulation' => $accum,   // ゼロ開始・右肩上がり（30分平均kW換算）
                'predict'      => $predict, // 予測（枠終端まで）
            ],
            'predict_last' => $predictLast, // バナー用: [ISO, value]
            'now' => \Carbon\Carbon::now('Asia/Tokyo')->toIso8601String(),
        ])->header('Cache-Control', 'no-store');
    })->name('api.v1.meters.demand');
    /**
     * /api/v1/meters/{key}/series?bucket=1m|30m
     * - 1m: 直近120分
     * - 30m: 直近48時間を30分ビン平均
     */
    Route::get('meters/{key}/series', function (Request $req, string $key) use ($resolveDemandIp, $parseTs) {
        try {
            $tz                              = 'Asia/Tokyo';
            [$ip, $name, $threshold, $meter] = $resolveDemandIp($key);

            $bucket = $req->query('bucket', '30m');
            $now    = Carbon::now($tz)->timestamp;

            if ($bucket === '1m') {
                $to   = $parseTs($req->query('to'), $tz) ?: $now;
                $from = $parseTs($req->query('from'), $tz) ?: ($to - 120 * 60); // 120分
                $rows = DB::table('demand')
                    ->select(['date', 'data'])
                    ->where('demand_ip', $ip)
                    ->whereBetween('date', [$from, $to])
                    ->where('delete_flag', 0)
                    ->orderBy('date')
                    ->get();

                $points = [];
                foreach ($rows as $r) {
                    $sec = is_numeric($r->date) ? (int) $r->date : (int) $r->date;
                    if ($sec > 1_000_000_000_000) {
                        $sec = intdiv($sec, 1000);
                    }

                    $points[] = [Carbon::createFromTimestamp($sec, $tz)->toIso8601String(), (float) $r->data];
                }
            } else { // '30m' 既定
                $to   = $parseTs($req->query('to'), $tz) ?: $now;
                $from = $parseTs($req->query('from'), $tz) ?: ($to - 48 * 3600); // 48時間
                                                                                 // 生データ取得 → 30分ビンで平均（SQLでもできるが、方言回避でPHP側集計）
                $rows = DB::table('demand')
                    ->select(['date', 'data'])
                    ->where('demand_ip', $ip)
                    ->whereBetween('date', [$from, $to])
                    ->where('delete_flag', 0)
                    ->orderBy('date')
                    ->get();

                $bins = []; // bin_ts(sec) => [sum, cnt]
                foreach ($rows as $r) {
                    $sec = is_numeric($r->date) ? (int) $r->date : (int) $r->date;
                    if ($sec > 1_000_000_000_000) {
                        $sec = intdiv($sec, 1000);
                    }

                    $bin = intdiv($sec, 1800) * 1800; // 30分ビン
                    if (! isset($bins[$bin])) {
                        $bins[$bin] = [0.0, 0];
                    }

                    $bins[$bin][0] += (float) $r->data;
                    $bins[$bin][1] += 1;
                }
                ksort($bins);
                $points = [];
                foreach ($bins as $bin => [$sum, $cnt]) {
                    $avg = $cnt ? $sum / $cnt : null;
                    if ($avg !== null) {
                        $points[] = [Carbon::createFromTimestamp($bin, $tz)->toIso8601String(), round($avg, 2)];
                    }
                }
            }

            return response()->json([
                'meter'      => $key,
                'meter_name' => $name,
                'demand_ip'  => $ip,
                'tz'         => $tz,
                'bucket'     => $bucket,
                'series'     => ['instant' => $points ?? []],
            ])->header('Cache-Control', 'no-store');
        } catch (\Throwable $e) {
            report($e);
            return response()->json([
                'error'   => 'server_error',
                'message' => config('app.debug') ? $e->getMessage() : 'Server Error',
            ], 500);
        }
    })->name('api.v1.meters.series');

    // 末尾キャッチ：JSON 404（HTMLを返さない）
    Route::any('{any}', fn() => response()->json(['error' => 'Not Found'], 404))
        ->where('any', '.*');
});
