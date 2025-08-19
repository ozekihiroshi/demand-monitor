<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\V1\MeterDemandController;
use App\Http\Controllers\Api\V1\SeriesController; // あるなら

Route::prefix('v1')->name('api.v1.')->group(function () {

    // 30分枠の当日リアルタイム（実績＋予測を毎回フル返却）
    // ★ モデルバインドをやめて {code} に
    Route::get('meters/{code}/demand', [MeterDemandController::class, 'demand'])
        ->name('meters.demand');

    // （任意）オーバーレイ
    Route::get('meters/{code}/series', [SeriesController::class, 'series'])
        ->name('meters.series');

    // フォールバック
    Route::any('{any}', function () {
        return response()->json(['error' => 'not_found'], 404);
    })->where('any', '.*');
});

