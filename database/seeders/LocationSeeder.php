<?php

namespace Database\Seeders;

use App\Models\Location;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class LocationSeeder extends Seeder
{
    public function run(): void
    {
        $children = User::where('role', 'child')->get();

        foreach ($children as $child) {
            // Create location history for last 7 days
            for ($day = 7; $day >= 0; $day--) {
                $date = Carbon::now()->subDays($day);

                // Morning at home
                Location::create([
                    'user_id' => $child->id,
                    'latitude' => -7.2575 + (rand(-10, 10) * 0.0001),
                    'longitude' => 112.7521 + (rand(-10, 10) * 0.0001),
                    'accuracy' => rand(5, 20),
                    'battery_level' => rand(80, 100),
                    'timestamp' => $date->copy()->setHour(7)->setMinute(rand(0, 30)),
                ]);

                // At school
                Location::create([
                    'user_id' => $child->id,
                    'latitude' => -7.2819 + (rand(-5, 5) * 0.0001),
                    'longitude' => 112.7953 + (rand(-5, 5) * 0.0001),
                    'accuracy' => rand(10, 30),
                    'battery_level' => rand(60, 90),
                    'timestamp' => $date->copy()->setHour(9)->setMinute(rand(0, 30)),
                ]);

                // Afternoon activities
                Location::create([
                    'user_id' => $child->id,
                    'latitude' => -7.2456 + (rand(-15, 15) * 0.0001),
                    'longitude' => 112.7389 + (rand(-15, 15) * 0.0001),
                    'accuracy' => rand(8, 25),
                    'battery_level' => rand(40, 80),
                    'timestamp' => $date->copy()->setHour(15)->setMinute(rand(0, 59)),
                ]);

                // Evening at home
                Location::create([
                    'user_id' => $child->id,
                    'latitude' => -7.2575 + (rand(-8, 8) * 0.0001),
                    'longitude' => 112.7521 + (rand(-8, 8) * 0.0001),
                    'accuracy' => rand(5, 15),
                    'battery_level' => rand(20, 60),
                    'timestamp' => $date->copy()->setHour(18)->setMinute(rand(0, 59)),
                ]);
            }
        }
    }
}
