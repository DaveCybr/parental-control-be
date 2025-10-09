<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use App\Models\Device;
use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'device_id' => 'required|exists:devices,device_id',
            'app_name' => 'required|string',
            'title' => 'required|string',
            'content' => 'string',
        ]);

        $device = Device::where('device_id', $request->device_id)->firstOrFail();

        $notification = Notification::create([
            'device_id' => $device['device_id'],
            'app_name' => $request['app_name'],
            'title' => $request['title'],
            'content' => $request['content'],
            'timestamp' => now(),
        ]);

        return response()->json([
            'success' => true,
            'data' => $notification,
            'message' => 'Notification saved',
        ], 201);
    }

    public function index(Request $request, $deviceId)
    {
        $request->validate([
            'start_date' => 'date',
            'end_date' => 'date|after_or_equal:start_date',
            'app_name' => 'string',
            'limit' => 'integer|min:1|max:1000',
        ]);

        $query = Notification::where('device_id', $deviceId)
            ->orderBy('timestamp', 'desc');

        if ($request->has('start_date')) {
            $query->where('timestamp', '>=', $request->start_date);
        }

        if ($request->has('end_date')) {
            $query->where('timestamp', '<=', $request->end_date);
        }

        if ($request->has('app_name')) {
            $query->where('app_name', $request->app_name);
        }

        $notifications = $query->limit($request->input('limit', 100))->get();

        return response()->json([
            'success' => true,
            'data' => $notifications,
        ]);
    }
}
