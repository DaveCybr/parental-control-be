<?php

namespace Database\Seeders;

use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class FamilySeeder extends Seeder
{
    public function run(): void
    {
        $parent = User::where('email', 'parent@demo.com')->first();
        $child1 = User::where('email', 'alice@demo.com')->first();
        $child2 = User::where('email', 'bob@demo.com')->first();

        // Create demo family
        $family = Family::create([
            'name' => 'Doe Family',
            'family_code' => 'DOE' . rand(10000, 99999),
            'created_by' => $parent->id,
        ]);

        // Add family members
        FamilyMember::create([
            'family_id' => $family->id,
            'user_id' => $parent->id,
            'role' => 'parent',
            'is_primary' => true,
        ]);

        FamilyMember::create([
            'family_id' => $family->id,
            'user_id' => $child1->id,
            'role' => 'child',
            'is_primary' => false,
        ]);

        FamilyMember::create([
            'family_id' => $family->id,
            'user_id' => $child2->id,
            'role' => 'child',
            'is_primary' => false,
        ]);

        // Create additional test families
        $parents = User::where('role', 'parent')->where('id', '>', 1)->get();
        $children = User::where('role', 'child')->where('id', '>', 3)->get();

        foreach ($parents->take(5) as $index => $parent) {
            $family = Family::create([
                'name' => $parent->name . ' Family',
                'family_code' => Str::random(3) . rand(10000, 99999),
                'created_by' => $parent->id,
            ]);

            // Add parent to family
            FamilyMember::create([
                'family_id' => $family->id,
                'user_id' => $parent->id,
                'role' => 'parent',
                'is_primary' => true,
            ]);

            // Add 1-3 children to family
            $familyChildren = $children->skip($index * 3)->take(rand(1, 3));
            foreach ($familyChildren as $child) {
                FamilyMember::create([
                    'family_id' => $family->id,
                    'user_id' => $child->id,
                    'role' => 'child',
                    'is_primary' => false,
                ]);
            }
        }
    }
}
