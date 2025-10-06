<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\Location;
use App\Services\GeofenceService;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    protected $geofenceService;

    public function __construct(GeofenceService $geofenceService)
    {
        $this->geofenceService = $geofenceService;
    }

    public function store(Request $request)
    {
        $request->validate([
            'device_id' => 'required|exists:devices,device_id',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);

        $device = Device::where('device_id', $request->device_id)->firstOrFail();

        $location = Location::create([
            'device_id' => $device->device_id,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'timestamp' => now(),
        ]);

        // Update device status
        $device->update([
            'is_online' => true,
            'last_seen' => now(),
        ]);

        // Check geofence violations
        $this->geofenceService->checkViolations($device, $location);

        return response()->json([
            'success' => true,
            'data' => $location,
            'message' => 'Location updated',
        ], 201);
    }

    public function index(Request $request, $deviceId)
    {
        $request->validate([
            'start_date' => 'date',
            'end_date' => 'date|after_or_equal:start_date',
            'limit' => 'integer|min:1|max:1000',
        ]);

        $query = Location::where('device_id', $deviceId)
            ->orderBy('timestamp', 'desc');

        if ($request->has('start_date')) {
            $query->where('timestamp', '>=', $request->start_date);
        }

        if ($request->has('end_date')) {
            $query->where('timestamp', '<=', $request->end_date);
        }

        $locations = $query->limit($request->input('limit', 100))->get();

        return response()->json([
            'success' => true,
            'data' => $locations,
        ]);
    }

    public function latest($deviceId)
    {
        $location = Location::where('device_id', $deviceId)
            ->orderBy('timestamp', 'desc')
            ->first();

        return response()->json([
            'success' => true,
            'data' => $location,
        ]);
    }
}
