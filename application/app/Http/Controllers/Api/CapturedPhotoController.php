<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\CapturedPhoto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class CapturedPhotoController extends Controller
{
    /**
     * Store captured photo from child device
     */
    public function store(Request $request)
    {
        $request->validate([
            'device_id' => 'required|exists:devices,device_id',
            'camera_type' => 'required|in:front,back',
            'photo' => 'required|image|max:10240', // max 10MB
        ]);

        try {
            $device = Device::where('device_id', $request->device_id)->firstOrFail();

            // Generate unique filename
            $timestamp = now()->format('YmdHis');
            $filename = "{$request->device_id}_{$request->camera_type}_{$timestamp}.jpg";

            // Upload file to storage/app/public/captured_photos
            $path = $request->file('photo')->storeAs('captured_photos', $filename, 'public');
            $url = Storage::url($path);

            // Save to database
            $photo = CapturedPhoto::create([
                'device_id' => $device->device_id,
                'camera_type' => $request->camera_type,
                'file_url' => $url,
                'captured_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'data' => $photo,
                'message' => 'Photo captured successfully',
            ], 201);
        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'message' => 'Failed to capture photo: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all captured photos for a device
     */
    public function index(Request $request, $deviceId)
    {
        $request->validate([
            'camera_type' => 'in:front,back',
            'start_date' => 'date',
            'end_date' => 'date|after_or_equal:start_date',
            'limit' => 'integer|min:1|max:100',
        ]);

        $query = CapturedPhoto::where('device_id', $deviceId)
            ->orderBy('captured_at', 'desc');

        // Filter by camera type
        if ($request->has('camera_type')) {
            $query->where('camera_type', $request->camera_type);
        }

        // Filter by date range
        if ($request->has('start_date')) {
            $query->where('captured_at', '>=', $request->start_date);
        }

        if ($request->has('end_date')) {
            $query->where('captured_at', '<=', $request->end_date);
        }

        $photos = $query->limit($request->input('limit', 50))->get();

        return response()->json([
            'success' => true,
            'data' => $photos,
            'count' => $photos->count(),
        ]);
    }

    /**
     * Get latest captured photo
     */
    public function latest($deviceId)
    {
        $photo = CapturedPhoto::where('device_id', $deviceId)
            ->orderBy('captured_at', 'desc')
            ->first();

        if (!$photo) {
            return response()->json([
                'success' => false,
                'message' => 'No photos found for this device',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $photo,
        ]);
    }

    /**
     * Delete captured photo
     */
    public function destroy($id)
    {
        try {
            $photo = CapturedPhoto::findOrFail($id);

            // Delete file from storage
            $path = str_replace('/storage/', '', $photo->file_url);

            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }

            // Delete from database
            $photo->delete();

            return response()->json([
                'success' => true,
                'message' => 'Photo deleted successfully',
            ]);
        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete photo: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete multiple photos
     */
    public function bulkDestroy(Request $request)
    {
        $request->validate([
            'photo_ids' => 'required|array',
            'photo_ids.*' => 'integer|exists:captured_photos,id',
        ]);

        try {
            $photos = CapturedPhoto::whereIn('id', $request->photo_ids)->get();

            $deletedCount = 0;
            foreach ($photos as $photo) {
                // Delete file from storage
                $path = str_replace('/storage/', '', $photo->file_url);

                if (Storage::disk('public')->exists($path)) {
                    Storage::disk('public')->delete($path);
                }

                $photo->delete();
                $deletedCount++;
            }

            return response()->json([
                'success' => true,
                'message' => "{$deletedCount} photos deleted successfully",
                'deleted_count' => $deletedCount,
            ]);
        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete photos: ' . $e->getMessage(),
            ], 500);
        }
    }
}
