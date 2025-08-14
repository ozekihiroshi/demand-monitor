<?php
// app/Http/Controllers/Admin/LegacyGraphController.php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Meter;
use App\Models\LegacyUser;

class LegacyGraphController extends Controller
{
    public function series(string $code)
    {
        // 登録主義：未登録は404
        $meter = Meter::where('code', $code)->firstOrFail();

        // 権限制御：登録グループ所属＋権限
        $this->authorize('view', $meter);

        // 互換：メーター上書きがあればそれを優先、なければ旧DB値にフォールバック
        $legacy = LegacyUser::find($code);

        return view('admin.meters.series', [
            'code' => $code,
            'rate' => $meter->effective_rate ?? ($legacy?->rate ?? 0),
            'goal' => $meter->effective_threshold ?? ($legacy?->shikiichi ?? 800),
        ]);
    }

    public function demand(string $code)
    {
        $meter = Meter::where('code', $code)->firstOrFail();
        $this->authorize('view', $meter);

        $legacy = LegacyUser::find($code);

        return view('admin.meters.demand', [
            'code'      => $code,
            'rate'      => $meter->effective_rate ?? ($legacy?->rate ?? 0),
            'threshold' => $meter->effective_threshold ?? ($legacy?->shikiichi ?? 800),
        ]);
    }
}
