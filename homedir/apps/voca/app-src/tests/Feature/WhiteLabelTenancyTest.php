<?php

namespace Tests\Feature;

use App\Models\Ministry;
use App\Models\LiveStreamSetting;
use App\Models\Organisation;
use App\Models\OrganisationMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhiteLabelTenancyTest extends TestCase
{
    use RefreshDatabase;

    private function tenant(string $slug, array $overrides = []): Organisation
    {
        return Organisation::create(array_merge([
            'name' => ucfirst($slug), 'slug' => $slug, 'status' => 'active',
            'modules' => Organisation::DEFAULT_MODULES,
            'branding' => ['name' => ucfirst($slug), 'primary_colour' => '#123456'],
        ], $overrides));
    }

    private function url(string $slug, string $path): string { return 'http://'.$slug.'.app.voca.co.za'.$path; }

    public function test_bootstrap_resolves_independent_brand_and_modules_by_subdomain(): void
    {
        $alpha = $this->tenant('alpha');
        $beta = $this->tenant('beta', ['branding' => ['name' => 'Beta Brand', 'primary_colour' => '#abcdef'], 'modules' => array_merge(Organisation::DEFAULT_MODULES, ['events' => false])]);

        $this->getJson($this->url('alpha', '/api/bootstrap'))
            ->assertOk()->assertJsonPath('organisation.id', $alpha->id)
            ->assertJsonPath('organisation.branding.name', 'Alpha')
            ->assertJsonPath('organisation.modules.events', true);
        $this->getJson($this->url('beta', '/api/bootstrap'))
            ->assertOk()->assertJsonPath('organisation.id', $beta->id)
            ->assertJsonPath('organisation.branding.name', 'Beta Brand')
            ->assertJsonPath('organisation.modules.events', false);
    }

    public function test_route_binding_and_global_scope_hide_another_tenants_ministry(): void
    {
        $alpha = $this->tenant('alpha');
        $beta = $this->tenant('beta');
        Ministry::create(['organisation_id' => $alpha->id, 'entity' => 'alpha', 'slug' => 'shared', 'name' => 'Alpha Ministry']);
        Ministry::create(['organisation_id' => $beta->id, 'entity' => 'beta', 'slug' => 'private', 'name' => 'Beta Private']);

        $this->getJson($this->url('alpha', '/api/ministries/private'))->assertNotFound();
        $this->getJson($this->url('alpha', '/api/ministries'))
            ->assertOk()->assertJsonCount(1, 'ministries')->assertJsonPath('ministries.0.name', 'Alpha Ministry');
    }

    public function test_organisation_admin_can_configure_own_tenant_but_not_another(): void
    {
        $alpha = $this->tenant('alpha');
        $beta = $this->tenant('beta');
        $admin = User::factory()->create();
        OrganisationMembership::create(['organisation_id' => $alpha->id, 'user_id' => $admin->id, 'role' => 'admin', 'status' => 'active']);

        $this->actingAs($admin)->patchJson($this->url('alpha', '/api/admin/settings'), [
            'branding' => ['name' => 'Alpha White Label', 'primary_colour' => '#112233'],
        ])->assertOk()->assertJsonPath('data.branding.name', 'Alpha White Label');

        $this->actingAs($admin)->patchJson($this->url('beta', '/api/admin/settings'), [
            'branding' => ['name' => 'Hijacked'],
        ])->assertForbidden();
        $this->assertSame('Beta', $beta->fresh()->name);
    }

    public function test_last_owner_cannot_be_removed_and_superadmin_can_manage_organisations(): void
    {
        $alpha = $this->tenant('alpha');
        $owner = User::factory()->create();
        $membership = OrganisationMembership::create(['organisation_id' => $alpha->id, 'user_id' => $owner->id, 'role' => 'owner', 'status' => 'active']);
        $this->actingAs($owner)
            ->deleteJson($this->url('alpha', '/api/admin/users/'.$membership->id))->assertUnprocessable();

        $superadmin = User::factory()->create(['is_superadmin' => true]);
        $this->actingAs($superadmin)->postJson('/api/platform/organisations', ['name' => 'Gamma', 'slug' => 'gamma'])
            ->assertCreated()->assertJsonPath('data.slug', 'gamma');
        $this->assertDatabaseHas('tenant_audits', ['action' => 'organisation.created']);
    }

    public function test_disabled_module_is_not_exposed(): void
    {
        $alpha = $this->tenant('alpha', ['modules' => array_merge(Organisation::DEFAULT_MODULES, ['public_ministries' => false])]);
        Ministry::create(['organisation_id' => $alpha->id, 'entity' => 'alpha', 'slug' => 'hidden', 'name' => 'Hidden']);
        $this->getJson($this->url('alpha', '/api/ministries'))->assertNotFound();
    }

    public function test_public_home_directory_combines_active_ministries_with_live_and_social_links(): void
    {
        $alpha = $this->tenant('alpha', [
            'branding' => [
                'name' => 'Alpha Church',
                'tagline' => 'A family ministry',
                'description' => 'Alpha public ministry profile.',
                'thumbnail_url' => 'https://example.com/alpha.jpg',
                'social_links' => ['facebook' => 'https://facebook.com/alpha'],
            ],
        ]);
        $this->tenant('paused', ['status' => 'suspended']);
        Ministry::create(['organisation_id' => $alpha->id, 'entity' => 'alpha', 'slug' => 'worship', 'name' => 'Worship']);
        LiveStreamSetting::withoutGlobalScopes()->create([
            'organisation_id' => $alpha->id,
            'platform' => 'Rumble',
            'status' => 'Currently live',
            'schedule' => 'Sunday 09H30',
            'embed_url' => 'https://rumble.com/embed/v123/?pub=abc',
            'watch_url' => 'https://rumble.com/v123-alpha-live.html',
        ]);

        $this->getJson('/api/public/ministry-directory')
            ->assertOk()
            ->assertJsonMissing(['name' => 'Paused'])
            ->assertJsonPath('data.0.name', 'Alpha Church')
            ->assertJsonPath('data.0.live.is_live', true)
            ->assertJsonPath('data.0.live.watch_url', 'https://rumble.com/v123-alpha-live.html')
            ->assertJsonPath('data.0.social_links.0.platform', 'Facebook')
            ->assertJsonPath('data.0.ministries.0.name', 'Worship');
    }

    public function test_unknown_and_suspended_tenants_are_rejected(): void
    {
        $this->tenant('paused', ['status' => 'suspended']);
        $this->getJson($this->url('paused', '/api/bootstrap'))->assertStatus(423);
        $this->getJson($this->url('missing', '/api/bootstrap'))->assertNotFound();
    }
}
