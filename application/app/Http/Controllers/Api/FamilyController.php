<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Family;
use App\Models\FamilyMember;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class FamilyController extends Controller
{
    public function create(Request $request): JsonResponse
    {
        $request->validate([
            'family_name' => 'required|string|max:255',
        ]);

        $family = Family::create([
            'name' => $request->family_name,
            'family_code' => Str::upper(Str::random(8)),
            'created_by' => $request->user()->id,
        ]);

        // Add creator as parent member
        FamilyMember::create([
            'family_id' => $family->id,
            'user_id' => $request->user()->id,
            'role' => 'parent',
            'is_primary' => true,
        ]);

        return response()->json([
            'success' => true,
            'family' => $family,
            'message' => 'Family created successfully'
        ], 201);
    }

    public function join(Request $request): JsonResponse
    {
        $request->validate([
            'family_code' => 'required|string|size:8',
        ]);

        $family = Family::where('family_code', $request->family_code)->first();

        if (!$family) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid family code'
            ], 404);
        }

        $existingMember = FamilyMember::where('family_id', $family->id)
            ->where('user_id', $request->user()->id)
            ->first();

        if ($existingMember) {
            return response()->json([
                'success' => false,
                'message' => 'Already member of this family'
            ], 400);
        }

        FamilyMember::create([
            'family_id' => $family->id,
            'user_id' => $request->user()->id,
            'role' => $request->user()->role,
            'is_primary' => false,
        ]);

        return response()->json([
            'success' => true,
            'family' => $family,
            'message' => 'Successfully joined family'
        ]);
    }

    public function members(Request $request): JsonResponse
    {
        $user = $request->user();

        // Ambil semua family yang dibuat oleh user login
        $families = Family::with(['creator', 'members.user'])
            ->where('created_by', $user->id)
            ->orderBy('created_at', 'desc') // 🔹 terbaru di atas
            ->get();

        return response()->json([
            'success' => true,
            'families' => $families
        ]);
    }

    public function membersjoin(Request $request): JsonResponse
    {
        $user = $request->user();

        // 🔹 Ambil semua family_id di mana user login sebagai parent
        $familyIds = FamilyMember::where('user_id', $user->id)
            ->where('role', 'parent')
            ->pluck('family_id');

        if ($familyIds->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'User is not a parent in any family'
            ], 404);
        }

        // 🔹 Ambil hanya families yang punya child
        $families = Family::with([
            'creator',
            'members' => function ($q) {
                $q->where('role', 'child')->with('user'); // hanya child
            }
        ])
            ->whereIn('id', $familyIds)
            ->whereHas('members', function ($q) {
                $q->where('role', 'child'); // pastikan ada child
            })
            ->orderBy('created_at', 'desc')
            ->get();

        if ($families->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No families with children found for this parent'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'families' => $families
        ]);
    }
}
