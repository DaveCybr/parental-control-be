<?php
// routes/api.php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\FamilyController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\GeofenceController;
use App\Http\Controllers\Api\ScreenController;
use App\Http\Controllers\Api\AlertController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\DashboardController;
// use App\Http\Controllers\Api\DeviceController;
use Illuminate\Http\Request;


/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public routes
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

// Protected routes
Route::middleware('auth:sanctum')->group(function () {

    // Authentication
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/user', [AuthController::class, 'user']);
        Route::put('/profile', [AuthController::class, 'updateProfile']);
    });

    // Family Management
    Route::prefix('family')->group(function () {
        Route::post('/create', [FamilyController::class, 'create']);
        Route::post('/join', [FamilyController::class, 'join']);
        Route::get('/members', [FamilyController::class, 'members']);
        Route::get('/info', [FamilyController::class, 'info']);
        Route::put('/update', [FamilyController::class, 'update']);
        Route::delete('/leave', [FamilyController::class, 'leave']);
        Route::delete('/remove-member/{userId}', [FamilyController::class, 'removeMember']);
    });

    // Location Tracking
    Route::prefix('location')->group(function () {
        // Child app endpoints
        Route::post('/update', [LocationController::class, 'update']);

        // Parent app endpoints
        Route::get('/track/{childId}', [LocationController::class, 'track']);
        Route::get('/history/{childId}', [LocationController::class, 'history']);
        Route::get('/all-children', [LocationController::class, 'trackAllChildren']);
    });

    // Geofencing
    Route::prefix('geofence')->group(function () {
        Route::post('/create', [GeofenceController::class, 'create']);
        Route::get('/list', [GeofenceController::class, 'list']);
        Route::put('/{id}', [GeofenceController::class, 'update']);
        Route::delete('/{id}', [GeofenceController::class, 'delete']);
        Route::post('/{id}/toggle', [GeofenceController::class, 'toggle']);
    });

    // Notification Mirroring
    Route::prefix('notification')->group(function () {
        // Child app endpoints
        Route::post('/send', [NotificationController::class, 'send']);
        Route::post('/batch-send', [NotificationController::class, 'batchSend']);

        // Parent app endpoints
        Route::get('/list/{childId}', [NotificationController::class, 'list']);
        Route::get('/unread/{childId}', [NotificationController::class, 'unread']);
        Route::post('/mark-read', [NotificationController::class, 'markRead']);
        Route::get('/statistics/{childId}', [NotificationController::class, 'statistics']);
    });

    // Screen Mirroring
    Route::prefix('screen')->group(function () {
        // Parent app endpoints
        Route::post('/start-session', [ScreenController::class, 'startSession']);
        Route::post('/end-session', [ScreenController::class, 'endSession']);
        Route::get('/active-sessions', [ScreenController::class, 'activeSessions']);

        // Child app endpoints
        Route::get('/active-session/{childId}', [ScreenController::class, 'getActiveSession']);
        Route::post('/screenshot', [ScreenController::class, 'sendScreenshot']);
        Route::post('/stream-frame', [ScreenController::class, 'sendStreamFrame']);
    });

    // Alerts & Emergency
    Route::prefix('alert')->group(function () {
        // Child app endpoints
        Route::post('/trigger', [AlertController::class, 'trigger']);
        Route::post('/emergency', [AlertController::class, 'emergency']);

        // Parent app endpoints
        Route::get('/list', [AlertController::class, 'list']);
        Route::post('/mark-read', [AlertController::class, 'markRead']);
        Route::get('/unread-count', [AlertController::class, 'unreadCount']);
        Route::delete('/{id}', [AlertController::class, 'delete']);
    });

    // Settings & Configuration
    Route::prefix('settings')->group(function () {
        Route::get('/{childId}', [SettingController::class, 'get']);
        Route::put('/{childId}', [SettingController::class, 'update']);
        Route::post('/{childId}/notification-filters', [SettingController::class, 'updateNotificationFilters']);
        Route::post('/{childId}/blocked-keywords', [SettingController::class, 'updateBlockedKeywords']);
    });

    // Dashboard & Analytics
    Route::prefix('dashboard')->group(function () {
        Route::get('/parent', [DashboardController::class, 'parentDashboard']);
        Route::get('/child', [DashboardController::class, 'childDashboard']);
        Route::get('/analytics/{childId}', [DashboardController::class, 'childAnalytics']);
    });

    // Device Management
    // Route::prefix('device')->group(function () {
    //     Route::post('/register', [DeviceController::class, 'register']);
    //     Route::put('/update-info', [DeviceController::class, 'updateInfo']);
    //     Route::get('/list', [DeviceController::class, 'list']);
    //     Route::delete('/{deviceId}', [DeviceController::class, 'remove']);
    // });
});

// WebSocket Broadcasting Authentication
Route::middleware('auth:sanctum')->post('/broadcasting/auth', function (Request $request) {
    return response()->json(['status' => 'success']);
});
