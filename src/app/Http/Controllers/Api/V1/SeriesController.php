<?php
// app/Http/Controllers/Api/V1/SeriesController.php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Domain\Timeseries\MeterTimeseriesRepository;
use Illuminate\Http\Request;

class SeriesController extends Controller
{
    public function __construct(
        private readonly MeterTimeseriesRepository $repo
    ) {}

    // 30分/1分オーバーレイ
    public function series(Request $request, string $code)
    {
        $bucket = strtolower((string)$request->query('bucket', '30m'));
        if (!in_array($bucket, ['30m','1m'], true)) {
            $bucket = '30m';
        }
        $days   = max(1, min(14, (int)$request->query('days', 8)));
        $offset = max(0, (int)$request->query('offset', 0));

        // 任意上書き（キャッシュキーに反映される）
        $opts = [];
        if ($request->filled('rate')) {
            $r = (float)$request->query('rate');
            if ($r >= 0) $opts['rate'] = $r;
        }
        if ($request->filled('goal')) {
            $opts['goal'] = (int)$request->query('goal');
        }

        return response()->json(
            $this->repo->getSeries($code, $bucket, $days, $offset, $opts)
        );
    }

    // 当日リアルタイム（瞬時/積算/予測）
    public function demand(Request $request, string $code)
    {
        $opts = [];
        if ($request->filled('rate')) {
            $r = (float)$request->query('rate');
            if ($r >= 0) $opts['rate'] = $r;
        }
        if ($request->filled('threshold')) {
            $opts['threshold'] = (int)$request->query('threshold');
        }

        return response()->json(
            $this->repo->getDemandToday($code, $opts)
        );
    }
}
