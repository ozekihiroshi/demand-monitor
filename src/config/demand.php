<?php

return [
    // 切替日時（null=常にlegacy）。例: '2025-12-01 00:00:00'
    'cutover_at' => env('DEMAND_CUTOVER_AT'),

    // 秒。シリーズ/需要の結果を短期キャッシュ
    'cache_ttl' => (int) env('DEMAND_CACHE_TTL', 30),
];


