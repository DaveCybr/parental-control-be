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

            // ✅ Ensure directory exists with multiple fallback methods
            $directory = 'captured_photos';
            $fullPath = storage_path('app/public/' . $directory);
            
            Log::info("Checking directory: {$fullPath}");
            
            if (!file_exists($fullPath)) {
                Log::warning("Directory does not exist, attempting to create: {$fullPath}");
                
                // Method 1: Try Storage facade first (safer)
                try {
                    Storage::disk('public')->makeDirectory($directory);
                    Log::info("Directory created via Storage facade");
                } catch (\Exception $e) {
                    Log::error("Storage facade failed: " . $e->getMessage());
                    
                    // Method 2: Try direct mkdir
                    if (@mkdir($fullPath, 0775, true)) {
                        Log::info("Directory created via mkdir");
                    } else {
                        // Method 3: Try without recursive flag
                        $parentPath = dirname($fullPath);
                        if (file_exists($parentPath) && is_writable($parentPath)) {
                            @mkdir($fullPath, 0775, false);
                            Log::info("Directory created non-recursively");
                        } else {
                            throw new \Exception(
                                "Cannot create directory. Parent path: {$parentPath} " .
                                (file_exists($parentPath) ? "exists but not writable" : "does not exist")
                            );
                        }
                    }
                }
            }
            
            // Verify directory is writable
            if (!is_writable($fullPath)) {
                Log::error("Directory exists but is not writable: {$fullPath}");
                
                // Try to fix permissions
                @chmod($fullPath, 0775);
                
                if (!is_writable($fullPath)) {
                    throw new \Exception(
                        "Directory is not writable: {$fullPath}. " .
                        "Current permissions: " . substr(sprintf('%o', fileperms($fullPath)), -4) . ". " .
                        "Owner: " . posix_getpwuid(fileowner($fullPath))['name'] . ". " .
                        "Group: " . posix_getgrgid(filegroup($fullPath))['name']
                    );
                }
            }
            
            Log::info("Directory is ready and writable: {$fullPath}");

            // Generate unique filename
            $timestamp = now()->format('YmdHis');
            $filename = "{$request->device_id}_{$request->camera_type}_{$timestamp}.jpg";

            // Upload file to storage/app/public/captured_photos
            $path = $request->file('photo')->storeAs($directory, $filename, 'public');
            
            if (!$path) {
                throw new \Exception('Failed to store file');
            }
            
            $url = Storage::url($path);

            // Save to database
            $photo = CapturedPhoto::create([
                'device_id' => $device->device_id,
                'camera_type' => $request->camera_type,
                'file_url' => $url,
                'captured_at' => now(),
            ]);

            Log::info("Photo captured successfully", [
                'device_id' => $device->device_id,
                'camera_type' => $request->camera_type,
                'path' => $path,
                'url' => $url
            ]);

            return response()->json([
                'success' => true,
                'data' => $photo,
                'message' => 'Photo captured successfully',
            ], 201);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation error uploading photo', [
                'errors' => $e->errors()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
            
        } catch (\Exception $e) {
            Log::error('Failed to capture photo', [
                'device_id' => $request->device_id ?? 'unknown',
                'camera_type' => $request->camera_type ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

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