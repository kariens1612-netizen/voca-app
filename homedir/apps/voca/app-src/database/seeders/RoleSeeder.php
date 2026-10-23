<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Seed the application's roles.
     */
    public function run(): void
    {
        $roles = [
            [
                'slug' => 'admin',
                'name' => 'Admin',
                'description' => 'Ministry administrator',
            ],
            [
                'slug' => 'leader',
                'name' => 'Team leader',
                'description' => 'Ministry team leader',
            ],
            [
                'slug' => 'member',
                'name' => 'Member',
                'description' => 'Ministry member',
            ],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(
                ['slug' => $role['slug']],
                [
                    'name' => $role['name'],
                    'description' => $role['description'],
                ]
            );
        }
    }
}
