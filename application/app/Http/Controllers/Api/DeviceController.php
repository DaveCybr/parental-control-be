<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\ParentModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DeviceController extends Controller
{
    public function index(Request $request)
    {
        $devices = $request->user()->devices()
            ->with('latestLocation')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $devices,
        ]);
    }

    public function show($id)
    {
        $device = Device::with(['latestLocation', 'parent'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $device,
        ]);
    }

    public function pair(Request $request)
    {
        $request->validate([
            'family_code' => 'required|exists:parents,family_code',
            'device_id' => 'required|unique:devices,device_id',
            'device_name' => 'required',
            'device_type' => 'required|in:android,ios',
        ]);

        $parent = ParentModel::where('family_code', $request->family_code)->first();

        $device = Device::create([
            'parent_id' => $parent->id,
            'device_id' => $request->device_id,
            'device_name' => $request->device_name,
            'device_type' => $request->device_type,
            'is_online' => true,
            'last_seen' => now(),
        ]);

        return response()->json([
            'success' => true,
            'data' => $device,
            'message' => 'Device paired successfully',
        ], 201);
    }

    public function updateStatus(Request $request, $deviceId)
    {
        $request->validate([
            'is_online' => 'required|boolean',
        ]);

        $device = Device::where('device_id', $deviceId)->firstOrFail();
        
        $device->update([
            'is_online' => $request->is_online,
            'last_seen' => now(),
        ]);

        return response()->json([
            'success' => true,
            'data' => $device,
            'message' => 'Device status updated',
        ]);
    }

    public function destroy($id)
    {
        $device = Device::findOrFail($id);
        $device->delete();

        return response()->json([
            'success' => true,
            'message' => 'Device removed successfully',
        ]);
    }
}