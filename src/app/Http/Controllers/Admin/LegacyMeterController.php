<?php
// app/Http/Controllers/Admin/LegacyMeterController.php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Meter;
use App\Models\LegacyUser;
use Illuminate\View\View;

class LegacyMeterController extends Controller
{
    public function series(Meter $meter): View
    {
        $this->authorize('view', $meter);

        // 互換フォールバック
        $legacy = LegacyUser::find($meter->code);

        // まずは公開用Bladeをそのまま使って外形復活
        return view('charts.index', [
            'code'   => $meter->code,
            'bucket' => request('bucket', '30m'),
            // 必要なら下記を渡して使うように後でBlade対応
            'rate'   => data_get($meter, 'effective_rate', data_get($legacy, 'rate', 0)),
            'goal'   => data_get($meter, 'effective_threshold', data_get($legacy, 'shikiichi', 800)),
        ]);
    }

    public function demand(Meter $meter): View
    {
        $this->authorize('view', $meter);

        $legacy = LegacyUser::find($meter->code);

        return view('charts.demand', [
            'code'       => $meter->code,
            'rate'       => data_get($meter, 'effective_rate', data_get($legacy, 'rate', 0)),
            'threshold'  => data_get($meter, 'effective_threshold', data_get($legacy, 'shikiichi', 800)),
        ]);
    }
}
