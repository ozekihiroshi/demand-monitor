<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Meter;              // ルートモデル {meter:code} を仮定
use Carbon\Carbon;

class LegacyMeterController extends Controller
{
    // 既存の管理用エンドポイント（残す場合）
    public function series(Request $request, Meter $meter)   { return $this->seriesJson($request, $meter); }
    public function demand(Request $request, Meter $meter)   { return $this->demandJson($request, $meter); }

    // 公開用（今回の追加）
    public function seriesPublic(Request $request, Meter $meter) { return $this->seriesJson($request, $meter); }
    public function demandPublic(Request $request, Meter $meter) { return $this->demandJson($request, $meter); }

    /** 実体：とりあえず疑似データで可視化を復旧。後でDBクエリに差し替え */
    private function seriesJson(Request $request, Meter $meter)
    {
        $bucket = $request->query('bucket', '30m');
        $tz     = 'Asia/Tokyo';

        // ★TODO: ここを実データの取得に置き換える
        $now = Carbon::now($tz)->second(0);
        $points = [];
        if ($bucket === '1m') {
            $start = $now->copy()->subMinutes(120);
            for ($t = $start->copy(); $t <= $now; $t->addMinute()) {
                $points[] = [$t->toIso8601String(), round(200 + mt_rand(-30, 30), 2)];
            }
        } else { // 30m
            $start = $now->copy()->subHours(48);
            for ($t = $start->copy(); $t <= $now; $t->addMinutes(30)) {
                $points[] = [$t->toIso8601String(), round(220 + mt_rand(-40, 40), 2)];
            }
        }

        return response()->json([
            'meter'  => $meter->code ?? (string)$meter,
            'bucket' => $bucket,
            'tz'     => $tz,
            'series' => ['instant' => $points],
        ]);
    }

    private function demandJson(Request $request, Meter $meter)
    {
        $tz        = 'Asia/Tokyo';
        $threshold = property_exists($meter, 'threshold') ? ($meter->threshold ?? 312) : 312;

        // ★TODO: ここを実データの取得に置き換える（直近30分を1分粒度）
        $end   = Carbon::now($tz)->second(0);
        $start = $end->copy()->subMinutes(30);

        $points = [];
        for ($t = $start->copy(); $t <= $end; $t->addMinute()) {
            $base = 250 + (($t->minute % 10) * 10);
            $points[] = [$t->toIso8601String(), round($base + mt_rand(-8, 8), 2)];
        }

        return response()->json([
            'meter'   => $meter->code ?? (string)$meter,
            'threshold' => $threshold,
            'tz'      => $tz,
            'window'  => ['start' => $start->toIso8601String(), 'end' => $end->toIso8601String()],
            'series'  => ['instant' => $points],
        ]);
    }
}


