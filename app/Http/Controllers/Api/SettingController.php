<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppSettings;
use App\Models\FamilyMember;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SettingController extends Controller
{
    public function get($childId): JsonResponse
    {
        // Verify parent-child relationship
        if (!$this->verifyParentChildRelationship(auth()->user()->id, $childId)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        $familyMember = FamilyMember::where('user_id', auth()->user()->id)->first();

        $settings = AppSettings::where('family_id', $familyMember->family_id)
            ->where('child_user_id', $childId)
            ->first();

        if (!$settings) {
            // Create default settings
            $settings = AppSettings::create([
                'family_id' => $familyMember->family_id,
                'child_user_id' => $childId,
                'notification_filters' => [
                    'whatsapp' => true,
                    'instagram' => true,
                    'telegram' => true,
                    'facebook' => true,
                    'snapchat' => true,
                    'tiktok' => true
                ],
                'blocked_keywords' => [],
                'location_update_interval' => 60,
                'screen_mirroring_enabled' => false,
                'geofence_settings' => [
                    'alert_on_exit' => true,
                    'alert_on_enter_danger' => true,
                    'auto_check_interval' => 30
                ]
            ]);
        }

        return response()->json([
            'success' => true,
            'settings' => $settings
        ]);
    }

    public function update($childId, Request $request): JsonResponse
    {
        $request->validate([
            'notification_filters' => 'nullable|array',
            'blocked_keywords' => 'nullable|array',
            'location_update_interval' => 'nullable|integer|min:30|max:3600',
            'screen_mirroring_enabled' => 'nullable|boolean',
            'geofence_settings' => 'nullable|array',
        ]);

        if (!$this->verifyParentChildRelationship(auth()->user()->id, $childId)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        $familyMember = FamilyMember::where('user_id', auth()->user()->id)->first();

        $settings = AppSettings::where('family_id', $familyMember->family_id)
            ->where('child_user_id', $childId)
            ->firstOrFail();

        $updateData = array_filter([
            'notification_filters' => $request->notification_filters,
            'blocked_keywords' => $request->blocked_keywords,
            'location_update_interval' => $request->location_update_interval,
            'screen_mirroring_enabled' => $request->screen_mirroring_enabled,
            'geofence_settings' => $request->geofence_settings,
        ], function ($value) {
            return $value !== null;
        });

        $settings->update($updateData);

        return response()->json([
            'success' => true,
            'settings' => $settings,
            'message' => 'Settings updated successfully'
        ]);
    }

    private function verifyParentChildRelationship($parentId, $childId): bool
    {
        $parentMember = FamilyMember::where('user_id', $parentId)
            ->where('role', 'parent')
            ->first();

        if (!$parentMember) return false;

        $childMember = FamilyMember::where('user_id', $childId)
            ->where('family_id', $parentMember->family_id)
            ->where('role', 'child')
            ->first();

        return $childMember !== null;
    }
}

// DashboardController.php