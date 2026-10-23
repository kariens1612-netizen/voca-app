<?php

namespace Tests\Feature;

use App\Models\Ministry;
use App\Models\MinistryMembership;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MinistryMemberManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_ministry_admin_can_register_a_new_member(): void
    {
        [$admin, $ministry] = $this->ministryAdmin();

        $response = $this->actingAs($admin)->postJson(
            "/brackenfell/ministries/{$ministry->slug}/members/register",
            [
                'name' => 'New Member',
                'email' => 'NEW.MEMBER@example.com',
                'role' => 'member',
            ]
        );

        $response
            ->assertCreated()
            ->assertJsonPath('name', 'New Member')
            ->assertJsonPath('email', 'new.member@example.com')
            ->assertJsonPath('created_user', true);

        $user = User::query()->where('email', 'new.member@example.com')->firstOrFail();
        $this->assertDatabaseHas('ministry_memberships', [
            'ministry_id' => $ministry->id,
            'user_id' => $user->id,
            'role' => 'member',
        ]);
    }

    public function test_removing_member_only_deletes_ministry_membership(): void
    {
        [$admin, $ministry] = $this->ministryAdmin();
        $member = User::factory()->create();
        MinistryMembership::create([
            'user_id' => $member->id,
            'ministry_id' => $ministry->id,
            'role' => 'member',
            'role_id' => Role::query()->where('slug', 'member')->value('id'),
        ]);

        $this->actingAs($admin)
            ->deleteJson("/brackenfell/ministries/{$ministry->slug}/members/{$member->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('ministry_memberships', [
            'ministry_id' => $ministry->id,
            'user_id' => $member->id,
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $member->id,
            'email' => $member->email,
        ]);
    }

    public function test_non_admin_cannot_manage_ministry_members(): void
    {
        [, $ministry] = $this->ministryAdmin();
        $member = User::factory()->create();
        MinistryMembership::create([
            'user_id' => $member->id,
            'ministry_id' => $ministry->id,
            'role' => 'member',
            'role_id' => Role::query()->where('slug', 'member')->value('id'),
        ]);

        $this->actingAs($member)
            ->postJson("/brackenfell/ministries/{$ministry->slug}/members/register", [
                'name' => 'Blocked User',
                'email' => 'blocked@example.com',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'blocked@example.com']);
    }

    private function ministryAdmin(): array
    {
        $adminRole = Role::create(['slug' => 'admin', 'name' => 'Admin']);
        Role::create(['slug' => 'leader', 'name' => 'Leader']);
        Role::create(['slug' => 'member', 'name' => 'Member']);

        $ministry = Ministry::create([
            'entity' => 'brackenfell',
            'slug' => 'test-ministry',
            'name' => 'Test Ministry',
        ]);
        $admin = User::factory()->create();
        MinistryMembership::create([
            'user_id' => $admin->id,
            'ministry_id' => $ministry->id,
            'role' => 'admin',
            'role_id' => $adminRole->id,
        ]);

        return [$admin, $ministry];
    }
}
