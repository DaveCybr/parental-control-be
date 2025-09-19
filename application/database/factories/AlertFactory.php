<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AlertFactory extends Factory
{
    public function definition(): array
    {
        $types = ['geofence', 'emergency', 'content', 'battery'];
        $priorities = ['critical', 'high', 'medium', 'low'];

        return [
            'child_user_id' => User::factory(),
            'type' => fake()->randomElement($types),
            'priority' => fake()->randomElement($priorities),
            'title' => fake()->sentence(3),
            'message' => fake()->sentence(8),
            'data' => [],
            'is_read' => fake()->boolean(30), // 30% chance of being read
            'triggered_at' => fake()->dateTimeBetween('-1 week', 'now'),
        ];
    }
}
