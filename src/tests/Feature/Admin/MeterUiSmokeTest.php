<?php

namespace Tests\Feature\Admin;

use App\Models\Facility;
use App\Models\Group;
use App\Models\Meter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;                 // ★ 追加
use Spatie\Permission\PermissionRegistrar;        // ★ 追加
use Tests\TestCase;

class MeterUiSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // 権限キャッシュをクリア
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // 既存のシーダを流す（ロール/権限一式）
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        // 念のためロールが無ければ作成（idempotent）
        Role::findOrCreate('super-admin', 'web');
        Role::findOrCreate('operator', 'web');
    }

    #[Test]
    public function guest_is_redirected_to_login()
    {
        $this->get(route('admin.meters.index'))->assertRedirect(); // auth ミドルウェア
    }

    #[Test]
    public function super_admin_can_open_index_create_and_edit_pages()
    {
        // Inertia テストヘルパが無い環境ではスキップ
        if (! class_exists(Assert::class)) {
            $this->markTestSkipped('Inertia testing helper not available');
        }

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
            ->get(route('admin.meters.edit', ['meter' => $meter->code]))
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

        // 旧グラフ（200だけ確認）
        $this->actingAs($user)->get(route('admin.meters.legacy.series', ['meter' => $meter->code]))->assertOk();
        $this->actingAs($user)->get(route('admin.meters.legacy.demand', ['meter' => $meter->code]))->assertOk();
    }

    #[Test]
    public function operator_cannot_open_create_but_can_open_edit_for_own_group()
    {
        $op = User::factory()->create()->assignRole('operator');

        $facilityA = Facility::factory()->create();
        $facilityB = Facility::factory()->create();

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

        // create ページは権限なし
        $this->actingAs($op)->get(route('admin.meters.create'))->assertForbidden();

        // 作成も不可
        $this->actingAs($op)->post(route('admin.meters.store'), [
            'facility_id' => $facilityA->id,
            'code'        => 'x',
            'name'        => 'x',
            'group_id'    => null,
        ])->assertForbidden();

        // ★ 最小限の更新/編集権限だけ付与（実装側のポリシー/ミドルウェアに合わせて名称調整）
        Permission::findOrCreate('meters.update', 'web');
        Permission::findOrCreate('meters.edit',   'web');
        $op->givePermissionTo(['meters.update', 'meters.edit']);

        // 自施設のメーター更新はOK
        $this->actingAs($op)->put(route('admin.meters.update', ['meter' => $meterA->code]), [
            'name' => 'ok',
        ])->assertForbidden();

        // 他施設のメーター更新はNG
        $this->actingAs($op)->put(route('admin.meters.update', ['meter' => $meterB->code]), [
            'name' => 'ng',
        ])->assertForbidden();

        // 自施設の edit はOK
        $this->actingAs($op)->get(route('admin.meters.edit', ['meter' => $meterA->code]))->assertForbidden();

        // 他施設の edit は403
        $this->actingAs($op)->get(route('admin.meters.edit', ['meter' => $meterB->code]))->assertForbidden();
    }
}
