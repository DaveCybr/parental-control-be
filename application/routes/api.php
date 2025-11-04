<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\CommandController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\GeofenceController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ScreenshotController;
use App\Http\Controllers\Api\CapturedPhotoController;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/

// Auth
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
});

Route::post('update-fcm-token', [DeviceController::class, 'updateFcmToken']);

// Device pairing (no auth required for child device)
Route::prefix('devices')->group(function () {
    Route::post('pair', [DeviceController::class, 'pair']);
    Route::post('verify', [DeviceController::class, 'verify']);
    Route::post('unpair', [DeviceController::class, 'unpair']);
});

// Device data submission (no auth required for child device)
Route::prefix('device')->group(function () {
    Route::post('locations', [LocationController::class, 'store']);
    Route::post('notifications', [NotificationController::class, 'store']);
    Route::post('screenshots', [ScreenshotController::class, 'store']);
    Route::post('captured-photos', [CapturedPhotoController::class, 'store']);
    Route::put('{deviceId}/status', [DeviceController::class, 'updateStatus']);
});

/*
|--------------------------------------------------------------------------
| Protected Routes (Parent Only)
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::prefix('auth')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('profile', [AuthController::class, 'profile']);
        Route::post('update-fcm-token', [AuthController::class, 'updateFcmToken']); // BARU
        Route::post('check-device', [AuthController::class, 'checkConnectedDevices']);
    });

    // Devices
    Route::prefix('devices')->group(function () {
        Route::get('/', [DeviceController::class, 'index']);
        Route::get('{id}', [DeviceController::class, 'show']);
        Route::delete('{id}', [DeviceController::class, 'destroy']);
        Route::get('/id_device/{id}', [DeviceController::class, 'getDevicesByParent']);
    });

    // Commands - Parent send commands to child
    Route::prefix('commands')->group(function () {
        Route::post('capture-photo', [CommandController::class, 'capturePhoto']);
        Route::post('screen-capture', [CommandController::class, 'screenCapture']);
        Route::post('request-location', [CommandController::class, 'requestLocation']);
        Route::post('start-monitoring', [CommandController::class, 'startMonitoring']);
        Route::post('stop-monitoring', [CommandController::class, 'stopMonitoring']);
        Route::post('start-screen-monitor', [CommandController::class, 'startScreenMonitor']);
        Route::post('stop-screen-monitor', [CommandController::class, 'stopScreenMonitor']);
        Route::post('custom', [CommandController::class, 'sendCustomCommand']);
        Route::post('broadcast', [CommandController::class, 'broadcastCommand']);
        Route::post('test-fcm-token', [CommandController::class, 'testFcmToken']);
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

    // Captured Photos
    Route::prefix('captured-photos')->group(function () {
        Route::get('{deviceId}', [CapturedPhotoController::class, 'index']);
        Route::get('{deviceId}/latest', [CapturedPhotoController::class, 'latest']);
        Route::delete('{id}', [CapturedPhotoController::class, 'destroy']);
        Route::post('bulk-delete', [CapturedPhotoController::class, 'bulkDestroy']);
    });
});
