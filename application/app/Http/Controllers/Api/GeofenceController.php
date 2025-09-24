<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Geofence;
use App\Models\FamilyMember;
use App\Models\Alert;
use App\Services\GeofenceService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Events\AlertTriggered;

class GeofenceController extends Controller
{
    protected $geofenceService;

    public function __construct(GeofenceService $geofenceService)
    {
        $this->geofenceService = $geofenceService;
    }

    public function create(Request $request): JsonResponse
    {
        $request->validate([
            'family_id' => 'required|integer|exists:families,id',
            'name' => 'required|string|max:255',
            'center_latitude' => 'required|numeric|between:-90,90',
            'center_longitude' => 'required|numeric|between:-180,180',
            'radius' => 'required|integer|min:10|max:50000',
            'type' => 'required|in:safe,danger',
        ]);

        $user = auth()->user();

        if ($user->role !== 'parent') {
            return response()->json([
                'success' => false,
                'message' => 'Only parents can create geofences'
            ], 403);
        }

        // Verifikasi user adalah parent di family yang diminta
        $familyMember = FamilyMember::where('user_id', $user->id)
            ->where('family_id', $request->family_id)
            ->where('role', 'parent')
            ->first();

        if (!$familyMember) {
            return response()->json([
                'success' => false,
                'message' => 'You are not a parent in the specified family'
            ], 400);
        }

        $geofence = Geofence::create([
            'family_id' => $request->family_id,
            'name' => $request->name,
            'center_latitude' => $request->center_latitude,
            'center_longitude' => $request->center_longitude,
            'radius' => $request->radius,
            'type' => $request->type,
            'is_active' => true,
        ]);

        return response()->json([
            'success' => true,
            'geofence' => $geofence,
            'message' => 'Geofence created successfully'
        ], 201);
    }

    public function list(Request $request): JsonResponse
    {
        $request->validate([
            'family_id' => 'required|integer|exists:families,id',
        ]);

        $user = auth()->user();

        // Verifikasi user adalah bagian dari family yang diminta
        $familyMember = FamilyMember::where('user_id', $user->id)
            ->where('family_id', $request->family_id)
            ->first();

        if (!$familyMember) {
            return response()->json([
                'success' => false,
                'message' => 'You are not part of the specified family'
            ], 400);
        }

        $geofences = Geofence::where('family_id', $request->family_id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'geofences' => $geofences
        ]);
    }

    public function update($id, Request $request): JsonResponse
    {
        $request->validate([
            'family_id' => 'required|integer|exists:families,id',
            'name' => 'sometimes|string|max:255',
            'center_latitude' => 'sometimes|numeric|between:-90,90',
            'center_longitude' => 'sometimes|numeric|between:-180,180',
            'radius' => 'sometimes|integer|min:10|max:50000',
            'type' => 'sometimes|in:safe,danger',
            'is_active' => 'sometimes|boolean',
        ]);

        $user = auth()->user();

        if ($user->role !== 'parent') {
            return response()->json([
                'success' => false,
                'message' => 'Only parents can update geofences'
            ], 403);
        }

        // Verifikasi user adalah parent di family yang diminta
        $familyMember = FamilyMember::where('user_id', $user->id)
            ->where('family_id', $request->family_id)
            ->where('role', 'parent')
            ->first();

        if (!$familyMember) {
            return response()->json([
                'success' => false,
                'message' => 'You are not a parent in the specified family'
            ], 400);
        }

        $geofence = Geofence::where('id', $id)
            ->where('family_id', $request->family_id)
            ->first();

        if (!$geofence) {
            return response()->json([
                'success' => false,
                'message' => 'Geofence not found in the specified family'
            ], 404);
        }

        $updateData = $request->only([
            'name',
            'center_latitude',
            'center_longitude',
            'radius',
            'type',
            'is_active'
        ]);

        $geofence->update(array_filter($updateData, function ($value) {
            return $value !== null;
        }));

        return response()->json([
            'success' => true,
            'geofence' => $geofence->fresh(),
            'message' => 'Geofence updated successfully'
        ]);
    }

    public function delete($id, Request $request): JsonResponse
    {
        $request->validate([
            'family_id' => 'required|integer|exists:families,id',
        ]);

        $user = auth()->user();

        if ($user->role !== 'parent') {
            return response()->json([
                'success' => false,
                'message' => 'Only parents can delete geofences'
            ], 403);
        }

        // Verifikasi user adalah parent di family yang diminta
        $familyMember = FamilyMember::where('user_id', $user->id)
            ->where('family_id', $request->family_id)
            ->where('role', 'parent')
            ->first();

        if (!$familyMember) {
            return response()->json([
                'success' => false,
                'message' => 'You are not a parent in the specified family'
            ], 400);
        }

        $geofence = Geofence::where('id', $id)
            ->where('family_id', $request->family_id)
            ->first();

        if (!$geofence) {
            return response()->json([
                'success' => false,
                'message' => 'Geofence not found in the specified family'
            ], 404);
        }

        $geofence->delete();

        return response()->json([
            'success' => true,
            'message' => 'Geofence deleted successfully'
        ]);
    }

    public function toggle($id, Request $request): JsonResponse
    {
        $request->validate([
            'family_id' => 'required|integer|exists:families,id',
        ]);

        $user = auth()->user();

        if ($user->role !== 'parent') {
            return response()->json([
                'success' => false,
                'message' => 'Only parents can toggle geofences'
            ], 403);
        }

        // Verifikasi user adalah parent di family yang diminta
        $familyMember = FamilyMember::where('user_id', $user->id)
            ->where('family_id', $request->family_id)
            ->where('role', 'parent')
            ->first();

        if (!$familyMember) {
            return response()->json([
                'success' => false,
                'message' => 'You are not a parent in the specified family'
            ], 400);
        }

        $geofence = Geofence::where('id', $id)
            ->where('family_id', $request->family_id)
            ->first();

        if (!$geofence) {
            return response()->json([
                'success' => false,
                'message' => 'Geofence not found in the specified family'
            ], 404);
        }

        $geofence->update(['is_active' => !$geofence->is_active]);

        return response()->json([
            'success' => true,
            'geofence' => $geofence->fresh(),
            'message' => 'Geofence ' . ($geofence->is_active ? 'activated' : 'deactivated')
        ]);
    }

    // Tambahan method untuk mendapatkan families dimana user adalah parent
    public function getParentFamilies(): JsonResponse
    {
        $user = auth()->user();

        if ($user->role !== 'parent') {
            return response()->json([
                'success' => false,
                'message' => 'Only parents can access this endpoint'
            ], 403);
        }

        $families = FamilyMember::where('user_id', $user->id)
            ->where('role', 'parent')
            ->with('family')
            ->get()
            ->map(function ($member) {
                return [
                    'family_id' => $member->family_id,
                    'family_name' => $member->family->name ?? "Family {$member->family_id}",
                    'is_primary' => $member->is_primary
                ];
            });

        return response()->json([
            'success' => true,
            'families' => $families
        ]);
    }

    public function checkViolation($childId, $latitude, $longitude)
    {
        $this->geofenceService->checkViolations($childId, $latitude, $longitude);
    }
}
