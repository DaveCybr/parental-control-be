<?php

namespace Database\Seeders;

use App\Models\Alert;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class AlertSeeder extends Seeder
{
    public function run(): void
    {
        $children = User::where('role', 'child')->get();

        $alertTypes = [
            'geofence' => [
                'titles' => ['Geofence Alert', 'Location Warning', 'Safety Zone'],
                'messages' => [
                    'Child has left safe zone',
                    'Child entered danger zone',
                    'Child is outside designated area'
                ]
            ],
            'content' => [
                'titles' => ['Content Alert', 'Inappropriate Content', 'Keyword Detected'],
                'messages' => [
                    'Blocked keyword detected in notification',
                    'Potentially inappropriate content received',
                    'Suspicious message content'
                ]
            ],
            'battery' => [
                'titles' => ['Battery Warning', 'Low Battery'],
                'messages' => [
                    'Child device battery is critically low',
                    'Device battery below 10%'
                ]
            ]
        ];

        foreach ($children as $child) {
            // Create 2-5 alerts for each child over last week
            $alertCount = rand(2, 5);

            for ($i = 0; $i < $alertCount; $i++) {
                $type = array_rand($alertTypes);
                $typeData = $alertTypes[$type];

                Alert::create([
                    'child_user_id' => $child->id,
                    'type' => $type,
                    'priority' => ['critical', 'high', 'medium', 'low'][array_rand(['critical', 'high', 'medium', 'low'])],
                    'title' => $typeData['titles'][array_rand($typeData['titles'])],
                    'message' => $typeData['messages'][array_rand($typeData['messages'])],
                    'data' => $this->generateAlertData($type),
                    'is_read' => rand(0, 1) == 1,
                    'triggered_at' => Carbon::now()->subDays(rand(0, 7))->subHours(rand(0, 23)),
                ]);
            }
        }
    }

    private function generateAlertData(string $type): array
    {
        switch ($type) {
            case 'geofence':
                return [
                    'geofence_name' => ['Home', 'School', 'Mall Area'][array_rand(['Home', 'School', 'Mall Area'])],
                    'action' => ['entered', 'exited'][array_rand(['entered', 'exited'])],
                    'latitude' => -7.2575 + (rand(-100, 100) * 0.001),
                    'longitude' => 112.7521 + (rand(-100, 100) * 0.001),
                ];
            case 'content':
                return [
                    'app_package' => 'com.whatsapp',
                    'detected_keyword' => 'inappropriate',
                    'content_preview' => 'Message contains flagged content...',
                ];
            case 'battery':
                return [
                    'battery_level' => rand(1, 15),
                    'last_location' => [
                        'latitude' => -7.2575,
                        'longitude' => 112.7521,
                    ],
                ];
            default:
                return [];
        }
    }
}
