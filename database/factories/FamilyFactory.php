<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class FamilyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->lastName() . ' Family',
            'family_code' => Str::random(3) . rand(10000, 99999),
            'created_by' => User::factory(),
        ];
    }
}
