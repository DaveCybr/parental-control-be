<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ScreenSession;
use App\Models\FamilyMember;
use App\Services\ScreenMirrorService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ScreenController extends Controller
{
    protected $screenMirrorService;

    public function __construct(ScreenMirrorService $screenMirrorService)
    {
        $this->screenMirrorService = $screenMirrorService;
    }

    public function startSession(Request $request): JsonResponse
    {
        $request->validate([
            'child_id' => 'required|integer|exists:users,id',
        ]);

        $user = auth()->user();

        if ($user->role !== 'parent') {
            return response()->json([
                'success' => false,
                'message' => 'Only parents can start screen mirroring sessions'
            ], 403);
        }

        try {
            $session = $this->screenMirrorService->startSession($user->id, $request->child_id);

            return response()->json([
                'success' => true,
                'session' => $session,
                'message' => 'Screen mirroring session started'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function endSession(Request $request): JsonResponse
    {
        $request->validate([
            'session_token' => 'required|string|size:64',
        ]);

        $user = auth()->user();

        if ($user->role !== 'parent') {
            return response()->json([
                'success' => false,
                'message' => 'Only parents can end screen mirroring sessions'
            ], 403);
        }

        try {
            $session = $this->screenMirrorService->endSession(
                $request->session_token,
                $user->id
            );

            return response()->json([
                'success' => true,
                'session' => $session,
                'message' => 'Screen mirroring session ended'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function activeSessions(): JsonResponse
    {
        $user = auth()->user();

        if ($user->role !== 'parent') {
            return response()->json([
                'success' => false,
                'message' => 'Only parents can view active sessions'
            ], 403);
        }

        $familyMember = FamilyMember::where('user_id', $user->id)->first();

        if (!$familyMember) {
            return response()->json([
                'success' => false,
                'message' => 'Not part of any family'
            ], 400);
        }

        // Get children in family
        $childrenIds = FamilyMember::where('family_id', $familyMember->family_id)
            ->where('role', 'child')
            ->pluck('user_id');

        $activeSessions = ScreenSession::with('child:id,name,email')
            ->where('parent_user_id', $user->id)
            ->whereIn('child_user_id', $childrenIds)
            ->where('is_active', true)
            ->get();

        return response()->json([
            'success' => true,
            'active_sessions' => $activeSessions
        ]);
    }

    public function getActiveSession($childId): JsonResponse
    {
        $user = auth()->user();

        if ($user->role !== 'child' || $user->id != $childId) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        $activeSession = ScreenSession::where('child_user_id', $childId)
            ->where('is_active', true)
            ->with('parent:id,name')
            ->first();

        return response()->json([
            'success' => true,
            'active_session' => $activeSession,
            'is_being_monitored' => $activeSession !== null
        ]);
    }

    public function sendScreenshot(Request $request): JsonResponse
    {
        $request->validate([
            'session_token' => 'required|string|size:64',
            'screenshot' => 'required|string', // base64 encoded image
            'timestamp' => 'required|date',
        ]);

        $user = auth()->user();

        if ($user->role !== 'child') {
            return response()->json([
                'success' => false,
                'message' => 'Only child devices can send screenshots'
            ], 403);
        }

        // Verify session exists and is active
        $session = ScreenSession::where('session_token', $request->session_token)
            ->where('child_user_id', $user->id)
            ->where('is_active', true)
            ->first();

        if (!$session) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or inactive session'
            ], 400);
        }

        try {
            // Decode base64 image
            $imageData = base64_decode($request->screenshot);

            if ($imageData === false) {
                throw new \Exception('Invalid image data');
            }

            // Generate unique filename
            $filename = 'screenshots/' . $user->id . '/' . now()->format('Y/m/d') . '/' . Str::uuid() . '.jpg';

            // Store the screenshot (you might want to use cloud storage in production)
            Storage::disk('public')->put($filename, $imageData);

            // Broadcast screenshot to parent (via WebSocket)
            broadcast(new \App\Events\ScreenshotReceived([
                'session_token' => $request->session_token,
                'child_id' => $user->id,
                'screenshot_url' => Storage::url($filename),
                'timestamp' => $request->timestamp,
            ]));

            return response()->json([
                'success' => true,
                'message' => 'Screenshot sent successfully',
                'filename' => $filename
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process screenshot: ' . $e->getMessage()
            ], 500);
        }
    }

    public function sendStreamFrame(Request $request): JsonResponse
    {
        $request->validate([
            'session_token' => 'required|string|size:64',
            'frame_data' => 'required|string', // base64 encoded image frame
            'frame_number' => 'required|integer|min:0',
            'timestamp' => 'required|date',
        ]);

        $user = auth()->user();

        if ($user->role !== 'child') {
            return response()->json([
                'success' => false,
                'message' => 'Only child devices can send stream frames'
            ], 403);
        }

        // Verify session
        $session = ScreenSession::where('session_token', $request->session_token)
            ->where('child_user_id', $user->id)
            ->where('is_active', true)
            ->first();

        if (!$session) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or inactive session'
            ], 400);
        }

        try {
            // For live streaming, we don't store frames but broadcast directly
            broadcast(new \App\Events\ScreenFrameReceived([
                'session_token' => $request->session_token,
                'child_id' => $user->id,
                'frame_data' => $request->frame_data,
                'frame_number' => $request->frame_number,
                'timestamp' => $request->timestamp,
            ]));

            return response()->json([
                'success' => true,
                'message' => 'Frame broadcasted successfully',
                'frame_number' => $request->frame_number
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to broadcast frame: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get screen session history for analytics
     */
    public function sessionHistory(Request $request): JsonResponse
    {
        $request->validate([
            'child_id' => 'nullable|integer',
            'days' => 'nullable|integer|min:1|max:30',
        ]);

        $user = auth()->user();

        if ($user->role !== 'parent') {
            return response()->json([
                'success' => false,
                'message' => 'Only parents can view session history'
            ], 403);
        }

        $familyMember = FamilyMember::where('user_id', $user->id)->first();
        if (!$familyMember) {
            return response()->json([
                'success' => false,
                'message' => 'Not part of any family'
            ], 400);
        }

        $query = ScreenSession::with('child:id,name')
            ->where('parent_user_id', $user->id);

        if ($request->child_id) {
            // Verify child is in family
            $childInFamily = FamilyMember::where('family_id', $familyMember->family_id)
                ->where('user_id', $request->child_id)
                ->where('role', 'child')
                ->exists();

            if (!$childInFamily) {
                return response()->json([
                    'success' => false,
                    'message' => 'Child not found in your family'
                ], 404);
            }

            $query->where('child_user_id', $request->child_id);
        }

        $days = $request->days ?? 7;
        $sessions = $query->where('started_at', '>=', now()->subDays($days))
            ->orderBy('started_at', 'desc')
            ->get();

        // Calculate session statistics
        $totalSessions = $sessions->count();
        $totalDuration = $sessions->sum(function ($session) {
            if ($session->ended_at) {
                return $session->started_at->diffInMinutes($session->ended_at);
            }
            return 0;
        });

        $avgDuration = $totalSessions > 0 ? round($totalDuration / $totalSessions, 2) : 0;

        return response()->json([
            'success' => true,
            'sessions' => $sessions,
            'statistics' => [
                'total_sessions' => $totalSessions,
                'total_duration_minutes' => $totalDuration,
                'average_duration_minutes' => $avgDuration,
                'period_days' => $days
            ]
        ]);
    }
}
