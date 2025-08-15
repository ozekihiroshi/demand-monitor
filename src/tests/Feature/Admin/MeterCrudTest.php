<?php
// tests/Feature/Admin/MeterCrudTest.php
namespace Tests\Feature\Admin;

use App\Models\Facility;
use App\Models\Group;
use App\Models\Meter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeterCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        // ここで roles/permissions のシーディングやユーザー作成を実施
        // e.g. $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_super_admin_can_create_update_delete_meter(): void
    {
        $user     = User::factory()->create()->assignRole('super-admin');
        $group    = Group::factory()->create();
        $facility = Facility::factory()->create();

        $this->actingAs($user)
            ->post(route('admin.meters.store'), [
                'facility_id'        => $facility->id,
                'code'               => 'd100001',
                'name'               => 'Main',
                'group_id'           => $group->id,
                'threshold_override' => 2000,
                'rate_override'      => json_encode(['plan' => 'custom']),
            ])->assertRedirect();

        $meter = Meter::first();
        $this->actingAs($user)
            ->put(route('admin.meters.update', $meter->code), [
                'name'               => 'Main Updated',
                'group_id'           => $group->id,
                'threshold_override' => 2500,
                'rate_override'      => json_encode(['plan' => 'custom', 'summer_rate' => 15.95]),
            ])->assertRedirect();

        $this->actingAs($user)
            ->delete(route('admin.meters.destroy', $meter->code))
            ->assertRedirect();

        $this->assertSoftDeleted('meters', ['code' => $meter->code]);
    }

    public function test_operator_cannot_create_or_delete_but_can_update_own_group(): void
    {
        $groupA = Group::factory()->create();
        $groupB = Group::factory()->create();
        $meterA = Meter::factory()->create(['group_id' => $groupA->id]);
        $meterB = Meter::factory()->create(['group_id' => $groupB->id]);

        $op = User::factory()->create()->assignRole('operator');
        $op->groups()->sync([$groupA->id]); // 所属

        $facility = \App\Models\Facility::factory()->create();

        $this->actingAs($op)->post(route('admin.meters.store'), [
            'facility_id' => $facility->id,
            'code'        => 'x',
            'name'        => 'x',
            'group_id'    => $groupA->id,
        ])->assertForbidden();

        $this->actingAs($op)->put(route('admin.meters.update', $meterA->code), [
            'name' => 'ok', 'group_id' => $groupA->id,
        ])->assertRedirect();

        $this->actingAs($op)->put(route('admin.meters.update', $meterB->code), [
            'name' => 'ng', 'group_id' => $groupB->id,
        ])->assertForbidden();

        $this->actingAs($op)->delete(route('admin.meters.destroy', $meterA->code))
            ->assertForbidden();
    }

    public function test_validation_rejects_bad_payload(): void
    {
        $admin = User::factory()->create()->assignRole('super-admin');
        $group = Group::factory()->create();

        $this->actingAs($admin)->post(route('admin.meters.store'), [
            'code'               => 'invalid space', // alpha_dash 違反
            'name'               => '',
            'group_id'           => 9999,
            'threshold_override' => -1,
            'rate_override'      => '{invalid',
        ])->assertSessionHasErrors(['code', 'name', 'group_id', 'threshold_override', 'rate_override']);
    }
}
