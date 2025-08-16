<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Engagement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class EngagementFactory extends Factory
{
    protected $model = Engagement::class;

    public function definition(): array
    {
        return [
            'user_id'        => User::factory(),
            'company_id'     => Company::factory(),
            'role'           => 'org-admin',
            'status'         => 'active',
            'effective_from' => now()->toDateString(),
            'effective_to'   => null,
        ];
    }
}


