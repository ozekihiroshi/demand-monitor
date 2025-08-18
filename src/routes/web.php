<?php

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/* Controllers */
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\LegacyMeterController;
use App\Http\Controllers\Admin\MeterController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Pro\ProDashboardController;
use App\Http\Controllers\Company\CompanyAdminController; // ★ Company配下を使用

/* --- 公開トップ --- */
Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin'       => Route::has('login'),
        'canRegister'    => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion'     => PHP_VERSION,
    ]);
});

/* --- 公開チャート（温存） --- */
Route::get('/meters/{code}/index', fn($code) => view('charts.index', ['code' => $code, 'bucket' => '30m']));
Route::get('/meters/{code}/index01', fn($code) => view('charts.index', ['code' => $code, 'bucket' => '1m']));
Route::get('/meters/{code}/demand', fn($code) => view('charts.demand', ['code' => $code]));

/* --- 認証系 --- */
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', fn() => Inertia::render('Dashboard'))->name('dashboard');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

/* --- 管理（auth + verified + can） --- */
Route::middleware(['auth', 'verified', 'can:access-admin'])
    ->prefix('admin')->name('admin.')
    ->group(function () {
        Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

        Route::prefix('meters/{meter:code}/charts')->name('meters.charts.')->group(function () {
            Route::get('series', [LegacyMeterController::class, 'series'])->name('series');
            Route::get('demand', [LegacyMeterController::class, 'demand'])->name('demand');
        });

        Route::resource('meters', MeterController::class)
            ->parameters(['meters' => 'meter:code'])
            ->except(['show']);

        Route::prefix('meters')->name('meters.')->group(function () {
            Route::get('{meter}/legacy/series', fn() => response('OK', 200))->name('legacy.series');
            Route::get('{meter}/legacy/demand', fn() => response('OK', 200))->name('legacy.demand');
        });
    });

/* --- Feature flags (.env) --- */
$featPro      = (bool) env('FEATURE_PRO_CONSOLE', false);
$featCompany  = (bool) env('FEATURE_COMPANY_CONSOLE', false);
$featFacility = (bool) env('FEATURE_FACILITY_CONSOLE', false);

/* プロ向けコンソール */
if ($featPro) {
    Route::middleware(['auth', 'verified'])
        ->prefix('pro/{provider:slug}')
        ->name('pro.')
        ->group(function () {
            Route::get('/', [ProDashboardController::class, 'index'])
                ->middleware('can:access-provider-console,provider')
                ->name('dashboard');

            Route::get('/billing', [ProDashboardController::class, 'billing'])
                ->middleware('can:access-provider-console,provider')
                ->name('billing.index');
        });
}

/* 会社コンソール（★ 1本に統一） */
if ($featCompany) {
    Route::middleware(['auth', 'verified'])
        ->prefix('companies/{company:slug}')
        ->name('company.')
        ->group(function () {
            Route::get('/admin', [CompanyAdminController::class, 'index'])
                ->middleware('can:access-company-console,company') // ← company を渡す
                ->name('dashboard');
        });
}

/* 施設（将来） */
if ($featFacility) {
    Route::middleware(['auth', 'verified', 'can:access-facility-console'])
        ->prefix('facilities/{facility}/admin')
        ->name('facility.')
        ->group(function () {
            Route::get('/', fn() => response('Facility console: not implemented', 501))
                ->name('dashboard');
        });
}

// すでに public なビュー (/meters/{code}/index, /demand) があるので、そこから叩くためのJSON
Route::prefix('public')->name('public.')->group(function () {
    Route::get('meters/{meter:code}/series', [LegacyMeterController::class, 'seriesPublic'])
        ->name('meters.series');
    Route::get('meters/{meter:code}/demand', [LegacyMeterController::class, 'demandPublic'])
        ->name('meters.demand');
});
require __DIR__ . '/auth.php';
