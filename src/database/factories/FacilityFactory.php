<?php

namespace Database\Factories;

use App\Models\Facility;
use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Organization;

class FacilityFactory extends Factory
{
    protected $model = Facility::class;

    public function definition(): array
    {
        $orgName = $this->faker->company();

        return [
            'name'            => $this->faker->company(),
            'organization_id' => Organization::factory(),
        ];
    }
}

