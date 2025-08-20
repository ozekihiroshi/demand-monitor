<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class CompanyFactory extends Factory
{
    protected $model = Company::class;

    public function definition(): array
    {
        return [
            "organization_id" => Organization::factory(),
            "name" => $this->faker->company(),
            "slug" => $this->faker->unique()->slug(2),
        ];
    }
}
