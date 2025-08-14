<?php
// app/Policies/MeterPolicy.php
namespace App\Policies;

use App\Models\Meter;
use App\Models\User;

class MeterPolicy
{
    /**
     * super-admin はすべて許可
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }
        return null;
    }

    /**
     * 一覧表示
     * - ロール（org-admin / operator / viewer）または meters.view があれば可
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['org-admin','operator','viewer'])
            || $user->can('meters.view');
    }

    /**
     * 単体表示
     * - ロール（org-admin / operator / viewer）または meters.view
     * - かつメーターの group に所属していること
     */
    public function view(User $user, Meter $meter): bool
    {
        if (! ($user->hasRole(['org-admin','operator','viewer']) || $user->can('meters.view'))) {
            return false;
        }
        return $this->isMemberOfGroup($user, $meter->group_id);
    }

    /**
     * 作成
     * - org-admin ロール、または meters.create / meters.manage
     */
    public function create(User $user): bool
    {
        return $user->hasRole('org-admin')
            || $user->can('meters.create')
            || $user->can('meters.manage');
    }

    /**
     * 更新
     * - org-admin / operator ロール、または meters.update / meters.manage
     * - かつ対象メーターの group に所属
     */
    public function update(User $user, Meter $meter): bool
    {
        if (! ($user->hasRole(['org-admin','operator'])
            || $user->can('meters.update')
            || $user->can('meters.manage'))) {
            return false;
        }
        return $this->isMemberOfGroup($user, $meter->group_id);
    }

    /**
     * 削除（ソフトデリート）
     * - org-admin ロール、または meters.delete / meters.manage
     * - かつ対象メーターの group に所属
     */
    public function delete(User $user, Meter $meter): bool
    {
        if (! ($user->hasRole('org-admin')
            || $user->can('meters.delete')
            || $user->can('meters.manage'))) {
            return false;
        }
        return $this->isMemberOfGroup($user, $meter->group_id);
    }

    public function restore(User $user, Meter $meter): bool
    {
        return $this->delete($user, $meter);
    }

    public function forceDelete(User $user, Meter $meter): bool
    {
        return false; // 物理削除は原則禁止
    }

    /**
     * ヘルパ：ユーザーが group に所属しているか
     * 既存の $user->groups() を利用
     */
    protected function isMemberOfGroup(User $user, ?int $groupId): bool
    {
        if (!$groupId) return false;
        return $user->groups()->whereKey($groupId)->exists();
    }
}
