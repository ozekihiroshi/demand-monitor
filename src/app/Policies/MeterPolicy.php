<?php
// app/Policies/MeterPolicy.php
namespace App\Policies;

use App\Models\Meter;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class MeterPolicy
{
    /**
     * super-admin は全許可
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('super-admin')) return true;
        return null;
    }

    /**
     * 一覧表示
     * - 会社の有効な engagement が1つでもあればOK
     * - もしくは施設割当（facility_user）が1つでもあればOK
     * - 互換: 従来のロール/権限でも許可（viewer 等）
     */
    public function viewAny(User $user): bool
    {
        // 互換ロール/権限（従来の gates）
        if ($user->hasRole(['org-admin','operator','viewer']) || $user->can('meters.view')) {
            return true;
        }

        return $this->hasAnyActiveEngagement($user) || $this->hasAnyActiveFacility($user);
    }

    /**
     * 単体表示
     * - 会社スコープ（engagements）OR 施設スコープ（facility_user）に該当
     * - 互換: 従来のロール/権限は残しつつ、必ずスコープチェック
     */
    public function view(User $user, Meter $meter): bool
    {
        if ($user->hasRole(['org-admin','operator','viewer']) || $user->can('meters.view')) {
            return $this->inScope($user, $meter);
        }
        return $this->inScope($user, $meter);
    }

    /**
     * 作成
     * - org-admin（会社スコープ）または権限 meters.create / meters.manage
     *   ※ どの会社に作るかはフォーム側で facility を選ばせる想定
     */
    public function create(User $user): bool
    {
        if ($user->hasRole('org-admin') || $user->can('meters.create') || $user->can('meters.manage')) {
            return true;
        }
        // engagements 上で org-admin の委託を持つなら許可
        return $this->hasActiveEngagementWithRole($user, ['org-admin']);
    }

    /**
     * 更新
     * - org-admin（該当会社スコープ） OR 該当施設の facility-operator
     * - 互換: meters.update / meters.manage でも可（ただしスコープ内）
     */
    public function update(User $user, Meter $meter): bool
    {
        if ($user->can('meters.update') || $user->can('meters.manage')) {
            return $this->inScope($user, $meter);
        }

        $companyId = $meter->facility?->company_id;

        // 会社 org-admin（engagements）
        if ($this->hasActiveEngagementWithRole($user, ['org-admin'], $companyId)) {
            return true;
        }

        // 施設 operator（facility_user）
        if ($this->hasActiveFacilityRole($user, $meter->facility_id, ['facility-operator'])) {
            return true;
        }

        // 互換ロール（旧 operator）：スコープ内のみ
        if ($user->hasRole('operator')) {
            return $this->inScope($user, $meter);
        }

        return false;
    }

    /**
     * 削除（ソフトデリート）
     * - org-admin（該当会社スコープ）または meters.delete / meters.manage
     * - operator は不可
     */
    public function delete(User $user, Meter $meter): bool
    {
        if ($user->can('meters.delete') || $user->can('meters.manage')) {
            return $this->inScope($user, $meter);
        }

        $companyId = $meter->facility?->company_id;
        return $this->hasActiveEngagementWithRole($user, ['org-admin'], $companyId);
    }

    public function restore(User $user, Meter $meter): bool
    {
        return $this->delete($user, $meter);
    }

    public function forceDelete(User $user, Meter $meter): bool
    {
        return false; // 物理削除は不可
    }

    /* ===================== ヘルパ ===================== */

    protected function inScope(User $user, Meter $meter): bool
    {
        $companyId = $meter->facility?->company_id;
        if ($companyId && $this->hasActiveEngagement($user, $companyId)) return true;
        return $this->hasActiveFacility($user, $meter->facility_id);
    }

    protected function hasAnyActiveEngagement(User $user): bool
    {
        return $user->engagements()
            ->where('status','active')
            ->whereDate('effective_from','<=', now())
            ->where(fn($q)=>$q->whereNull('effective_to')->orWhereDate('effective_to','>=', now()))
            ->exists();
    }

    protected function hasActiveEngagement(User $user, int $companyId): bool
    {
        return $user->engagements()
            ->where('status','active')
            ->where('company_id', $companyId)
            ->whereDate('effective_from','<=', now())
            ->where(fn($q)=>$q->whereNull('effective_to')->orWhereDate('effective_to','>=', now()))
            ->exists();
    }

    protected function hasActiveEngagementWithRole(User $user, array $roles, ?int $companyId = null): bool
    {
        $q = $user->engagements()
            ->where('status','active')
            ->whereIn('role', $roles)
            ->whereDate('effective_from','<=', now())
            ->where(fn($q)=>$q->whereNull('effective_to')->orWhereDate('effective_to','>=', now()));
        if ($companyId) $q->where('company_id', $companyId);
        return $q->exists();
    }

    protected function hasAnyActiveFacility(User $user): bool
    {
        return DB::table('facility_user')
            ->where('user_id',$user->id)
            ->where(function($q){ $q->whereNull('effective_from')->orWhereDate('effective_from','<=', now()); })
            ->where(function($q){ $q->whereNull('effective_to')->orWhereDate('effective_to','>=', now()); })
            ->exists();
    }

    protected function hasActiveFacility(User $user, ?int $facilityId): bool
    {
        if (!$facilityId) return false;
        return DB::table('facility_user')
            ->where('user_id',$user->id)
            ->where('facility_id',$facilityId)
            ->where(function($q){ $q->whereNull('effective_from')->orWhereDate('effective_from','<=', now()); })
            ->where(function($q){ $q->whereNull('effective_to')->orWhereDate('effective_to','>=', now()); })
            ->exists();
    }

    protected function hasActiveFacilityRole(User $user, ?int $facilityId, array $roles): bool
    {
        if (!$facilityId) return false;
        return DB::table('facility_user')
            ->where('user_id',$user->id)
            ->where('facility_id',$facilityId)
            ->whereIn('role',$roles)
            ->where(function($q){ $q->whereNull('effective_from')->orWhereDate('effective_from','<=', now()); })
            ->where(function($q){ $q->whereNull('effective_to')->orWhereDate('effective_to','>=', now()); })
            ->exists();
    }
}
