<?php

namespace Tests\Feature;

use App\Models\LiveStreamSetting;
use App\Models\Organisation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicLiveBroadcastTest extends TestCase
{
    use RefreshDatabase;

    private function organisation(string $slug, string $streamStatus = 'Live', array $attributes = []): Organisation
    {
        $org = Organisation::updateOrCreate(['slug' => $slug], array_merge([
            'name' => ucfirst($slug), 'slug' => $slug, 'status' => 'active',
        ], $attributes));
        LiveStreamSetting::withoutGlobalScopes()->updateOrCreate(['organisation_id' => $org->id], [
            'organisation_id' => $org->id, 'platform' => 'Rumble', 'status' => $streamStatus,
            'embed_url' => 'https://rumble.com/embed/example/',
            'watch_url' => 'https://rumble.com/example', 'stream_title' => $slug.' service',
            'notes' => 'Private operator notes', 'api_key' => 'private-stream-key',
        ]);
        return $org;
    }

    public function test_guests_can_watch_live_broadcasts_across_organisations_without_secrets(): void
    {
        $this->organisation('voca');
        $this->organisation('alpha', 'On air');
        $response = $this->getJson('/api/public/live-broadcasts')->assertOk()->assertJsonCount(2, 'data');
        $this->assertEqualsCanonicalizing(['alpha', 'voca'], collect($response->json('data'))->pluck('slug')->all());
        $this->assertStringNotContainsString('private-stream-key', $response->getContent());
        $this->assertStringNotContainsString('Private operator notes', $response->getContent());
        $this->assertFalse(array_key_exists('updated_by', $response->json('data.0.live')));
    }

    public function test_only_current_playable_broadcasts_from_active_enabled_organisations_appear(): void
    {
        $this->organisation('voca', 'Stand by');
        foreach (['Not live', 'Live ended', 'Offline', 'Scheduled', 'Previously live'] as $i => $status) {
            $this->organisation('offline-'.$i, $status);
        }
        $this->organisation('suspended', 'Live', ['status' => 'suspended']);
        $this->organisation('disabled', 'Live', ['modules' => ['livestreaming' => false]]);
        $empty = $this->organisation('empty');
        LiveStreamSetting::withoutGlobalScopes()->where('organisation_id', $empty->id)->update(['embed_url' => '', 'watch_url' => null]);
        $this->getJson('/api/public/live-broadcasts')->assertOk()->assertExactJson(['data' => []]);
    }

    public function test_ending_a_broadcast_removes_it_on_the_next_refresh(): void
    {
        $org = $this->organisation('voca');
        $this->getJson('/api/public/live-broadcasts')->assertOk()->assertJsonCount(1, 'data');
        LiveStreamSetting::withoutGlobalScopes()->where('organisation_id', $org->id)->update(['status' => 'Offline']);
        $this->getJson('/api/public/live-broadcasts')->assertOk()->assertExactJson(['data' => []]);
    }

    public function test_inside_path_is_reserved_and_not_resolved_as_an_organisation(): void
    {
        $this->organisation('voca');
        $this->get('/inside')->assertOk();
    }
}
