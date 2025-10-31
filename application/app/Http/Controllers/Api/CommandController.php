<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\ParentModel;
use App\Services\FCMService;
use Illuminate\Http\Request;

class CommandController extends Controller
{
    protected $fcmService;

    public function __construct(FCMService $fcmService)
    {
        $this->fcmService = $fcmService;
    }

    /**
     * Send capture photo command
     */
    public function capturePhoto(Request $request)
    {
        $request->validate([
            'device_id' => 'required|string|exists:devices,device_id',
            'front_camera' => 'boolean',
        ]);

        // Verify parent owns this device
        $device = Device::where('device_id', $request->device_id)
            ->where('parent_id', $request->user()->id)
            ->first();

        if (!$device) {
            return response()->json([
                'success' => false,
                'message' => 'Device not found or unauthorized',
            ], 404);
        }

        $useFrontCamera = $request->input('front_camera', true);

        $result = $this->fcmService->sendCapturePhotoCommand(
            $device->device_id,
            $useFrontCamera
        );

        return response()->json($result);
    }

    /**
     * Send request location command
     */
    public function requestLocation(Request $request)
    {
        $request->validate([
            'device_id' => 'required|string|exists:devices,device_id',
        ]);

        $device = Device::where('device_id', $request->device_id)
            ->where('parent_id', $request->user()->id)
            ->first();

        if (!$device) {
            return response()->json([
                'success' => false,
                'message' => 'Device not found or unauthorized',
            ], 404);
        }

        $result = $this->fcmService->sendRequestLocationCommand($device->device_id);

        return response()->json($result);
    }

    /**
     * Send start monitoring command
     */
    public function startMonitoring(Request $request)
    {
        $request->validate([
            'device_id' => 'required|string|exists:devices,device_id',
        ]);

        $device = Device::where('device_id', $request->device_id)
            ->where('parent_id', $request->user()->id)
            ->first();

        if (!$device) {
            return response()->json([
                'success' => false,
                'message' => 'Device not found or unauthorized',
            ], 404);
        }

        $result = $this->fcmService->sendStartMonitoringCommand($device->device_id);

        return response()->json($result);
    }

    /**
     * Send stop monitoring command
     */
    public function stopMonitoring(Request $request)
    {
        $request->validate([
            'device_id' => 'required|string|exists:devices,device_id',
        ]);

        $device = Device::where('device_id', $request->device_id)
            ->where('parent_id', $request->user()->id)
            ->first();

        if (!$device) {
            return response()->json([
                'success' => false,
                'message' => 'Device not found or unauthorized',
            ], 404);
        }

        $result = $this->fcmService->sendStopMonitoringCommand($device->device_id);

        return response()->json($result);
    }

    /**
     * Send start screen monitor command
     */
    public function startScreenMonitor(Request $request)
    {
        $request->validate([
            'device_id' => 'required|string|exists:devices,device_id',
        ]);

        $device = Device::where('device_id', $request->device_id)
            ->where('parent_id', $request->user()->id)
            ->first();

        if (!$device) {
            return response()->json([
                'success' => false,
                'message' => 'Device not found or unauthorized',
            ], 404);
        }

        $result = $this->fcmService->sendStartScreenMonitorCommand($device->device_id);

        return response()->json($result);
    }

    /**
     * Send stop screen monitor command
     */
    public function stopScreenMonitor(Request $request)
    {
        $request->validate([
            'device_id' => 'required|string|exists:devices,device_id',
        ]);

        $device = Device::where('device_id', $request->device_id)
            ->where('parent_id', $request->user()->id)
            ->first();

        if (!$device) {
            return response()->json([
                'success' => false,
                'message' => 'Device not found or unauthorized',
            ], 404);
        }

        $result = $this->fcmService->sendStopScreenMonitorCommand($device->device_id);

        return response()->json($result);
    }

    /**
     * Send custom command
     */
    public function sendCustomCommand(Request $request)
    {
        $request->validate([
            'device_id' => 'required|string|exists:devices,device_id',
            'command' => 'required|array',
            'command.type' => 'required|string',
        ]);

        $device = Device::where('device_id', $request->device_id)
            ->where('parent_id', $request->user()->id)
            ->first();

        if (!$device) {
            return response()->json([
                'success' => false,
                'message' => 'Device not found or unauthorized',
            ], 404);
        }

        $result = $this->fcmService->sendCommand(
            $device->device_id,
            $request->command
        );

        return response()->json($result);
    }

    /**
     * Broadcast command to all parent's devices
     */
    public function broadcastCommand(Request $request)
    {
        $request->validate([
            'command' => 'required|array',
            'command.type' => 'required|string',
        ]);

        $parent = $request->user();

        $devices = Device::where('parent_id', $parent->id)
            ->pluck('device_id')
            ->toArray();

        if (empty($devices)) {
            return response()->json([
                'success' => false,
                'message' => 'No devices found',
            ], 404);
        }

        $results = $this->fcmService->broadcastCommand($devices, $request->command);

        $successCount = collect($results)->where('success', true)->count();
        $totalCount = count($results);

        return response()->json([
            'success' => $successCount > 0,
            'message' => "Command sent to {$successCount}/{$totalCount} devices",
            'results' => $results,
        ]);
    }

    /**
     * Test FCM token
     */
    public function testFcmToken(Request $request)
    {
        $request->validate([
            'device_id' => 'required|string|exists:devices,device_id',
        ]);

        $device = Device::where('device_id', $request->device_id)
            ->where('parent_id', $request->user()->id)
            ->first();

        if (!$device) {
            return response()->json([
                'success' => false,
                'message' => 'Device not found or unauthorized',
            ], 404);
        }

        if (!$device->hasValidFcmToken()) {
            return response()->json([
                'success' => false,
                'message' => 'Device does not have FCM token',
            ], 400);
        }

        $isValid = $this->fcmService->testToken($device->fcm_token);

        return response()->json([
            'success' => $isValid,
            'message' => $isValid ? 'FCM token is valid' : 'FCM token is invalid',
            'device_id' => $device->device_id,
            'token_preview' => substr($device->fcm_token, 0, 20) . '...',
        ]);
    }
}
