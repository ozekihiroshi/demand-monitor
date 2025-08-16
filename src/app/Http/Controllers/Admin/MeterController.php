<?php
// app/Http/Controllers/Admin/MeterController.php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\MeterStoreRequest;
use App\Http\Requests\MeterUpdateRequest;
use App\Models\Company;
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

        // ★会社スコープ（engagements ベースの暫定実装）
        $companyIds = $isSuper
        ? collect()
        : $request->user()->engagements()->active()->pluck('company_id');

        $q = Meter::query()->with(['facility.company'])->orderBy('code');

        if (! $isSuper) {
            if ($companyIds->isEmpty()) {
                $q->whereRaw('1=0');
            } else {
                $q->whereHas('facility', fn($w) => $w->whereIn('company_id', $companyIds));
            }
        }

        $s   = trim((string) $request->get('search', ''));
        $cid = $request->get('company_id');

        if ($s !== '') {
            $q->where(function ($w) use ($s) {
                $w->where('code', 'like', "%{$s}%")
                    ->orWhere('name', 'like', "%{$s}%");
            });
        }
        if ($cid) {
            if ($isSuper || $companyIds->contains((int) $cid)) {
                $q->whereHas('facility', fn($w) => $w->where('company_id', $cid));
            } else {
                $q->whereRaw('1=0');
            }
        }

        // 会社プルダウン
        $companiesQuery = Company::query()->orderBy('name')->select(['id', 'name']);
        if (! $isSuper) {
            $companiesQuery->whereIn('id', $companyIds);
        }

        return Inertia::render('Meters/Index', [
            'filters'   => ['search' => $s, 'company_id' => $cid],
            'companies' => $companiesQuery->get(),
            'meters'    => $q->paginate(20)->withQueryString(),
            'can'       => ['create' => $user->can('create', Meter::class)],
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
