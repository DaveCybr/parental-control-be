<?php
// File: database/seeders/DatabaseSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            FamilySeeder::class,
            GeofenceSeeder::class,
            AppSettingsSeeder::class,
            LocationSeeder::class,
            NotificationMirrorSeeder::class,
            AlertSeeder::class,
        ]);
    }
}

// File: database/seeders/UserSeeder.php


// File: database/seeders/FamilySeeder.php


// File: database/seeders/GeofenceSeeder.php

// File: database/seeders/AppSettingsSeeder.php

// File: database/seeders/LocationSeeder.php

// File: database/seeders/NotificationMirrorSeeder.php

// File: database/seeders/AlertSeeder.php

// File: database/factories/FamilyFactory.php


// File: database/factories/LocationFactory.php

// File: database/factories/AlertFactory.php
