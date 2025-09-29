<?php

// routes/api.php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\GeofenceController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ScreenshotController;
// use App\Http\Controllers\Api\AlertController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public routes
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
});

// Device pairing (no auth required for child device)
Route::post('devices/pair', [DeviceController::class, 'pair']);

// Device routes (for child device to send data)
Route::prefix('device')->group(function () {
    Route::post('locations', [LocationController::class, 'store']);
    Route::post('notifications', [NotificationController::class, 'store']);
    Route::post('screenshots', [ScreenshotController::class, 'store']);
    Route::put('{deviceId}/status', [DeviceController::class, 'updateStatus']);
});

// Protected routes (for parent dashboard)
Route::middleware('auth:sanctum')->group(function () {
    
    // Auth
    Route::prefix('auth')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('profile', [AuthController::class, 'profile']);
    });

    // Devices
    Route::prefix('devices')->group(function () {
        Route::get('/', [DeviceController::class, 'index']);
        Route::get('{id}', [DeviceController::class, 'show']);
        Route::delete('{id}', [DeviceController::class, 'destroy']);
    });

    // Locations
    Route::prefix('locations')->group(function () {
        Route::get('{deviceId}', [LocationController::class, 'index']);
        Route::get('{deviceId}/latest', [LocationController::class, 'latest']);
    });

    // Geofences
    Route::prefix('geofences')->group(function () {
        Route::get('/', [GeofenceController::class, 'index']);
        Route::post('/', [GeofenceController::class, 'store']);
        Route::put('{id}', [GeofenceController::class, 'update']);
        Route::delete('{id}', [GeofenceController::class, 'destroy']);
    });

    // Notifications
    Route::get('notifications/{deviceId}', [NotificationController::class, 'index']);

    // Screenshots
    Route::prefix('screenshots')->group(function () {
        Route::get('{deviceId}', [ScreenshotController::class, 'index']);
        Route::delete('{id}', [ScreenshotController::class, 'destroy']);
    });

    // // Alerts
    // Route::prefix('alerts')->group(function () {
    //     Route::get('/', [AlertController::class, 'index']);
    //     Route::get('unread-count', [AlertController::class, 'unreadCount']);
    //     Route::put('{id}/read', [AlertController::class, 'markAsRead']);
    //     Route::put('mark-all-read', [AlertController::class, 'markAllAsRead']);
    //     Route::delete('{id}', [AlertController::class, 'destroy']);
    // });
});