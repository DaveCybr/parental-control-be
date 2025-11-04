<?php

namespace App\Services;

use App\Models\Device;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class FCMService
{
    protected $projectId;
    protected $credentialsPath;
    protected $credentials;

    public function __construct()
    {
        $this->projectId = env('FIREBASE_PROJECT_ID');
        $this->credentialsPath = storage_path('app/firebase/service-account.json');
        $this->loadCredentials();
    }

    /**
     * Load service account credentials
     */
    protected function loadCredentials(): void
    {
        if (!file_exists($this->credentialsPath)) {
            throw new \Exception('Firebase credentials file not found at: ' . $this->credentialsPath);
        }

        $this->credentials = json_decode(file_get_contents($this->credentialsPath), true);

        if (!$this->credentials) {
            throw new \Exception('Invalid Firebase credentials JSON file');
        }
    }

    /**
     * Get OAuth2 access token using JWT
     */
    protected function getAccessToken(): string
    {
        // Cache token for 55 minutes (FCM tokens valid for 1 hour)
        return Cache::remember('fcm_access_token', 55 * 60, function () {
            return $this->generateAccessToken();
        });
    }

    /**
     * Generate OAuth2 access token
     */
    protected function generateAccessToken(): string
    {
        try {
            // Prepare JWT header
            $header = [
                'alg' => 'RS256',
                'typ' => 'JWT',
            ];

            // Prepare JWT claim set
            $now = time();
            $claimSet = [
                'iss' => $this->credentials['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => 'https://oauth2.googleapis.com/token',
                'exp' => $now + 3600,
                'iat' => $now,
            ];

            // Encode header and claim
            $base64UrlHeader = $this->base64UrlEncode(json_encode($header));
            $base64UrlClaimSet = $this->base64UrlEncode(json_encode($claimSet));

            // Create signature
            $signatureInput = $base64UrlHeader . '.' . $base64UrlClaimSet;

            $privateKey = openssl_pkey_get_private($this->credentials['private_key']);
            if (!$privateKey) {
                throw new \Exception('Invalid private key');
            }

            openssl_sign($signatureInput, $signature, $privateKey, OPENSSL_ALGO_SHA256);
            openssl_free_key($privateKey);

            $base64UrlSignature = $this->base64UrlEncode($signature);

            // Create JWT
            $jwt = $signatureInput . '.' . $base64UrlSignature;

            // Exchange JWT for access token
            $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

            if (!$response->successful()) {
                throw new \Exception('Failed to get access token: ' . $response->body());
            }

            $result = $response->json();

            if (!isset($result['access_token'])) {
                throw new \Exception('No access token in response');
            }

            return $result['access_token'];
        } catch (\Exception $e) {

            throw $e;
        }
    }

    /**
     * Base64 URL encode
     */
    protected function base64UrlEncode($data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Send command to child device via FCM v1 API
     */
    public function sendCommand(string $deviceId, array $command): array
    {
        try {
            // Get device
            $device = Device::where('device_id', $deviceId)->first();

            if (!$device) {
                return [
                    'success' => false,
                    'message' => 'Device not found'
                ];
            }

            if (!$device->hasValidFcmToken()) {
                return [
                    'success' => false,
                    'message' => 'Device FCM token not found'
                ];
            }

            // Send FCM notification
            $response = $this->sendFcmMessageV1($device->fcm_token, $command);

            return $response;
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to send command: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Send FCM message using v1 API
     */
    protected function sendFcmMessageV1(string $fcmToken, array $data): array
    {
        try {
            // Get access token
            $accessToken = $this->getAccessToken();

            // FCM v1 API URL
            $url = "https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send";

            // Prepare payload
            $payload = [
                'message' => [
                    'token' => $fcmToken,
                    'data' => $this->convertDataToStringArray($data),
                    'android' => [
                        'priority' => 'high',
                        'ttl' => '3600s',
                    ],
                ],
            ];



            // Send request
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
            ])->post($url, $payload);

            $result = $response->json();



            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => 'Command sent successfully',
                    'fcm_response' => $result
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'FCM send failed',
                    'fcm_response' => $result
                ];
            }
        } catch (\Exception $e) {


            return [
                'success' => false,
                'message' => 'FCM request failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Send notification to parent device
     */
    public function sendNotificationToParent(string $fcmToken, array $notification, array $data): array
    {
        try {
            // Get access token
            $accessToken = $this->getAccessToken();

            // FCM v1 API URL
            $url = "https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send";

            // Prepare payload with notification
            $payload = [
                'message' => [
                    'token' => $fcmToken,
                    'notification' => [
                        'title' => $notification['title'] ?? '',
                        'body' => $notification['body'] ?? '',
                    ],
                    'data' => $this->convertDataToStringArray($data),
                    'android' => [
                        'priority' => 'high',
                        'notification' => [
                            'sound' => 'default',
                            'channel_id' => 'geofence_alerts',
                            'priority' => 'high',
                            'default_vibrate_timings' => true,
                        ],
                    ],
                    'apns' => [
                        'payload' => [
                            'aps' => [
                                'sound' => 'default',
                                'badge' => 1,
                            ],
                        ],
                    ],
                ],
            ];

            // Send request
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
            ])->post($url, $payload);

            $result = $response->json();

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => 'Notification sent successfully',
                    'fcm_response' => $result
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'FCM send failed',
                    'fcm_response' => $result
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'FCM notification request failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Convert data to string array (FCM v1 requirement)
     */
    protected function convertDataToStringArray(array $data): array
    {
        $stringData = [];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $stringData[$key] = json_encode($value);
            } elseif (is_bool($value)) {
                $stringData[$key] = $value ? 'true' : 'false';
            } else {
                $stringData[$key] = (string) $value;
            }
        }

        return $stringData;
    }

    /**
     * Send photo capture command
     */
    public function sendCapturePhotoCommand(string $deviceId, bool $useFrontCamera = true): array
    {
        return $this->sendCommand($deviceId, [
            'type' => 'CAPTURE_PHOTO',
            'front_camera' => $useFrontCamera,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Send location request command
     */
    public function sendRequestLocationCommand(string $deviceId): array
    {
        return $this->sendCommand($deviceId, [
            'type' => 'REQUEST_LOCATION',
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Send start monitoring command
     */
    public function sendStartMonitoringCommand(string $deviceId): array
    {
        return $this->sendCommand($deviceId, [
            'type' => 'START_MONITORING',
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Send stop monitoring command
     */
    public function sendStopMonitoringCommand(string $deviceId): array
    {
        return $this->sendCommand($deviceId, [
            'type' => 'STOP_MONITORING',
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Send start screen monitor command
     */
    public function sendStartScreenMonitorCommand(string $deviceId): array
    {
        return $this->sendCommand($deviceId, [
            'type' => 'START_SCREEN_MONITOR',
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Send stop screen monitor command
     */
    public function sendStopScreenMonitorCommand(string $deviceId): array
    {
        return $this->sendCommand($deviceId, [
            'type' => 'STOP_SCREEN_MONITOR',
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Broadcast command to multiple devices
     */
    public function broadcastCommand(array $deviceIds, array $command): array
    {
        $results = [];

        foreach ($deviceIds as $deviceId) {
            $results[$deviceId] = $this->sendCommand($deviceId, $command);
        }

        return $results;
    }

    /**
     * Test FCM token validity
     */
    public function testToken(string $fcmToken): bool
    {
        try {
            $response = $this->sendFcmMessageV1($fcmToken, [
                'type' => 'TEST',
                'message' => 'FCM token test'
            ]);

            return $response['success'];
        } catch (\Exception $e) {
            return false;
        }
    }
}
