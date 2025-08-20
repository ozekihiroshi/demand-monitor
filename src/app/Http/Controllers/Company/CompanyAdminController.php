<?php
namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\Company;

class CompanyAdminController extends Controller
{
    public function index(Company $company)
    {
        $this->authorize('access-company-console', $company);

        // 施設一覧（最小で id/name）
        $facilities = $company->facilities()
            ->select('id', 'name', 'company_id')
            ->orderBy('name')
            ->get();

        return view('company.dashboard', compact('company', 'facilities'));
    }
}