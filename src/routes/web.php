<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\MeterController;        // Inertia（新UI）
use App\Http\Controllers\Admin\LegacyMeterController;  // 旧Bladeグラフ用
use Inertia\Inertia;

/* --- 公開トップなど --- */
Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin'       => Route::has('login'),
        'canRegister'    => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion'     => PHP_VERSION,
    ]);
});

/* --- （必要なら）公開チャート --- */
Route::get('/meters/{code}/index',   fn($code) => view('charts.index',   ['code'=>$code,'bucket'=>'30m']));
Route::get('/meters/{code}/index01', fn($code) => view('charts.index',   ['code'=>$code,'bucket'=>'1m']));
Route::get('/meters/{code}/demand',  fn($code) => view('charts.demand',  ['code'=>$code]));

/* --- 認証系 --- */
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', fn() => Inertia::render('Dashboard'))->name('dashboard');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth','verified'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [DashboardController::class,'index'])->name('dashboard');
    Route::get('/meters', [MeterController::class,'index'])->name('meters.index');

    // ← ここを明示で :code に
    Route::get('/meters/{meter:code}', [MeterController::class,'show'])->name('meters.show');
    Route::get('/meters/{meter:code}/series', [LegacyMeterController::class,'series'])->name('meters.series');
    Route::get('/meters/{meter:code}/demand', [LegacyMeterController::class,'demand'])->name('meters.demand');
});


require __DIR__.'/auth.php';
