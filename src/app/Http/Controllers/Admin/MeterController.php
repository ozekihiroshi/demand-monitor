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

        $q = Meter::query()->with('group')->orderBy('code');
        if ($s = trim($request->get('search',''))) {
            $q->where(fn($w) => $w
                ->where('code','like',"%{$s}%")
                ->orWhere('name','like',"%{$s}%")
            );
        }
        if ($gid = $request->get('group_id')) {
            $q->where('group_id', $gid);
        }

        return Inertia::render('Meters/Index', [
            'filters' => [
                'search' => $s,
                'group_id' => $gid,
            ],
            'groups' => Group::orderBy('name')->get(['id','name']),
            'meters' => $q->paginate(20)->withQueryString(),
            'can' => [
                'create' => $request->user()->can('create', Meter::class),
            ],
        ]);
    }

    public function create()
    {
        $this->authorize('create', Meter::class);
        return Inertia::render('Meters/Form', [
            'meter' => null,
            'groups' => Group::orderBy('name')->get(['id','name']),
        ]);
    }

    public function store(MeterStoreRequest $request)
    {
        $this->authorize('create', Meter::class);
        $meter = Meter::create($request->validated());
        return redirect()->route('admin.meters.edit', $meter->code)
            ->with('success', 'メーターを作成しました。');
    }

    public function edit(Meter $meter)
    {
        $this->authorize('update', $meter);
        return Inertia::render('Meters/Form', [
            'meter' => $meter->only(['code','name','group_id','threshold_override','rate_override']),
            'groups' => Group::orderBy('name')->get(['id','name']),
        ]);
    }

    public function update(MeterUpdateRequest $request, Meter $meter)
    {
        $this->authorize('update', $meter);
        $meter->update($request->validated());
        return back()->with('success', 'メーターを更新しました。');
    }

    public function destroy(Meter $meter)
    {
        $this->authorize('delete', $meter);
        $meter->delete(); // ソフトデリート
        return redirect()->route('admin.meters.index')->with('success', 'メーターを削除しました。');
    }
}
