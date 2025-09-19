<?php

namespace Database\Seeders;

use App\Models\NotificationMirror;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class NotificationMirrorSeeder extends Seeder
{
    public function run(): void
    {
        $children = User::where('role', 'child')->get();

        $sampleNotifications = [
            [
                'app_package' => 'com.whatsapp',
                'titles' => ['Sarah', 'Mom', 'Study Group', 'Best Friends'],
                'contents' => [
                    'Hey, want to hang out after school?',
                    'Don\'t forget to pick up groceries',
                    'Math homework due tomorrow',
                    'Movie night this weekend!'
                ]
            ],
            [
                'app_package' => 'com.instagram.android',
                'titles' => ['Instagram', 'sarah_123', 'school_official'],
                'contents' => [
                    'sarah_123 liked your photo',
                    'New story from sarah_123',
                    'School announcement: Sports day next week'
                ]
            ],
            [
                'app_package' => 'org.telegram.messenger',
                'titles' => ['Telegram', 'Class Group', 'Family Chat'],
                'contents' => [
                    'New message in Class Group',
                    'Assignment uploaded in Class Group',
                    'Family dinner at 7 PM'
                ]
            ],
            [
                'app_package' => 'com.zhiliaoapp.musically',
                'titles' => ['TikTok', 'For You'],
                'contents' => [
                    'Someone liked your video',
                    'New videos from people you follow',
                    'Trending in your area'
                ]
            ]
        ];

        foreach ($children as $child) {
            // Create notifications for last 3 days
            for ($day = 3; $day >= 0; $day--) {
                $date = Carbon::now()->subDays($day);

                // Create 5-15 notifications per day
                $notificationCount = rand(5, 15);

                for ($i = 0; $i < $notificationCount; $i++) {
                    $appData = $sampleNotifications[array_rand($sampleNotifications)];

                    NotificationMirror::create([
                        'child_user_id' => $child->id,
                        'app_package' => $appData['app_package'],
                        'title' => $appData['titles'][array_rand($appData['titles'])],
                        'content' => $appData['contents'][array_rand($appData['contents'])],
                        'priority' => rand(1, 5),
                        'category' => ['message', 'social', 'update', 'reminder'][array_rand(['message', 'social', 'update', 'reminder'])],
                        'is_read' => rand(0, 1) == 1,
                        'timestamp' => $date->copy()->setHour(rand(7, 22))->setMinute(rand(0, 59)),
                    ]);
                }
            }
        }
    }
}
