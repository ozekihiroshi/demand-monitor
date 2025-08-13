<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Meter;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        // TODO: 実データに接続（旧DB or 内部API呼び出し）
        $overThresholdCount = Cache::remember('dash:over', 30, fn() => 0);
        $topPeaks = Cache::remember('dash:top5', 30, fn() => []);
        $unconfigured = Cache::remember('dash:unconf', 30, function(){
            return Meter::query()
                ->whereNull('rate_override')
                ->orWhereNull('threshold_override')
                ->count();
        });
        return Inertia::render('Admin/Dashboard', [
            'cards' => [
                'overThreshold' => $overThresholdCount,
                'topPeaks' => $topPeaks,
                'unconfigured' => $unconfigured,
            ],
        ]);
    }
}


