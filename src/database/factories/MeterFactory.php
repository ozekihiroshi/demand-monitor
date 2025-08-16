<?php

namespace Database\Factories;

use App\Models\Meter;
use App\Models\Group;
use App\Models\Facility;
use Illuminate\Database\Eloquent\Factories\Factory;

class MeterFactory extends Factory
{
    protected $model = Meter::class;

    public function definition(): array
    {
        return [
            'facility_id' => Facility::factory(),
            'code' => 'd' . $this->faker->unique()->numberBetween(100000, 999999),
            'name' => $this->faker->streetName(),
            'kind'               => 'consumption',
            'group_id' => Group::factory(),
            'threshold_override' => null,
            'rate_override' => null,
        ];
    }
}
