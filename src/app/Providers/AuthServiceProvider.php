<?php
// app/Providers/AuthServiceProvider.php
namespace App\Providers;

use App\Models\Meter;
use App\Models\Provider as EnergyProvider;
use App\Models\Company;
use App\Policies\MeterPolicy;
use App\Policies\CompanyPolicy;
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

        // /pro/{provider} の入場（例外でも 403 にフォールバック）
        Gate::define('access-provider-console', static function ($user, EnergyProvider $provider): bool {
            try {
                if (!method_exists($user, 'providers')) {
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
    }
}
