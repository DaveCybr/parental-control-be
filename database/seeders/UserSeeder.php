<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Create demo parent account
        $parent = User::create([
            'name' => 'John Doe',
            'email' => 'parent@demo.com',
            'password' => Hash::make('password'),
            'role' => 'parent',
            'phone' => '+628123456789',
            'email_verified_at' => now(),
        ]);

        // Create demo child accounts
        $child1 = User::create([
            'name' => 'Alice Doe',
            'email' => 'alice@demo.com',
            'password' => Hash::make('password'),
            'role' => 'child',
            'phone' => '+628987654321',
            'email_verified_at' => now(),
        ]);

        $child2 = User::create([
            'name' => 'Bob Doe',
            'email' => 'bob@demo.com',
            'password' => Hash::make('password'),
            'role' => 'child',
            'phone' => '+628555666777',
            'email_verified_at' => now(),
        ]);

        // Create additional test users
        User::factory(10)->create(['role' => 'parent']);
        User::factory(15)->create(['role' => 'child']);
    }
}
