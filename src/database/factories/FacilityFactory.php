<?php

namespace Database\Factories;

use App\Models\Facility;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

class FacilityFactory extends Factory
{
    protected $model = Facility::class;

    public function definition(): array
    {
        // 会社を先に作成し、organization を継承して整合性を保つ
        $company = Company::factory()->create();

        return [
            "company_id"       => $company->id,
            "organization_id"  => $company->organization_id,
            "name"             => $this->faker->company(),
            "main_meter_code"  => null,
        ];
    }
}
