<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\DeviceInfo;

class DeviceTrackingMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user) {
            $deviceId = $request->header('X-Device-ID');
            $deviceType = $request->header('X-Device-Type', 'unknown');
            $appVersion = $request->header('X-App-Version', '1.0.0');

            if ($deviceId) {
                DeviceInfo::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'device_id' => $deviceId
                    ],
                    [
                        'device_type' => $deviceType,
                        'app_version' => $appVersion,
                        'last_activity' => now(),
                        'ip_address' => $request->ip(),
                        'user_agent' => $request->userAgent()
                    ]
                );
            }
        }

        return $next($request);
    }
}
