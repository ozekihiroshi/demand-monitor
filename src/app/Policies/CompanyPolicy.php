<?php
// app/Policies/CompanyPolicy.php
namespace App\Policies;

use App\Models\Company;
use App\Models\User;
use App\Models\Engagement;
use Illuminate\Support\Facades\Schema;

class CompanyPolicy
{
    public function accessCompanyConsole(User $user, Company $company): bool
    {
        try {
            // リレーションがあれば優先、無ければモデル直参照
            if (method_exists($user, 'engagements')) {
                $q = $user->engagements()->where('company_id', $company->id);
            } elseif (class_exists(Engagement::class)) {
                $q = Engagement::query()
                    ->where('user_id', $user->id)
                    ->where('company_id', $company->id);
            } else {
                return false;
            }

            // テーブルに存在する列だけ条件を足す（無ければスキップ）
            if (Schema::hasColumn('engagements', 'status')) {
                $q->where('status', 'active');
            }
            if (Schema::hasColumn('engagements', 'ended_at')) {
                $q->where(function ($w) {
                    $w->whereNull('ended_at')->orWhere('ended_at', '>', now());
                });
            }
            if (Schema::hasColumn('engagements', 'effective_from')) {
                $q->where('effective_from', '<=', now());
            }
            if (Schema::hasColumn('engagements', 'effective_to')) {
                $q->where(function ($w) {
                    $w->whereNull('effective_to')->orWhere('effective_to', '>=', now());
                });
            }

            return $q->exists();
        } catch (\Throwable $e) {
            report($e);
            return false; // ← 例外でも必ず boolean（deny）で返す
        }
    }
}

