<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use App\Models\Device;
use App\Models\Screenshot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ScreenshotController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'device_id' => 'required|exists:devices,device_id',
            'screenshot' => 'required|image|max:5120', // max 5MB
        ]);

        try {
            $device = Device::where('device_id', $request->device_id)->firstOrFail();

            // ✅ Pastikan direktori 'screenshots' tersedia
            $directory = 'screenshots';
            $fullPath = storage_path('app/public/' . $directory);

            if (!file_exists($fullPath)) {
                try {
                    // Method 1: pakai Storage facade
                    Storage::disk('public')->makeDirectory($directory);
                } catch (\Exception $e) {
                    // Method 2: mkdir manual
                    if (@mkdir($fullPath, 0775, true)) {
                        // sukses
                    } else {
                        $parentPath = dirname($fullPath);
                        if (file_exists($parentPath) && is_writable($parentPath)) {
                            @mkdir($fullPath, 0775, false);
                        } else {
                            throw new \Exception(
                                "Cannot create directory. Parent path: {$parentPath} " .
                                    (file_exists($parentPath) ? "exists but not writable" : "does not exist")
                            );
                        }
                    }
                }
            }

            // ✅ Pastikan folder bisa ditulis
            if (!is_writable($fullPath)) {
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

            // ✅ Generate nama file unik
            $timestamp = now()->format('YmdHis');
            $filename = "{$request->device_id}_screenshot_{$timestamp}.jpg";

            // ✅ Simpan file ke storage/app/public/screenshots
            $path = $request->file('screenshot')->storeAs($directory, $filename, 'public');

            if (!$path) {
                throw new \Exception('Failed to store screenshot file');
            }

            $url = Storage::url($path); // contoh: /storage/screenshots/xxxx.jpg
            $fullUrl = url($url);       // contoh: https://parentalcontrol.satelliteorbit.cloud/storage/screenshots/xxxx.jpg

            // ✅ Simpan ke database
            $screenshot = Screenshot::create([
                'device_id' => $device->device_id,
                'file_url' => $fullUrl,
                'timestamp' => now(),
            ]);

            return response()->json([
                'success' => true,
                'data' => $screenshot,
                'message' => 'Screenshot uploaded successfully',
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload screenshot: ' . $e->getMessage(),
            ], 500);
        }
    }


    public function index(Request $request, $deviceId)
    {
        $request->validate([
            'start_date' => 'date',
            'end_date' => 'date|after_or_equal:start_date',
            'limit' => 'integer|min:1|max:100',
        ]);

        $query = Screenshot::where('device_id', $deviceId)
            ->orderBy('timestamp', 'desc');

        if ($request->has('start_date')) {
            $query->where('timestamp', '>=', $request->start_date);
        }

        if ($request->has('end_date')) {
            $query->where('timestamp', '<=', $request->end_date);
        }

        $screenshots = $query->limit($request->input('limit', 50))->get();

        return response()->json([
            'success' => true,
            'data' => $screenshots,
        ]);
    }

    public function destroy($id)
    {
        $screenshot = Screenshot::findOrFail($id);

        // Delete file from storage
        $path = str_replace('/storage/', '', $screenshot->file_url);
        Storage::disk('public')->delete($path);

        $screenshot->delete();

        return response()->json([
            'success' => true,
            'message' => 'Screenshot deleted',
        ]);
    }
}
