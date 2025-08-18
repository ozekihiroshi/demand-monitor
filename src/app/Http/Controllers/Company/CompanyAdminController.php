<?php
// app/Http/Controllers/Company/CompanyAdminController.php
namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\Company;

class CompanyAdminController extends Controller
{
    public function index(Company $company)
    {
        // ルート側で can 済みだが二重防御としてOK
        $this->authorize('access-company-console', $company);

        // 後で Inertia 画面に差し替え可
        return response('Company console: ok', 200);
    }
}

