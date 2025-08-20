<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DashboardRedirectController extends Controller
{
    public function __invoke(Request $request)
    {
        $u = $request->user();

        if (method_exists($u, 'hasRole') && $u->hasRole('super-admin')) {
            return redirect()->route('admin.dashboard'); // 既存
        }
        if (method_exists($u, 'hasRole') && $u->hasRole('power-manager')) {
            return redirect()->route('admin.dashboard'); // 電管＝管理ダッシュボードへ
        }
        // 会社ダッシュボード
        if (method_exists($u, 'primaryCompany') && ($c = $u->primaryCompany())) {
            return redirect()->route('company.dashboard', $c); // companies/{slug}/admin
        }
        // 施設ダッシュボード（必要なら）
        if (method_exists($u, 'primaryFacility') && ($f = $u->primaryFacility())) {
            return redirect()->route('facility.dashboard', $f);
        }

        // フォールバック（従来のダッシュボード or プロフィール等）
        return redirect()->route('profile.edit');
    }
}