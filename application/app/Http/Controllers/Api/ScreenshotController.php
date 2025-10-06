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

        $device = Device::where('device_id', $request->device_id)->firstOrFail();

        // Upload file
        $path = $request->file('screenshot')->store('screenshots', 'public');
        $url = Storage::url($path);

        $screenshot = Screenshot::create([
            'device_id' => $device->device_id,
            'file_url' => $url,
            'timestamp' => now(),
        ]);

        return response()->json([
            'success' => true,
            'data' => $screenshot,
            'message' => 'Screenshot uploaded',
        ], 201);
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