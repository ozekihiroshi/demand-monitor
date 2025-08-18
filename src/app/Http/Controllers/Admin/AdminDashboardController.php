<?php
// app/Http/Controllers/Admin/AdminDashboardController.php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Facility;
use App\Models\Meter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AdminDashboardController extends Controller
{
    public function index()
    {
        $companies = class_exists(Company::class) ? Company::count() : 0;
        $facilities = class_exists(Facility::class) ? Facility::count() : 0;
        $meters = class_exists(Meter::class) ? Meter::count() : 0;

        // 最近のメーター更新（軽量・確実）
        $recentMeterUpdates = class_exists(Meter::class) && Schema::hasColumn('meters', 'updated_at')
            ? Meter::query()->select('id','code','name','updated_at')
                ->latest('updated_at')->limit(10)->get()
            : collect();

        // 最近のエラーは failed_jobs を“あれば”表示（無ければ空）
        $recentErrors = Schema::hasTable('failed_jobs')
            ? DB::table('failed_jobs')->select('id','connection','queue','failed_at')
                ->latest('failed_at')->limit(10)->get()
            : collect();

        return view('admin.dashboard', compact('companies','facilities','meters','recentMeterUpdates','recentErrors'));
    }
}

