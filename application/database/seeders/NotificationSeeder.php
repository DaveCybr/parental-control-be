<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class NotificationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('notifications')->insert([
            [
                'device_id' => 7,
                'app_name' => 'com.google.android.youtube',
                'title' => 'Video Baru Direkomendasikan',
                'content' => 'YouTube merekomendasikan video edukatif untuk anak Anda.',
                'timestamp' => Carbon::now()->subMinutes(5),
            ],
            [
                'device_id' => 7,
                'app_name' => 'com.whatsapp',
                'title' => 'Pesan Baru',
                'content' => 'Pesan masuk dari grup sekolah anak Anda.',
                'timestamp' => Carbon::now()->subMinutes(15),
            ],
            [
                'device_id' => 7,
                'app_name' => 'com.zhiliaoapp.musically',
                'title' => 'Batas Waktu Penggunaan',
                'content' => 'Penggunaan aplikasi TikTok hari ini telah mencapai batas yang ditentukan.',
                'timestamp' => Carbon::now()->subMinutes(30),
            ],
            [
                'device_id' => 7,
                'app_name' => 'com.android.chrome',
                'title' => 'Situs Diblokir',
                'content' => 'Akses ke situs dengan konten tidak sesuai usia telah diblokir otomatis.',
                'timestamp' => Carbon::now()->subHour(),
            ],
            [
                'device_id' => 7,
                'app_name' => 'com.google.android.apps.maps',
                'title' => 'Lokasi Terpantau',
                'content' => 'Anak Anda telah tiba di sekolah dengan aman.',
                'timestamp' => Carbon::now()->subMinutes(10),
            ],
        ]);
    }
}
