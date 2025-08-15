<?php
// app/Http/Controllers/Admin/MeterController.php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\MeterStoreRequest;
use App\Http\Requests\MeterUpdateRequest;
use App\Models\Group;
use App\Models\Meter;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MeterController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Meter::class);

        $user    = $request->user();
        $isSuper = $user->hasRole('super-admin');

        // ★ユーザーが見えるグループIDを取得（super-admin は無制限）
        $allowedGroupIds = $isSuper ? collect() : $user->groups()->pluck('groups.id');

        $q = Meter::query()->with('group')->orderBy('code');

        // ★一覧の可視範囲を絞る
        if (! $isSuper) {
            if ($allowedGroupIds->isEmpty()) {
                $q->whereRaw('1 = 0'); // 所属なしは0件
            } else {
                $q->whereIn('group_id', $allowedGroupIds);
            }
        }

        // フィルタ値（未定義対策で先に初期化）
        $s   = trim((string) $request->get('search', ''));
        $gid = $request->get('group_id');

        if ($s !== '') {
            $q->where(function ($w) use ($s) {
                $w->where('code', 'like', "%{$s}%")
                    ->orWhere('name', 'like', "%{$s}%");
            });
        }

        // ★指定 group_id も可視範囲内のときだけ適用（漏えい防止）
        if ($gid) {
            if ($isSuper || $allowedGroupIds->contains((int) $gid)) {
                $q->where('group_id', $gid);
            } else {
                $q->whereRaw('1 = 0'); // 見えないグループは常に0件
            }
        }

        // ★プルダウン用のグループ一覧も可視範囲で絞る
        $groupsQuery = Group::query()->orderBy('name')->select(['id', 'name']);
        if (! $isSuper) {
            if ($allowedGroupIds->isEmpty()) {
                $groupsQuery->whereRaw('1 = 0');
            } else {
                $groupsQuery->whereIn('id', $allowedGroupIds);
            }
        }

        return Inertia::render('Meters/Index', [
            'filters' => [
                'search'   => $s,
                'group_id' => $gid,
            ],
            'groups'  => $groupsQuery->get(),
            'meters'  => $q->paginate(20)->withQueryString(),
            'can'     => [
                'create' => $user->can('create', Meter::class),
            ],
        ]);
    }

    public function create()
    {
        $this->authorize('create', Meter::class);
        return Inertia::render('Meters/Form', [
            'meter'  => null,
            'groups' => Group::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(MeterStoreRequest $request)
    {
        $this->authorize('create', Meter::class);
        $data = $request->validated();
        if (array_key_exists('rate_override', $data) && is_string($data['rate_override'])) {
            $decoded               = json_decode($data['rate_override'], true);
            $data['rate_override'] = $decoded ?? null;
        }
        $meter = Meter::create($data);
        return redirect()->route('admin.meters.edit', $meter->code)
            ->with('success', 'メーターを作成しました。');
    }

    public function edit(Meter $meter)
    {
        $this->authorize('update', $meter);
        return Inertia::render('Meters/Form', [
            'meter'  => $meter->only(['code', 'name', 'group_id', 'threshold_override', 'rate_override']),
            'groups' => Group::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(MeterUpdateRequest $request, Meter $meter)
    {
        $this->authorize('update', $meter);
        $data = $request->validated();
        if (array_key_exists('rate_override', $data) && is_string($data['rate_override'])) {
            $decoded               = json_decode($data['rate_override'], true);
            $data['rate_override'] = $decoded ?? null;
        }
        $meter->update($data);
        return back()->with('success', 'メーターを更新しました。');
    }

    public function destroy(Meter $meter)
    {
        $this->authorize('delete', $meter);
        $meter->delete(); // ソフトデリート
        return redirect()->route('admin.meters.index')->with('success', 'メーターを削除しました。');
    }
}
