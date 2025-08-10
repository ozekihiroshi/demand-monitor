<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DemandGraphController;

    // とりあえず固定値 or 後でDB参照に差し替え
Route::get('/restfull/demand/{demand_ip}', [DemandGraphController::class, 'restfull']);
Route::get('/demand/demand', [DemandGraphController::class, 'demand']);
Route::get('/demand/index',  [DemandGraphController::class, 'index']);

