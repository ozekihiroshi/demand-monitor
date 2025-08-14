<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Application;
use Inertia\Inertia;

/* Controllers */
use App\Http\Controllers\ProfileController;           // プロファイル（Inertia）
use App\Http\Controllers\Admin\DashboardController;   // 管理ダッシュボード
use App\Http\Controllers\Admin\MeterController;       // 新CRUD（Inertia）
use App\Http\Controllers\Admin\LegacyMeterController; // 旧グラフ（Blade）

/* --- 公開トップなど（そのまま） --- */
Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin'       => Route::has('login'),
        'canRegister'    => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion'     => PHP_VERSION,
    ]);
});

/* --- （必要なら）公開チャート（そのまま温存） --- */
Route::get('/meters/{code}/index',    fn($code) => view('charts.index',  ['code' => $code, 'bucket' => '30m']));
Route::get('/meters/{code}/index01',  fn($code) => view('charts.index',  ['code' => $code, 'bucket' => '1m']));
Route::get('/meters/{code}/demand',   fn($code) => view('charts.demand', ['code' => $code]));

/* --- 認証系（そのまま） --- */
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', fn() => Inertia::render('Dashboard'))->name('dashboard');
    Route::get('/profile',   [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile',[ProfileController::class, 'destroy'])->name('profile.destroy');
});

/* --- 管理（auth+verified のみ1回定義） --- */
Route::middleware(['auth','verified'])
    ->prefix('admin')->name('admin.')
    ->group(function () {
        // ダッシュボード
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        // 旧グラフ（URL互換を維持）— 登録主義＆Policyで保護（Controller内）
        Route::get('meters/{code}/series', [LegacyMeterController::class, 'series'])->name('meters.series');
        Route::get('meters/{code}/demand', [LegacyMeterController::class, 'demand'])->name('meters.demand');

        // 新CRUD（Inertia）— code でルートバインド、showは未実装なので除外
        Route::resource('meters', MeterController::class)
            ->parameters(['meters' => 'meter:code'])
            ->except(['show']);
    });

require __DIR__.'/auth.php';
