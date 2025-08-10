<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DemandGraphController extends Controller
{
    public function restfull(string $demand_ip)
    {
        return response()->json([
            'shikiichi' => 800,
            'max_data'  => 457.81,
            'demand_ip' => $demand_ip,
        ]);
    }

    public function demand(Request $request)
    {
        return response('<h1>デマンド管理図</h1>', 200)
            ->header('Content-Type', 'text/html; charset=UTF-8');
    }

    public function index(Request $request)
    {
        return response('<h1>デマンド推移図</h1>', 200)
            ->header('Content-Type', 'text/html; charset=UTF-8');
    }
}

