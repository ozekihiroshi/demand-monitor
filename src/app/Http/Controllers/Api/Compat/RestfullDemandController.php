<?php
namespace App\Http\Controllers\Api\Compat;

use App\Http\Controllers\Controller;
use App\Models\Meter;
use Illuminate\Http\Request;

class RestfullDemandController extends Controller
{
    // GET /api/restfull/demand/{demand_ip}
    public function show($demand_ip)
    {
        $meter = Meter::where('demand_ip', $demand_ip)->first();

        if (!$meter) {
            return response()->json(['shikiichi'=>800, 'max_data'=>0.0]); // フォールバック
        }

        $latest = $meter->demands()->orderByDesc('ts')->first();
        $max = $meter->demands()->where('ts','>=',now()->subDay())->max('kw_30m');

        // しきい値は暫定ロジック：デバイス or メータ or 施設の設定に拡張予定
        $threshold = optional($meter->facility->devices()->first())->threshold_kw ?? 800;

        return response()->json([
            'shikiichi' => (float)$threshold,
            'max_data'  => (float)($latest->kw_30m ?? $max ?? 0.0),
        ]);
    }
}

