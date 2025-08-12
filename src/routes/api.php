<?php
// routes/api.php
use App\Http\Controllers\Api\V1\SeriesController;

Route::prefix('v1')->group(function () {
    Route::get('meters/{code}/series', [SeriesController::class, 'series']); // 30m/1m 両対応
    Route::get('meters/{code}/demand', [SeriesController::class, 'demand']); // 瞬間/積算/予測
});

