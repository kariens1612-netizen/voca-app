<?php

namespace Tests\Feature;

use App\Models\Organisation;
use App\Models\OrganisationMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnerSetupTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(string $role = 'owner'): Organisation
    {
        $org = Organisation::create(['name' => 'Alpha', 'slug' => 'alpha', 'status' => 'active', 'modules' => Organisation::DEFAULT_MODULES, 'branding' => ['footer_text' => 'Keep me'], 'settings' => ['locale' => 'en']]);
        $user = User::factory()->create();
        OrganisationMembership::create(['organisation_id' => $org->id, 'user_id' => $user->id, 'role' => $role, 'status' => 'active']);
        $this->actingAs($user);
        return $org;
    }

    private function payload(): array
    {
        return ['name' => 'New Alpha', 'branding' => ['name' => 'New Alpha', 'description' => 'Our community', 'contact' => ['email' => 'hello@example.org']], 'settings' => ['timezone' => 'Africa/Johannesburg'], 'modules' => ['events' => false], 'first_ministry' => ['name' => 'Worship', 'slug' => 'worship']];
    }

    public function test_owner_can_finish_once_with_brand_modules_and_ministry(): void
    {
        $org = $this->fixture();
        $this->postJson('http://alpha.app.voca.co.za/api/admin/setup', $this->payload())->assertOk();
        $org->refresh();
        $this->assertSame('New Alpha', $org->name);
        $this->assertSame('Keep me', $org->branding['footer_text']);
        $this->assertFalse($org->modules['events']);
        $this->assertNotEmpty($org->settings['onboarding']['completed_at']);
        $this->assertDatabaseHas('ministries', ['organisation_id' => $org->id, 'slug' => 'worship']);
        $this->postJson('http://alpha.app.voca.co.za/api/admin/setup', $this->payload())->assertConflict();
        $this->assertDatabaseCount('ministries', 1);
    }

    public function test_invalid_modules_roll_back_brand_and_completion(): void
    {
        $org = $this->fixture();
        $this->postJson('http://alpha.app.voca.co.za/api/admin/setup', array_replace($this->payload(), ['modules' => ['unknown' => true]]))->assertUnprocessable();
        $this->assertSame('Alpha', $org->fresh()->name);
        $this->assertNull(data_get($org->fresh()->settings, 'onboarding.completed_at'));
        $this->assertDatabaseCount('ministries', 0);
    }

    public function test_admin_cannot_complete_owner_setup(): void
    {
        $this->fixture('admin');
        $this->postJson('http://alpha.app.voca.co.za/api/admin/setup', $this->payload())->assertForbidden();
    }

    public function test_regular_user_cannot_complete_owner_setup(): void
    {
        $this->fixture('user');
        $this->postJson('http://alpha.app.voca.co.za/api/admin/setup', $this->payload())->assertForbidden();
    }

    public function test_owner_cannot_complete_another_organizations_setup(): void
    {
        $this->fixture();
        Organisation::create(['name' => 'Beta', 'slug' => 'beta', 'status' => 'active']);
        $this->postJson('http://beta.app.voca.co.za/api/admin/setup', $this->payload())->assertForbidden();
    }
}
