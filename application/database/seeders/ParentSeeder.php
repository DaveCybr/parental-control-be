<?php
namespace Database\Seeders;

use App\Models\ParentModel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ParentSeeder extends Seeder
{
    public function run(): void
    {
        ParentModel::create([
            'email' => 'parent1@example.com',
            'password' => Hash::make('password123'),
            'family_code' => 'ABC123',
        ]);

        ParentModel::create([
            'email' => 'parent2@example.com',
            'password' => Hash::make('password123'),
            'family_code' => 'XYZ678',
        ]);
    }
}