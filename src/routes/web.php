<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Illuminate\Foundation\Application;

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\LegacyMeterController;
use App\Http\Controllers\Admin\MeterController;
use App\Http\Controllers\Company\CompanyAdminController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Pro\ProDashboardController;

use App\Models\Facility;
use App\Models\Meter;

/* --- Feature flags (.env) --- */
$featPro      = (bool) env('FEATURE_PRO_CONSOLE', false);
$featCompany  = (bool) env('FEATURE_COMPANY_CONSOLE', false);
$featFacility = (bool) env('FEATURE_FACILITY_CONSOLE', false);

/* --- 公開トップ --- */
Route::get('/', function () {
    return view('guest.home');
})->name('home');

/* --- 認証系（共通） --- */
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', \App\Http\Controllers\DashboardRedirectController::class)
        ->name('dashboard');
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

/* --- 会社コンソール（Company） --- */
if ($featCompany) {
    Route::middleware(['auth', 'verified'])
        ->prefix('companies/{company:slug}')
        ->name('company.')
        ->group(function () {
            Route::get('/admin', [CompanyAdminController::class, 'index'])
                ->middleware('can:access-company-console,company')
                ->name('dashboard');
        });
}

/* --- プロコンソール（Provider/Pro） --- */
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

/* --- 公開チャート（ビュー）。$meter を eager load する版に統一 --- */
Route::get('/meters/{code}/demand', function (string $code) {
    $meter = Meter::with('facility.company')->where('code', $code)->firstOrFail();
    return view('charts.demand', ['code' => $code, 'meter' => $meter]);
})->name('meter.demand');

Route::get('/meters/{code}/minute', function (string $code) {
    $meter = Meter::with('facility.company')->where('code', $code)->firstOrFail();
    return view('charts.minutely', ['code' => $code, 'days' => 7, 'view' => 'overlay', 'meter' => $meter]);
})->name('meter.minute');

Route::get('/meters/{code}/series', function (string $code) {
    $meter = Meter::with('facility.company')->where('code', $code)->firstOrFail();
    return view('charts.series', ['code' => $code, 'bucket' => '30m', 'days' => 8, 'meter' => $meter]);
})->name('meter.series');

/* 互換：昔の /meters/{code}/series1 も残したい場合（任意） */
Route::get('/meters/{code}/series1', function (string $code) {
    $meter = Meter::with('facility.company')->where('code', $code)->firstOrFail();
    return view('charts.series', ['code' => $code, 'bucket' => '1m', 'days' => 1, 'meter' => $meter]);
})->name('meter.series1');

/* --- 施設ダッシュ & 施設配下メータの“ハブ” --- */
if ($featFacility) {
    Route::middleware(['auth', 'verified'])
        ->prefix('facilities/{facility}')
        ->name('facility.')
        ->group(function () {
            // 施設ダッシュ（施設→メータ一覧）
            Route::get('/admin', function (Facility $facility) {
                $facility->load(['company', 'meters']);
                $code = $facility->main_meter_code
                    ?? optional($facility->meters->first())->code
                    ?? 'd100318';
                return view('facility.dashboard', compact('facility', 'code'));
            })->middleware('can:access-facility-console,facility')
              ->name('dashboard');

            // 施設→メータ “ハブ”（施設配下の特定メータのリンク集）
            Route::get('/meters/{meter:code}', function (Facility $facility, Meter $meter) {
                abort_unless($meter->facility_id === $facility->id, 404);
                $facility->load('company');
                return view('meter.dashboard', compact('facility', 'meter'));
            })->middleware('can:access-facility-console,facility')
              ->name('meters.show');
        });
}

/* --- 公開JSON（既存のpublicエンドポイントのまま） --- */
Route::prefix('public')->name('public.')->group(function () {
    Route::get('meters/{meter:code}/series', [LegacyMeterController::class, 'seriesPublic'])
        ->name('meters.series');
    Route::get('meters/{meter:code}/demand', [LegacyMeterController::class, 'demandPublic'])
        ->name('meters.demand');
});

/* --- 認証ルート --- */
require __DIR__ . '/auth.php';
