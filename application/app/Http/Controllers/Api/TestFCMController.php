<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FCMService;
use App\Models\ParentModel;
use Illuminate\Http\Request;

class TestFCMController extends Controller
{
    protected $fcmService;

    public function __construct(FCMService $fcmService)
    {
        $this->fcmService = $fcmService;
    }

    /**
     * Test send notification to parent
     * POST /api/test/fcm/notification
     */
    public function testNotificationToParent(Request $request)
    {
        $request->validate([
            'parent_id' => 'required|exists:parents,id',
            'title' => 'string|max:255',
            'body' => 'string|max:1000',
        ]);

        try {
            // Get parent
            $parent = ParentModel::find($request->parent_id);

            if (!$parent) {
                return response()->json([
                    'success' => false,
                    'message' => 'Parent not found'
                ], 404);
            }

            if (!$parent->hasValidFcmToken()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Parent does not have valid FCM token'
                ], 400);
            }

            // Prepare test notification
            $notification = [
                'title' => $request->input('title', '🔔 Test Notification'),
                'body' => $request->input('body', 'This is a test notification from SafeKids API'),
            ];

            $data = [
                'type' => 'TEST_NOTIFICATION',
                'test_id' => uniqid('test_'),
                'timestamp' => now()->toISOString(),
                'message' => 'If you receive this, FCM is working correctly!',
            ];

            // Send notification
            $result = $this->fcmService->sendNotificationToParent(
                $parent->fcm_token,
                $notification,
                $data
            );

            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'],
                'parent_info' => [
                    'id' => $parent->id,
                    'name' => $parent->name,
                    'email' => $parent->email,
                ],
                'notification_sent' => $notification,
                'fcm_response' => $result['fcm_response'] ?? null,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test geofence alert simulation
     * POST /api/test/fcm/geofence-alert
     */
    public function testGeofenceAlert(Request $request)
    {
        $request->validate([
            'parent_id' => 'required|exists:parents,id',
            'device_name' => 'string|max:255',
            'geofence_name' => 'string|max:255',
        ]);

        try {
            $parent = ParentModel::find($request->parent_id);

            if (!$parent || !$parent->hasValidFcmToken()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Parent not found or no valid FCM token'
                ], 400);
            }

            // Simulate geofence alert
            $notification = [
                'title' => '⚠️ Geofence Alert',
                'body' => ($request->input('device_name', 'Child Device') .
                    ' has left ' .
                    $request->input('geofence_name', 'Home Zone')),
            ];

            $data = [
                'type' => 'GEOFENCE_VIOLATION',
                'device_id' => 'test_device_' . uniqid(),
                'device_name' => $request->input('device_name', 'Child Device'),
                'geofence_id' => '999',
                'geofence_name' => $request->input('geofence_name', 'Home Zone'),
                'latitude' => '-6.2088',
                'longitude' => '106.8456',
                'timestamp' => now()->toISOString(),
            ];

            $result = $this->fcmService->sendNotificationToParent(
                $parent->fcm_token,
                $notification,
                $data
            );

            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'],
                'simulation' => 'Geofence alert sent',
                'fcm_response' => $result['fcm_response'] ?? null,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send notification using FCM token directly
     * POST /api/test/fcm/direct
     */
    public function testDirectNotification(Request $request)
    {
        $request->validate([
            'fcm_token' => 'required|string',
            'title' => 'string|max:255',
            'body' => 'string|max:1000',
        ]);

        try {
            $notification = [
                'title' => $request->input('title', '🔔 Direct Test'),
                'body' => $request->input('body', 'Direct FCM test notification'),
            ];

            $data = [
                'type' => 'DIRECT_TEST',
                'timestamp' => now()->toISOString(),
            ];

            $result = $this->fcmService->sendNotificationToParent(
                $request->fcm_token,
                $notification,
                $data
            );

            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'],
                'fcm_response' => $result['fcm_response'] ?? null,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }
}
