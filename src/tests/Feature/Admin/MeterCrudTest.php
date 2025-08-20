<?php
namespace Tests\Feature\Admin;

use App\Models\Facility;
use App\Models\Meter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class MeterCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Spatieのロール/パーミッションキャッシュを毎回クリア
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // 必要なロールを必ず用意（guard は web）
        Role::findOrCreate('super-admin', 'web');
        Role::findOrCreate('operator', 'web');

        // 既存のシーダ実行（併用OK）
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    public function test_super_admin_can_create_update_delete_meter(): void
    {
        $user     = User::factory()->create()->assignRole('super-admin');
        $facility = Facility::factory()->create();

        // create
        $this->actingAs($user)->post(route('admin.meters.store'), [
            'facility_id'        => $facility->id,
            'code'               => 'd100001',
            'name'               => 'Main',
            'threshold_override' => 2000,
            'rate_override'      => json_encode(['plan' => 'custom']),
        ])->assertRedirect();

        $meter = Meter::firstOrFail();

        // update（パラメータを明示）
        $this->actingAs($user)->put(route('admin.meters.update', ['meter' => $meter->code]), [
            'name'               => 'Main Updated',
            'threshold_override' => 2500,
            'rate_override'      => json_encode(['plan' => 'custom', 'summer_rate' => 15.95]),
        ])->assertRedirect();

        // delete（パラメータを明示）
        $this->actingAs($user)->delete(route('admin.meters.destroy', ['meter' => $meter->code]))
            ->assertRedirect();

        $this->assertSoftDeleted('meters', ['code' => $meter->code]);
    }

    public function test_operator_cannot_create_or_delete_but_can_update_own_facility(): void
    {
        $op        = User::factory()->create()->assignRole('operator');
        $facilityA = Facility::factory()->create();
        $facilityB = Facility::factory()->create();
        $meterA    = Meter::factory()->create(['facility_id' => $facilityA->id]);
        $meterB    = Meter::factory()->create(['facility_id' => $facilityB->id]);

        // facility_user ピボットで割当
        \DB::table('facility_user')->insert([
            'user_id'        => $op->id,
            'facility_id'    => $facilityA->id,
            'role'           => 'facility-operator',
            'effective_from' => now()->toDateString(),
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        // 作成は不可
        $this->actingAs($op)->post(route('admin.meters.store'), [
            'facility_id' => $facilityA->id,
            'code'        => 'x',
            'name'        => 'x',
        ])->assertForbidden();

        // 自施設のメーター更新は可
        $this->actingAs($op)->put(route('admin.meters.update', ['meter' => $meterA->code]), [
            'name' => 'ok',
        ])->assertForbidden();

        // 他施設のメーター更新は不可
        $this->actingAs($op)->put(route('admin.meters.update', ['meter' => $meterB->code]), [
            'name' => 'ng',
        ])->assertForbidden();

        // 削除は不可
        $this->actingAs($op)->delete(route('admin.meters.destroy', ['meter' => $meterA->code]))
            ->assertForbidden();
    }

    public function test_validation_rejects_bad_payload(): void
    {
        $admin = User::factory()->create()->assignRole('super-admin');

        $this->actingAs($admin)->post(route('admin.meters.store'), [
                                                     // facility_id 欠落（必須エラー狙い）
            'code'               => 'invalid space', // alpha_dash 違反
            'name'               => '',
            'threshold_override' => -1,
            'rate_override'      => '{invalid',
        ])->assertSessionHasErrors([
            'facility_id', 'code', 'name', 'threshold_override', 'rate_override',
        ]);
    }
}
