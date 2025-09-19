<?php

namespace Database\Seeders;

use App\Models\AppSettings;
use App\Models\Family;
use App\Models\FamilyMember;
use Illuminate\Database\Seeder;

class AppSettingsSeeder extends Seeder
{
    public function run(): void
    {
        // Get all children from families
        $childrenInFamilies = FamilyMember::where('role', 'child')
            ->with(['family', 'user'])
            ->get();

        foreach ($childrenInFamilies as $familyMember) {
            AppSettings::create([
                'family_id' => $familyMember->family_id,
                'child_user_id' => $familyMember->user_id,
                'notification_filters' => AppSettings::getDefaultNotificationFilters(),
                'blocked_keywords' => [
                    'drugs',
                    'violence',
                    'inappropriate',
                    'danger',
                    'skip school',
                    'party tonight',
                    'alcohol',
                    'cigarettes',
                    'meet stranger'
                ],
                'location_update_interval' => 60,
                'screen_mirroring_enabled' => false,
                'geofence_settings' => AppSettings::getDefaultGeofenceSettings(),
            ]);
        }
    }
}
