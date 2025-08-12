<?php
// app/Http/Controllers/Admin/MeterController.php
// app/Http/Controllers/Admin/MeterController.php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LegacyUser;

class MeterController extends Controller
{
    public function index() {
        $meters = LegacyUser::query()
            ->select('uid as code','name','rate','shikiichi')
            ->orderBy('uid')->limit(200)->get();
        return view('admin.meters.index', compact('meters'));
    }

    public function series(string $code) {
        $m = LegacyUser::find($code);
        return view('admin.meters.series', [
            'code' => $code,
            'rate' => $m?->rate ?? 0,
            'goal' => $m?->shikiichi ?? 800,
        ]);
    }

    public function demand(string $code) {
        $m = LegacyUser::find($code);
        return view('admin.meters.demand', [
            'code' => $code,
            'rate' => $m?->rate ?? 0,
            'threshold' => $m?->shikiichi ?? 800,
        ]);
    }
}
