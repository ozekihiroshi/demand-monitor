<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Meter;
use App\Models\LegacyUser; // 現状のまま使う（名前空間そのまま）
use Illuminate\View\View;

class LegacyMeterController extends Controller
{
    public function series(Meter $meter): View
    {
        $this->authorize('view', $meter);
        $m = LegacyUser::find($meter->code); // 既存どおり（uid=code を想定）
        return view('admin.meters.series', [
            'code' => $meter->code,
            'rate' => $m?->rate ?? 0,
            'goal' => $m?->shikiichi ?? 800,
        ]);
    }

    public function demand(Meter $meter): View
    {
        $this->authorize('view', $meter);
        $m = LegacyUser::find($meter->code);
        return view('admin.meters.demand', [
            'code'      => $meter->code,
            'rate'      => $m?->rate ?? 0,
            'threshold' => $m?->shikiichi ?? 800,
        ]);
    }
}