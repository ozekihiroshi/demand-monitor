<?php
use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\MeterController;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin'       => Route::has('login'),
        'canRegister'    => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion'     => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('/meters/{code}/index', fn($code) => view('charts.index', ['code'=>$code,'bucket'=>'30m']));
Route::get('/meters/{code}/index01', fn($code) => view('charts.index', ['code'=>$code,'bucket'=>'1m']));
Route::get('/meters/{code}/demand', fn($code) => view('charts.demand', ['code'=>$code]));

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth'])->prefix('admin/meters')->name('admin.meters.')->group(function () {
    Route::get('/', [MeterController::class, 'index'])->name('index');
    Route::get('{code}/series', [MeterController::class, 'series'])->name('series');
    Route::get('{code}/demand', [MeterController::class, 'demand'])->name('demand');
});



require __DIR__ . '/auth.php';
