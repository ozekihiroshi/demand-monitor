<?php

namespace App\Http\Controllers\Pro;

use App\Http\Controllers\Controller;
use App\Models\Provider;

class ProDashboardController extends Controller
{
    public function index(Provider $provider)
    {
        return response('Provider console: ok', 200);
    }

    public function billing(Provider $provider)
    {
        return response('Billing: ok', 200);
    }
}
