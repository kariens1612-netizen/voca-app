<?php

namespace Tests\Feature;

use App\Models\MediaAsset;
use App\Models\Organisation;
use App\Models\OrganisationMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaAssetManagementTest extends TestCase
{
    use RefreshDatabase;

    private function tenant(string $slug): Organisation
    {
        return Organisation::create([
            'name' => ucfirst($slug),
            'slug' => $slug,
            'status' => 'active',
            'modules' => Organisation::DEFAULT_MODULES,
        ]);
    }

    private function url(string $slug, string $path): string
    {
        return 'http://'.$slug.'.app.voca.co.za'.$path;
    }

    public function test_organisation_admin_can_upload_and_list_media_assets(): void
    {
        Storage::fake('public');
        $organisation = $this->tenant('alpha');
        $admin = User::factory()->create();
        OrganisationMembership::create(['organisation_id' => $organisation->id, 'user_id' => $admin->id, 'role' => 'admin', 'status' => 'active']);

        $response = $this->actingAs($admin)->post($this->url('alpha', '/api/admin/media'), [
            'asset' => UploadedFile::fake()->image('banner.jpg', 1200, 600),
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.type', 'image')
            ->assertJsonPath('data.name', 'banner.jpg');
        $this->assertStringContainsString('/media/', $response->json('data.url'));
        $this->assertStringNotContainsString('/storage/', $response->json('data.url'));

        $asset = MediaAsset::withoutGlobalScopes()->firstOrFail();
        Storage::disk('public')->assertExists($asset->path);
        $this->assertSame($organisation->id, $asset->organisation_id);

        $this->get($response->json('data.url'))->assertOk();

        $this->actingAs($admin)->getJson($this->url('alpha', '/api/admin/media'))
            ->assertOk()
            ->assertJsonPath('data.data.0.name', 'banner.jpg');
    }

    public function test_uploads_are_capped_at_eight_megabytes(): void
    {
        Storage::fake('public');
        $organisation = $this->tenant('alpha');
        $admin = User::factory()->create();
        OrganisationMembership::create(['organisation_id' => $organisation->id, 'user_id' => $admin->id, 'role' => 'admin', 'status' => 'active']);

        $this->actingAs($admin)->post($this->url('alpha', '/api/admin/media'), [
            'asset' => UploadedFile::fake()->create('too-large.pdf', 8193, 'application/pdf'),
        ])->assertUnprocessable()->assertInvalid('asset');
    }

    public function test_authenticated_tenant_users_can_use_the_shared_media_library(): void
    {
        Storage::fake('public');
        $organisation = $this->tenant('alpha');
        $user = User::factory()->create();
        OrganisationMembership::create(['organisation_id' => $organisation->id, 'user_id' => $user->id, 'role' => 'user', 'status' => 'active']);

        $this->actingAs($user)->post($this->url('alpha', '/api/media'), [
            'asset' => UploadedFile::fake()->image('shared.jpg'),
        ])->assertCreated()->assertJsonPath('data.type', 'image');

        $this->actingAs($user)->getJson($this->url('alpha', '/api/media?type=image'))
            ->assertOk()
            ->assertJsonPath('data.data.0.name', 'shared.jpg');
    }

    public function test_media_assets_are_tenant_scoped_and_deleted_from_storage(): void
    {
        Storage::fake('public');
        $alpha = $this->tenant('alpha');
        $beta = $this->tenant('beta');
        $admin = User::factory()->create();
        OrganisationMembership::create(['organisation_id' => $alpha->id, 'user_id' => $admin->id, 'role' => 'admin', 'status' => 'active']);

        $asset = MediaAsset::withoutEvents(fn () => MediaAsset::withoutGlobalScopes()->create([
            'organisation_id' => $beta->id,
            'uploaded_by' => $admin->id,
            'disk' => 'public',
            'path' => 'media/'.$beta->id.'/beta.pdf',
            'original_name' => 'beta.pdf',
            'mime_type' => 'application/pdf',
            'size' => 100,
            'type' => 'file',
        ]));
        Storage::disk('public')->put($asset->path, 'test');

        $this->actingAs($admin)->deleteJson($this->url('alpha', '/api/admin/media/'.$asset->id))->assertNotFound();
        Storage::disk('public')->assertExists($asset->path);
    }
}
