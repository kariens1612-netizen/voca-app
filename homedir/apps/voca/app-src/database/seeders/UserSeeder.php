<?php

namespace Database\Seeders;

use App\Models\Ministry;
use App\Models\MinistryMembership;
use App\Models\Role;
use App\Models\User;
use App\Models\Organisation;
use App\Models\OrganisationMembership;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Seed superadmin and admin users.
     */
    public function run(): void
    {
        $superadminEmail = env('SUPERADMIN_EMAIL', 'superadmin@voca.test');
        $superadminPassword = env('SUPERADMIN_PASSWORD', 'Superadmin123!');
        $superadminName = env('SUPERADMIN_NAME', 'Super Admin');

        $superadmin = User::updateOrCreate(
            ['email' => $superadminEmail],
            [
                'name' => $superadminName,
                'password' => Hash::make($superadminPassword),
                'is_superadmin' => true,
            ]
        );

        $organisation = Organisation::where('slug', config('tenancy.default_slug', 'voca'))->first();
        if ($organisation) OrganisationMembership::updateOrCreate(
            ['organisation_id' => $organisation->id, 'user_id' => $superadmin->id],
            ['role' => 'owner', 'status' => 'active']
        );

        $adminEmail = env('ADMIN_EMAIL', 'admin@voca.test');
        $adminPassword = env('ADMIN_PASSWORD', 'Admin123!');
        $adminName = env('ADMIN_NAME', 'Ministry Admin');

        $adminUser = User::updateOrCreate(
            ['email' => $adminEmail],
            [
                'name' => $adminName,
                'password' => Hash::make($adminPassword),
                'is_superadmin' => false,
            ]
        );

        if ($organisation) OrganisationMembership::updateOrCreate(
            ['organisation_id' => $organisation->id, 'user_id' => $adminUser->id],
            ['role' => 'admin', 'status' => 'active']
        );

        $adminRole = Role::where('slug', 'admin')->first();
        $memberRole = Role::where('slug', 'member')->first();

        $entity = env('ADMIN_MINISTRY_ENTITY', 'brackenfell');
        $slug = env('ADMIN_MINISTRY_SLUG', 'coffee');
        $ministry = Ministry::where('entity', $entity)->where('slug', $slug)->first();

        if ($adminRole && $ministry) {
            MinistryMembership::updateOrCreate(
                ['user_id' => $adminUser->id, 'ministry_id' => $ministry->id],
                [
                    'role' => 'admin',
                    'role_id' => $adminRole->id,
                ]
            );
        }

        if ($memberRole) {
            MinistryMembership::whereNull('role_id')
                ->where('role', 'member')
                ->update(['role_id' => $memberRole->id]);
        }

        if ($adminRole) {
            MinistryMembership::whereNull('role_id')
                ->where('role', 'admin')
                ->update(['role_id' => $adminRole->id]);
        }
    }
}
