<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Geofence;
use Illuminate\Http\Request;

class GeofenceController extends Controller
{
    public function index(Request $request)
    {
        $geofences = $request->user()->geofences()->get();

        return response()->json([
            'success' => true,
            'data' => $geofences,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'radius' => 'required|integer|min:10',
        ]);

        $geofence = Geofence::create([
            'parent_id' => $request->user()->id,
            'name' => $request->name,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'radius' => $request->radius,
            'is_active' => true,
        ]);

        return response()->json([
            'success' => true,
            'data' => $geofence,
            'message' => 'Geofence created successfully',
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'string',
            'latitude' => 'numeric|between:-90,90',
            'longitude' => 'numeric|between:-180,180',
            'radius' => 'integer|min:10',
            'is_active' => 'boolean',
        ]);

        $geofence = Geofence::where('parent_id', $request->user()->id)
            ->findOrFail($id);

        $geofence->update($request->only([
            'name', 'latitude', 'longitude', 'radius', 'is_active'
        ]));

        return response()->json([
            'success' => true,
            'data' => $geofence,
            'message' => 'Geofence updated successfully',
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $geofence = Geofence::where('parent_id', $request->user()->id)
            ->findOrFail($id);

        $geofence->delete();

        return response()->json([
            'success' => true,
            'message' => 'Geofence deleted successfully',
        ]);
    }
}
