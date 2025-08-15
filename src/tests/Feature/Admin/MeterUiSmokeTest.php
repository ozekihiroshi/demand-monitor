<?php
// tests/Feature/Admin/MeterUiSmokeTest.php
namespace Tests\Feature\Admin;

use App\Models\User;
use App\Models\Group;
use App\Models\Meter;
use App\Models\Facility;
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

    /** @test */
    public function guest_is_redirected_to_login()
    {
        $this->get(route('admin.meters.index'))->assertRedirect(); // authミドルウェア
    }

    /** @test */
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
                     ->has('groups')
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
        $group = Group::factory()->create();
        $facility = Facility::factory()->create();
        $meter = Meter::factory()->create([
            'group_id' => $group->id,
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

    /** @test */
    public function legacy_series_and_demand_pages_render()
    {
        $user = User::factory()->create()->assignRole('super-admin');

        $group = Group::factory()->create();
        $facility = Facility::factory()->create();
        $meter = Meter::factory()->create([
            'group_id' => $group->id,
            'facility_id' => $facility->id,
        ]);

        // 旧グラフ（埋め込み用URL）— 200だけ確認（中身の断定はしない）
        $this->actingAs($user)->get(route('admin.meters.legacy.series', $meter->code))->assertOk();
        $this->actingAs($user)->get(route('admin.meters.legacy.demand', $meter->code))->assertOk();
    }

    /** @test */
    public function operator_cannot_open_create_but_can_open_edit_for_own_group()
    {
        $op = User::factory()->create()->assignRole('operator');
        $groupA = Group::factory()->create();
        $groupB = Group::factory()->create();
        $op->groups()->sync([$groupA->id]);

        $facility = Facility::factory()->create();
        $meterA = Meter::factory()->create(['group_id' => $groupA->id, 'facility_id'=>$facility->id]);
        $meterB = Meter::factory()->create(['group_id' => $groupB->id, 'facility_id'=>$facility->id]);

        // create ページ（権限なし）
        $this->actingAs($op)->get(route('admin.meters.create'))->assertForbidden();

        // 自グループ edit はOK
        $this->actingAs($op)->get(route('admin.meters.edit', $meterA->code))->assertOk();

        // 他グループ edit は403
        $this->actingAs($op)->get(route('admin.meters.edit', $meterB->code))->assertForbidden();
    }
}


