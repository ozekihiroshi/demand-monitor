<?php
// tests/Feature/Admin/MeterUiSmokeTest.php
namespace Tests\Feature\Admin;

use App\Models\Facility;
use App\Models\Group;
use App\Models\Meter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MeterUiSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    #[Test]
    public function guest_is_redirected_to_login()
    {
        $this->get(route('admin.meters.index'))->assertRedirect(); // authミドルウェア
    }

    #[Test]
    public function super_admin_can_open_index_create_and_edit_pages()
    {
        $user = User::factory()->create()->assignRole('super-admin');

        // Index
        $this->actingAs($user)
            ->get(route('admin.meters.index'))
            ->assertOk()
            ->assertInertia(fn(Assert $page) =>
                $page->component('Meters/Index')
                    ->has('filters')
                    ->has('companies')
                    ->has('meters.data')
            );

        // Create
        $this->actingAs($user)
            ->get(route('admin.meters.create'))
            ->assertOk()
            ->assertInertia(fn(Assert $page) =>
                $page->component('Meters/Form')
                    ->where('meter', null)
                    ->has('groups')
            );

        // Edit
        $group    = Group::factory()->create();
        $facility = Facility::factory()->create();
        $meter    = Meter::factory()->create([
            'group_id'    => $group->id,
            'facility_id' => $facility->id,
        ]);

        $this->actingAs($user)
            ->get(route('admin.meters.edit', $meter->code))
            ->assertOk()
            ->assertInertia(fn(Assert $page) =>
                $page->component('Meters/Form')
                    ->has('meter', fn(Assert $m) =>
                        $m->where('code', $meter->code)
                            ->where('name', $meter->name)
                            ->where('group_id', $meter->group_id)
                            ->etc()
                    )
                    ->has('groups')
            );
    }

    #[Test]
    public function legacy_series_and_demand_pages_render()
    {
        $user = User::factory()->create()->assignRole('super-admin');

        $group    = Group::factory()->create();
        $facility = Facility::factory()->create();
        $meter    = Meter::factory()->create([
            'group_id'    => $group->id,
            'facility_id' => $facility->id,
        ]);

        // 旧グラフ（埋め込み用URL）— 200だけ確認（中身の断定はしない）
        $this->actingAs($user)->get(route('admin.meters.legacy.series', $meter->code))->assertOk();
        $this->actingAs($user)->get(route('admin.meters.legacy.demand', $meter->code))->assertOk();
    }

    #[Test]
    public function operator_cannot_open_create_but_can_open_edit_for_own_group()
    {

        $op = User::factory()->create()->assignRole('operator');

        $facilityA = \App\Models\Facility::factory()->create();
        $facilityB = \App\Models\Facility::factory()->create();

        $meterA = Meter::factory()->create(['facility_id' => $facilityA->id]);
        $meterB = Meter::factory()->create(['facility_id' => $facilityB->id]);

        // 施設割当（facility_user）でスコープ付与
        \DB::table('facility_user')->insert([
            'user_id'        => $op->id,
            'facility_id'    => $facilityA->id,
            'role'           => 'facility-operator',
            'effective_from' => now()->toDateString(),
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        // create ページ（権限なし）
        $this->actingAs($op)->get(route('admin.meters.create'))->assertForbidden();

        $this->actingAs($op)->post(route('admin.meters.store'), [
            'facility_id' => $facilityA->id,
            'code'        => 'x',
            'name'        => 'x',
            'group_id'    => null, // 互換不要なら除去。現行ルールに合わせて調整
        ])->assertForbidden();

        // 自施設のメーター更新はOK
        $this->actingAs($op)->put(route('admin.meters.update', $meterA->code), [
            'name' => 'ok',
        ])->assertRedirect();

        // 他施設のメーター更新はNG
        $this->actingAs($op)->put(route('admin.meters.update', $meterB->code), [
            'name' => 'ng',
        ])->assertForbidden();

        // 自グループ edit はOK
        $this->actingAs($op)->get(route('admin.meters.edit', $meterA->code))->assertOk();

        // 他グループ edit は403
        $this->actingAs($op)->get(route('admin.meters.edit', $meterB->code))->assertForbidden();
    }
}
