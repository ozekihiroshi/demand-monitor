<?php

namespace Database\Factories;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class OrganizationFactory extends Factory
{
    protected $model = Organization::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->company();

        return [
            'name' => $name,
            // organizations.slug が NOT NULL のため必ず埋める
            'slug' => Str::slug($name.'-'.$this->faker->unique()->numerify('###')),
        ];
    }
}
