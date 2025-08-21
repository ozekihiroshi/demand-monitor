<?php

namespace Tests\Unit\Policy;

use Tests\TestCase;
use Illuminate\Support\Facades\Gate;
use App\Models\User;
use App\Models\Facility;
// use App\Policies\FacilityPolicy; // 直接呼ぶなら use する

class FacilityScopePolicyTest extends TestCase
{
    /** @test */
    public function facility_user_can_view_own_facility_but_not_others(): void
    {
        // Arrange: 施設A/B とユーザA/B（Aは施設Aに属す、Bは施設Bに属す）
        $facilityA = Facility::factory()->create();
        $facilityB = Facility::factory()->create();

        $userA = User::factory()->create();
        $userB = User::factory()->create();

        // ↓ 実環境の所属付与ロジックに合わせて調整（pivot名・関数名など）
        $facilityA->users()->attach($userA->id);
        $facilityB->users()->attach($userB->id);

        // Act + Assert
        $this->assertTrue(Gate::forUser($userA)->allows('view', $facilityA));
        $this->assertFalse(Gate::forUser($userA)->allows('view', $facilityB));

        $this->assertTrue(Gate::forUser($userB)->allows('view', $facilityB));
        $this->assertFalse(Gate::forUser($userB)->allows('view', $facilityA));
    }

    /** @test */
    public function company_user_cannot_cross_facility_boundary(): void
    {
        // 会社配下に施設をぶら下げているなら、Company 経由の範囲制御も 1 ケース追加
        // Company モデル／Gate 名称は環境に合わせて調整してください
        $this->markTestIncomplete('Company スコープの Gate/Policy 名称と関連をプロジェクト定義に合わせて実装してください。');
    }
}


