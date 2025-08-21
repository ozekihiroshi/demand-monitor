<?php
// app/Support/DemandTable.php
namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class DemandTable
{
    private static ?bool $has = null;

    public static function has(): bool
    {
        return self::$has ??= Schema::hasTable('demand');
    }

    /** @return array<array{date:int,data:float}> */
    public static function read(string $key, int $from, int $to): array
    {
        if (!self::has()) return [];
        return DB::table('demand')
            ->select(['date','data'])
            ->where('demand_ip', $key)
            ->where('delete_flag', 0)
            ->whereBetween('date', [$from, $to])
            ->orderBy('date')
            ->get()
            ->map(fn($r)=>['date'=>(int)$r->date,'data'=>(float)$r->data])
            ->all();
    }
}

