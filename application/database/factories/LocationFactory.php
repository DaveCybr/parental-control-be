<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class LocationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'latitude' => fake()->latitude(-7.5, -7.0), // Surabaya area
            'longitude' => fake()->longitude(112.5, 113.0), // Surabaya area
            'accuracy' => fake()->randomFloat(2, 5.0, 50.0),
            'battery_level' => fake()->numberBetween(10, 100),
            'timestamp' => fake()->dateTimeBetween('-1 week', 'now'),
        ];
    }
}
