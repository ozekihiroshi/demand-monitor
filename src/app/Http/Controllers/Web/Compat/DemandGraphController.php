<?php
namespace App\Http\Controllers\Web\Compat;

use App\Http\Controllers\Controller;
use App\Models\Meter;
use Illuminate\Http\Request;

class DemandGraphController extends Controller
{
    // GET /api/demand/demand?demand_ip=...
    public function demand(Request $req)
    {
        $meter = Meter::where('demand_ip', $req->get('demand_ip'))->first();
        return response(
            view('compat.demand', ['meter'=>$meter])->render()
        )->header('Content-Type', 'text/html; charset=UTF-8');
    }

    // GET /api/demand/index?demand_ip=...&hard=...
    public function index(Request $req)
    {
        $meter = Meter::where('demand_ip', $req->get('demand_ip'))->first();
        return response(
            view('compat.index', ['meter'=>$meter])->render()
        )->header('Content-Type', 'text/html; charset=UTF-8');
    }
}

