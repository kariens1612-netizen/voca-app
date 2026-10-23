<?php

namespace Tests\Feature;

use App\Models\InspirationContent;
use App\Models\Ministry;
use App\Models\MinistryMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InspirationContentTest extends TestCase
{
    use RefreshDatabase;

    private function ministry(string $slug): Ministry
    {
        return Ministry::create(['entity' => 'brackenfell', 'slug' => $slug, 'name' => ucfirst($slug)]);
    }

    private function content(Ministry $ministry, User $creator, array $overrides = []): InspirationContent
    {
        return InspirationContent::create(array_merge([
            'ministry_id' => $ministry->id,
            'created_by' => $creator->id,
            'type' => 'encouragement',
            'title' => 'A hopeful word',
            'slug' => 'a-hopeful-word',
            'summary' => 'Be encouraged today.',
            'audience' => 'public',
            'status' => 'published',
            'published_at' => now(),
        ], $overrides));
    }

    public function test_public_feed_only_returns_visible_public_content(): void
    {
        $ministry = $this->ministry('media');
        $user = User::factory()->create();
        $this->content($ministry, $user);
        $this->content($ministry, $user, ['title' => 'Private', 'slug' => 'private', 'audience' => 'members']);
        $this->content($ministry, $user, ['title' => 'Draft', 'slug' => 'draft', 'status' => 'draft']);

        $this->getJson('/inspiration/content')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'a-hopeful-word');
    }

    public function test_scheduled_content_becomes_visible_at_its_publish_time(): void
    {
        $ministry = $this->ministry('media');
        $user = User::factory()->create();
        $this->content($ministry, $user, ['status' => 'scheduled', 'publish_at' => now()->subMinute()]);
        $this->content($ministry, $user, ['title' => 'Future', 'slug' => 'future', 'status' => 'scheduled', 'publish_at' => now()->addDay()]);

        $this->getJson('/inspiration/content')->assertJsonCount(1, 'data');
    }

    public function test_ministry_admin_cannot_create_content_for_another_ministry(): void
    {
        $own = $this->ministry('own');
        $other = $this->ministry('other');
        $admin = User::factory()->create();
        MinistryMembership::create(['user_id' => $admin->id, 'ministry_id' => $own->id, 'role' => 'admin']);

        $payload = ['ministry_id' => $other->id, 'type' => 'scripture', 'title' => 'Test', 'audience' => 'public', 'status' => 'draft'];
        $this->actingAs($admin)->postJson('/inspiration/admin/content', $payload)->assertForbidden();
        $payload['ministry_id'] = $own->id;
        $this->actingAs($admin)->postJson('/inspiration/admin/content', $payload)->assertCreated();
    }

    public function test_members_only_media_is_protected_and_public_media_is_streamed(): void
    {
        Storage::fake('local');
        $ministry = $this->ministry('media');
        $admin = User::factory()->create(['is_superadmin' => true]);
        $private = $this->content($ministry, $admin, ['audience' => 'members']);

        $response = $this->actingAs($admin)->post('/inspiration/admin/content/'.$private->id, [
            'cover' => UploadedFile::fake()->image('cover.jpg'),
        ]);
        $response->assertOk();
        $media = $private->fresh()->media()->first();

        auth()->logout();
        $this->get(route('inspiration.media', $media))->assertNotFound();
        $this->actingAs($admin)->get(route('inspiration.media', $media))->assertOk();

        $private->update(['audience' => 'public']);
        auth()->logout();
        $this->get(route('inspiration.media', $media))->assertOk();
    }

    public function test_unsupported_video_hosts_and_unsafe_html_are_handled(): void
    {
        $ministry = $this->ministry('media');
        $admin = User::factory()->create(['is_superadmin' => true]);
        $payload = [
            'ministry_id' => $ministry->id, 'type' => 'video', 'title' => 'Clip',
            'audience' => 'public', 'status' => 'draft', 'external_video_url' => 'https://example.com/video',
        ];
        $this->actingAs($admin)->postJson('/inspiration/admin/content', $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('external_video_url');

        unset($payload['external_video_url']);
        $payload['type'] = 'encouragement';
        $payload['body'] = '<p onclick="bad()">Safe</p><script>alert(1)</script>';
        $this->actingAs($admin)->postJson('/inspiration/admin/content', $payload)->assertCreated();
        $saved = InspirationContent::first();
        $this->assertStringNotContainsString('onclick', $saved->body);
        $this->assertStringNotContainsString('script', $saved->body);
    }
}
