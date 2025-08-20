<?php
// app/Http/Controllers/Company/CompanyAdminController.php
namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\Company;

class CompanyAdminController extends Controller
{
    public function index(Company $company)
    {
        // Gate（二重防御）
        $this->authorize('access-company-console', $company);

        // 施設をロード（必要なら並び順を調整）
        $company->load(['facilities' => function ($q) {
            $q->orderBy('name');
        }]);

        // Blade へ明示的に渡す
        return view('company.dashboard', [
            'company'    => $company,
            'facilities' => $company->facilities,
        ]);
    }
}
