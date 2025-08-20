<?php
// app/Providers/AuthServiceProvider.php
namespace App\Providers;

use App\Models\Company;
use App\Models\Facility;
use App\Models\Meter;
use App\Models\Provider as EnergyProvider;
use App\Models\User;
use App\Policies\CompanyPolicy;
use App\Policies\MeterPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Meter::class   => MeterPolicy::class,
        Company::class => CompanyPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();

        // super-admin は全許可
        Gate::before(function ($user, $ability) {
            return method_exists($user, 'hasRole') && $user->hasRole('super-admin') ? true : null;
        });
        Gate::define('access-admin', function (User $u): bool {
            return $u->hasAnyRole(['super-admin', 'power-manager']);
        });

        // /pro/{provider} の入場（例外でも 403 にフォールバック）
        Gate::define('access-provider-console', static function ($user, EnergyProvider $provider): bool {
            try {
                if (! method_exists($user, 'providers')) {
                    return false;
                }

                $now = now();

                $rel = $user->providers()
                    ->whereKey($provider->getKey())
                    ->where(function ($q) {
                        $q->wherePivot('role', 'admin')
                            ->orWherePivot('role', 'viewer');
                    });

                // ピボット列がある時だけ期間条件を適用
                if (Schema::hasColumn('provider_user', 'valid_from')) {
                    $rel->where(function ($q) use ($now) {
                        $q->wherePivot('valid_from', null)
                            ->orWherePivot('valid_from', '<=', $now);
                    });
                }
                if (Schema::hasColumn('provider_user', 'valid_until')) {
                    $rel->where(function ($q) use ($now) {
                        $q->wherePivot('valid_until', null)
                            ->orWherePivot('valid_until', '>=', $now);
                    });
                }

                return $rel->exists();
            } catch (\Throwable $e) {
                report($e);
                return false; // ← 500 を出さず 403（deny）に落とす
            }
        });

        // /companies/{company}/admin は Policy へ委譲（“正道”）
        Gate::define('access-company-console', [CompanyPolicy::class, 'accessCompanyConsole']);

        Gate::define('access-facility-console', function (User $u, Facility $f): bool {
            // Super/電管は無条件OK
            if ($u->hasAnyRole(['super-admin', 'power-manager'])) {
                return true;
            }

            // 会社 Gate に委譲（CompanyPolicy / Gate が既に通っている前提）
            $company = $f->company; // ← Facility に company() リレーションがある想定
            if ($company && Gate::forUser($u)->allows('access-company-console', $company)) {
                return true;
            }

            // 会社リレーションが無い or 会社 Gate でNG の場合のみ deny
            return false;
        });

    }
}
