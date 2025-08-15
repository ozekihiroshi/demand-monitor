<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Meter;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $this->middleware(['auth', 'verified']); // 念のため

        $user = $request->user();

        // Spatie\Permission想定
        $isSuper   = method_exists($user, 'hasRole') ? $user->hasRole('super-admin') : false;
        $roleNames = method_exists($user, 'getRoleNames') ? $user->getRoleNames()->values() : collect();
        $groupIds  = $isSuper ? collect() : $user->groups()->pluck('groups.id');
        $groupList = $user->groups()
            ->select(['groups.id as id', 'groups.name']) // ← 修飾＆必要なら alias
            ->orderBy('groups.name')
            ->get();

        // メーター集計（可視範囲内）
        $base = Meter::query();
        if (! $isSuper) {
            $base->whereIn('group_id', $groupIds->all() ?: [-1]); // 所属なしなら空ヒット
        }
        $metrics = [
            'meters_total'   => (clone $base)->count(),
            'meters_active'  => (clone $base)->withoutTrashed()->count(),
            'meters_deleted' => (clone $base)->onlyTrashed()->count(),
        ];

        return Inertia::render('Admin/Dashboard', [
            'userInfo' => [
                'name'    => $user->name,
                'email'   => $user->email,
                'roles'   => $roleNames, // ["super-admin", ...]
                'isSuper' => $isSuper,
                'groups'  => $groupList, // [{id,name}]
            ],
            'metrics'  => $metrics,
            'can'      => [
                'createMeter' => $user->can('create', Meter::class),
            ],
        ]);
    }
}
