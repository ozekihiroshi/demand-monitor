<?php
namespace Tests\Feature;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TimeseriesApiTest extends TestCase
{
    use RefreshDatabase;

    /** テスト前に legacy 接続を sqlite ファイルへ切替＆demand テーブル作成 */
    protected function setUp(): void
    {
        parent::setUp();

        // ---- legacy 接続をテスト用 sqlite ファイルへ切替 ----
        $path = storage_path('framework/testing/legacy.sqlite');
        @mkdir(dirname($path), 0777, true);
        if (! file_exists($path)) {
            touch($path);
        }
        \Config::set('database.connections.legacy', [
            'driver'   => 'sqlite',
            'database' => $path,
            'prefix'   => '',
        ]);

        // ---- legacy 側の最小テーブル（demand / user）作成 ----
        $legacySchema = \DB::connection('legacy')->getSchemaBuilder();

        if (! $legacySchema->hasTable('demand')) {
            $legacySchema->create('demand', function ($table) {
                $table->integer('date');     // UTC epoch 秒
                $table->integer('data');     // パルス
                $table->string('demand_ip'); // 旧キー（= legacy_uid or code）
                $table->tinyInteger('delete_flag')->default(0);
            });
        }
        if (! $legacySchema->hasTable('user')) {
            $legacySchema->create('user', function ($table) {
                $table->string('uid')->primary();
                $table->integer('rate')->nullable();
                $table->integer('shikiichi')->nullable();
            });
        }

        // ---- app（デフォルト接続）側：facility が必要なら作って facility_id を埋める ----
        // RefreshDatabase が既存の全マイグレーションを流すため、meters に facility_id がある前提に対応
        $needFacility = \Schema::hasColumn('meters', 'facility_id');

        $facilityId = null;
        if ($needFacility) {
            // organizations / facilities がある前提の最小レコードを投入
            if (\Schema::hasTable('organizations') && \Schema::hasTable('facilities')) {
                $now   = now();
                $orgId = \DB::table('organizations')->insertGetId([
                    'name'       => 'Test Org',
                    'slug'       => 'test-org',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $facilityId = \DB::table('facilities')->insertGetId([
                    'organization_id' => $orgId,
                    'name'            => 'Main Site',
                    'timezone'        => 'Asia/Tokyo',
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ]);
            }
        }

        // ---- meters 1件投入（override で rate/threshold をカバー）----
        $row = [
            'code'               => 't001',
            'legacy_uid'         => null, // legacy は demand_ip='t001' を使う
            'name'               => 'テスト計器',
            'group_id'           => null,
            'rate_override'      => 1200,
            'threshold_override' => 300,
            'created_at'         => now(),
            'updated_at'         => now(),
        ];
        if ($needFacility && $facilityId) {
            $row['facility_id'] = $facilityId;
        }
        \DB::table('meters')->insert($row);
    }

    public function test_ioc_binding_resolves_switching_repo(): void
    {
        $impl = app(\App\Domain\Timeseries\MeterTimeseriesRepository::class);
        $this->assertInstanceOf(\App\Domain\Timeseries\SwitchingMeterTimeseriesRepository::class, $impl);
    }

    public function test_series_endpoint_returns_json_structure(): void
    {
        // 当日分の 1m データを少しだけ投入（UTC で）
        Carbon::setTestNow(Carbon::parse('2025-08-13 08:05:00', 'Asia/Tokyo'));
        $jstBase = Carbon::now('Asia/Tokyo')->startOfDay();
        // 08:01 と 08:02 分終端
        foreach ([1, 2] as $m) {
            $tEndUtc = $jstBase->copy()->hour(8)->minute($m)->second(0)->utc()->timestamp;
            DB::connection('legacy')->table('demand')->insert([
                'date'        => $tEndUtc,
                'data'        => 100 + $m, // ダミーパルス
                'demand_ip'   => 't001',
                'delete_flag' => 0,
            ]);
        }

        $res = $this->getJson('/api/v1/meters/t001/series?bucket=1m');
        $res->assertOk()
            ->assertJsonStructure([
                'meter', 'bucket', 'days', 'offset', 'rate', 'goal_kw', 'tz', 'series' => [
                    ['label', 'visible', 'color', 'data'],
                ],
            ])
            ->assertJsonPath('meter', 't001')
            ->assertJsonPath('bucket', '1m');

        Carbon::setTestNow(); // reset
    }

    public function test_demand_endpoint_returns_predict_series(): void
    {
        // 現在枠：08:00-08:30 を固定
        Carbon::setTestNow(Carbon::parse('2025-08-13 08:10:00', 'Asia/Tokyo'));

        // 08:01〜08:05 分終端にデータ投入
        $segStart = Carbon::now('Asia/Tokyo')->minute(0)->second(0);
        for ($m = 1; $m <= 5; $m++) {
            $tEndUtc = $segStart->copy()->addMinutes($m)->utc()->timestamp;
            DB::connection('legacy')->table('demand')->insert([
                'date'        => $tEndUtc,
                'data'        => 120 + $m, // ダミーパルス
                'demand_ip'   => 't001',
                'delete_flag' => 0,
            ]);
        }

        $res = $this->getJson('/api/v1/meters/t001/demand');
        $res->assertOk()
            ->assertJsonStructure([
                'meter', 'threshold', 'tz', 'window' => ['start', 'end'],
                'series' => ['instant', 'accumulation', 'predict'],
                'predict_last', 'will_exceed_threshold', 'max_point',
            ])
            ->assertJsonPath('meter', 't001');

        $json = $res->json();
        $this->assertIsArray($json['series']['predict']);
        $this->assertGreaterThan(0, count($json['series']['predict']), 'predict should not be empty');

        Carbon::setTestNow();
    }

    public function test_unregistered_code_returns_404(): void
    {
        $this->getJson('/api/v1/meters/unknown999/series?bucket=30m')->assertNotFound();
        $this->getJson('/api/v1/meters/unknown999/demand')->assertNotFound();
    }
}
