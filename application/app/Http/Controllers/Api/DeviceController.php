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

    /**
     * BARU: Verify apakah device sudah paired
     */
    public function verify(Request $request)
    {
        $request->validate([
            'device_id' => 'required|string',
        ]);

        $device = Device::where('device_id', $request->device_id)->first();

        if (!$device) {
            return response()->json([
                'success' => false,
                'is_paired' => false,
                'message' => 'Device not paired yet',
            ], 200); // 200 bukan 404, karena ini bukan error
        }

        // Load parent info
        $device->load('parent:id,email,family_code');

        return response()->json([
            'success' => true,
            'is_paired' => true,
            'data' => [
                'device_id' => $device->device_id,
                'device_name' => $device->device_name,
                'parent_id' => $device->parent_id,
                'family_code' => $device->parent->family_code,
                'is_online' => $device->is_online,
                'last_seen' => $device->last_seen,
                'created_at' => $device->created_at,
            ],
            'message' => 'Device is paired',
        ]);
    }

    /**
     * BARU: Unpair device
     */
    public function unpair(Request $request)
    {
        $request->validate([
            'device_id' => 'required|string|exists:devices,device_id',
        ]);

        $device = Device::where('device_id', $request->device_id)->first();

        if (!$device) {
            return response()->json([
                'success' => false,
                'message' => 'Device not found',
            ], 404);
        }

        // Hapus device (cascade akan hapus locations, notifications, dll)
        $device->delete();

        return response()->json([
            'success' => true,
            'message' => 'Device unpaired successfully',
        ]);
    }

    public function pair(Request $request)
    {
        $request->validate([
            'family_code' => 'required|exists:parents,family_code',
            'device_id' => 'required|string',
            'device_name' => 'required',
            'device_type' => 'required|in:android,ios',
        ]);

        // Cek apakah device sudah paired
        $existingDevice = Device::where('device_id', $request->device_id)->first();

        if ($existingDevice) {
            // Device sudah paired, return info device yang ada
            $existingDevice->load('parent:id,email,family_code');

            return response()->json([
                'success' => true,
                'data' => [
                    'device_id' => $existingDevice->device_id,
                    'device_name' => $existingDevice->device_name,
                    'parent_id' => $existingDevice->parent_id,
                    'family_code' => $existingDevice->parent->family_code,
                ],
                'message' => 'Device already paired',
            ], 200);
        }

        // Device belum paired, create baru
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
            'data' => [
                'device_id' => $device->device_id,
                'device_name' => $device->device_name,
                'parent_id' => $device->parent_id,
                'family_code' => $parent->family_code,
            ],
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
