<?php

namespace Database\Seeders;

use App\Models\Family;
use App\Models\Geofence;
use Illuminate\Database\Seeder;

class GeofenceSeeder extends Seeder
{
    public function run(): void
    {
        $doeFamily = Family::where('name', 'Doe Family')->first();

        // Safe zones for demo family
        Geofence::create([
            'family_id' => $doeFamily->id,
            'name' => 'Home',
            'center_latitude' => -7.2575,
            'center_longitude' => 112.7521,
            'radius' => 100,
            'type' => 'safe',
            'is_active' => true,
        ]);

        Geofence::create([
            'family_id' => $doeFamily->id,
            'name' => 'School',
            'center_latitude' => -7.2819,
            'center_longitude' => 112.7953,
            'radius' => 200,
            'type' => 'safe',
            'is_active' => true,
        ]);

        Geofence::create([
            'family_id' => $doeFamily->id,
            'name' => 'Grandma House',
            'center_latitude' => -7.2456,
            'center_longitude' => 112.7389,
            'radius' => 50,
            'type' => 'safe',
            'is_active' => true,
        ]);

        // Danger zones
        Geofence::create([
            'family_id' => $doeFamily->id,
            'name' => 'Mall Area (Late Night)',
            'center_latitude' => -7.2892,
            'center_longitude' => 112.8012,
            'radius' => 300,
            'type' => 'danger',
            'is_active' => true,
        ]);

        // Create geofences for other families
        $otherFamilies = Family::where('id', '>', 1)->get();
        foreach ($otherFamilies as $family) {
            // Home zone
            Geofence::create([
                'family_id' => $family->id,
                'name' => 'Home',
                'center_latitude' => -7.2575 + (rand(-100, 100) * 0.001),
                'center_longitude' => 112.7521 + (rand(-100, 100) * 0.001),
                'radius' => rand(50, 200),
                'type' => 'safe',
                'is_active' => true,
            ]);

            // School zone
            Geofence::create([
                'family_id' => $family->id,
                'name' => 'School',
                'center_latitude' => -7.2819 + (rand(-50, 50) * 0.001),
                'center_longitude' => 112.7953 + (rand(-50, 50) * 0.001),
                'radius' => rand(100, 300),
                'type' => 'safe',
                'is_active' => true,
            ]);
        }
    }
}
