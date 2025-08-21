<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Meter;

class GraphApiContractTest extends TestCase
{
    use RefreshDatabase;

    private string $meterCode;

    protected function setUp(): void
    {
        parent::setUp();

        if (class_exists(Meter::class) && method_exists(Meter::class, 'factory')) {
            $m = Meter::factory()->create([
                'code' => 't-api-meter-1',
                // 必要なら not-null のカラムを追加
                // 'threshold' => 300,
                // 'rate' => 1.0,
            ]);
            $this->meterCode = $m->code;
        } else {
            $this->meterCode = env('TEST_METER_CODE', 'd100318');
        }
    }

    /** @test */
    public function series_endpoint_returns_minimal_contract(): void
    {
        $resp = $this->getJson("/api/v1/meters/{$this->meterCode}/series?bucket=30m");
        $resp->assertStatus(200);

        $json = $resp->json();
        foreach (['meter','tz','bucket','view','series'] as $k) {
            $this->assertArrayHasKey($k, $json, "missing key: {$k}");
        }
        $this->assertIsArray($json['series']);

        if (!empty($json['series'])) {
            $first = $json['series'][0];
            $this->assertArrayHasKey('label', $first);
            $this->assertArrayHasKey('data', $first);
            $this->assertIsArray($first['data']);

            if (!empty($first['data'])) {
                $p = $first['data'][0]; // [iso, number|null]
                $this->assertIsArray($p);
                $this->assertCount(2, $p);
                $this->assertIsString($p[0]);
                $this->assertTrue(is_numeric($p[1]) || is_null($p[1]));
            }
        }
    }

    /** @test */
    public function demand_endpoint_returns_minimal_contract(): void
    {
        $resp = $this->getJson("/api/v1/meters/{$this->meterCode}/demand");
        $resp->assertStatus(200);

        $json = $resp->json();
        foreach (['meter','tz','window','series','threshold'] as $k) {
            $this->assertArrayHasKey($k, $json, "missing key: {$k}");
        }
        foreach (['instant','accumulation','predict'] as $k) {
            $this->assertArrayHasKey($k, $json['series'], "series missing: {$k}");
            $this->assertIsArray($json['series'][$k]);
        }
    }
}
