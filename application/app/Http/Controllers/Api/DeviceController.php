<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\ParentModel;
use App\Services\FCMService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Events\DevicePaired;

class DeviceController extends Controller
{
    protected $fcmService;

    public function __construct(FCMService $fcmService)
    {
        $this->fcmService = $fcmService;
    }

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

    public function getDevicesByParent($parentId)
    {
        $parentExists = ParentModel::where('id', $parentId)->exists();

        if (!$parentExists) {
            return response()->json([
                'success' => false,
                'message' => 'Parent not found',
            ], 404);
        }

        $devices = Device::where('parent_id', $parentId)
            ->select('id', 'device_id', 'device_name', 'device_type', 'fcm_token', 'is_online', 'last_seen')
            ->get();

        return response()->json([
            'success' => true,
            'parent_id' => $parentId,
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
     * Verify device pairing status
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
            ], 200);
        }

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
     * Unpair device
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

        $device->delete();

        return response()->json([
            'success' => true,
            'message' => 'Device unpaired successfully',
        ]);
    }

    /**
     * Pair device
     */
    public function pair(Request $request)
    {
        $request->validate([
            'family_code' => 'required|exists:parents,family_code',
            'device_id' => 'required|string',
            'device_name' => 'required',
            'device_type' => 'required|in:android,ios',
        ]);

        Log::info('Pair request received', $request->all());

        $existingDevice = Device::where('device_id', $request->device_id)->first();

        if ($existingDevice) {
            $existingDevice->load('parent:id,email,family_code');

            Log::info('Device already paired', [
                'device_id' => $existingDevice->device_id,
                'family_code' => $existingDevice->parent->family_code
            ]);

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

        $parent = ParentModel::where('family_code', $request->family_code)->first();

        if (!$parent) {
            Log::error('Parent not found for family code: ' . $request->family_code);
            return response()->json([
                'success' => false,
                'message' => 'Family code not found',
            ], 404);
        }

        $device = Device::create([
            'parent_id' => $parent->id,
            'device_id' => $request->device_id,
            'device_name' => $request->device_name,
            'device_type' => $request->device_type,
            'is_online' => true,
            'last_seen' => now(),
            'created_at' => now(),
        ]);

        Log::info('New device paired', [
            'device_id' => $device->device_id,
            'device_name' => $device->device_name,
            'family_code' => $parent->family_code
        ]);

        try {
            broadcast(new DevicePaired($parent->family_code, [
                'device_id' => $device->device_id,
                'device_name' => $device->device_name,
                'device_type' => $device->device_type,
                'parent_id' => $parent->id,
                'family_code' => $parent->family_code,
                'paired_at' => now()->toISOString(),
                'message' => 'Device berhasil dipasangkan'
            ]));

            Log::info('Broadcast event sent for family: ' . $parent->family_code);
        } catch (\Exception $e) {
            Log::error('Failed to broadcast event: ' . $e->getMessage());
        }

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

    /**
     * Update FCM token
     */
    public function updateFcmToken(Request $request)
    {
        $request->validate([
            'device_id' => 'required|string|exists:devices,device_id',
            'fcm_token' => 'required|string|min:50',
        ]);

        $device = Device::where('device_id', $request->device_id)->first();

        if (!$device) {
            return response()->json([
                'success' => false,
                'message' => 'Device not found',
            ], 404);
        }

        $device->updateFcmToken($request->fcm_token);

        Log::info('FCM token updated', [
            'device_id' => $device->device_id,
            'token_preview' => substr($request->fcm_token, 0, 20) . '...'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'FCM token updated successfully',
        ]);
    }

    /**
     * Update device status
     */
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

    /**
     * Delete device
     */
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
