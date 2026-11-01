<?php

namespace Tests\Feature;

use App\Http\Controllers\VocaAffiliateController;
use App\Services\MemberContentSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MemberContentSyncTest extends TestCase
{
    use RefreshDatabase;

    private function snapshot(): array
    {
        $pages = [];
        foreach (['', '/churches-ministries', '/businesses', '/pastors', '/individuals', '/partners'] as $suffix) {
            $pages[] = ['path' => '/members'.$suffix, 'content' => ['sections' => [
                ['id' => 'founders', 'title' => 'Founders', 'paragraphs' => [], 'images' => [], 'links' => [], 'cards' => []],
            ]]];
        }
        return ['version' => 1, 'pages' => $pages];
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.voca_members.url' => 'https://voca.africa', 'services.voca_members.key' => str_repeat('k', 32)]);
        Http::preventStrayRequests();
    }

    public function test_sync_saves_locally_and_display_requires_no_website_connection(): void
    {
        Http::fake(['https://voca.africa/api/mobile-members/content' => Http::response($this->snapshot())]);
        $this->assertSame(6, (new MemberContentSync)->run());
        Http::assertSent(fn ($request) => $request->hasHeader('X-Voca-Member-Key', str_repeat('k', 32)));
        Http::fake(['*' => Http::failedConnection()]);
        $response = (new VocaAffiliateController)->index();
        $this->assertSame('Founders', $response->getData(true)['sections'][0]['title']);
        Http::assertNothingSent();
    }

    public function test_incomplete_or_unavailable_source_retains_last_successful_copy(): void
    {
        Http::fake(['*' => Http::sequence()->push($this->snapshot())->push(['version' => 1, 'pages' => []])->push([], 503)]);
        (new MemberContentSync)->run();
        foreach ([1, 2] as $attempt) {
            try {
                (new MemberContentSync)->run();
                $this->fail('Invalid sync should fail');
            } catch (\Illuminate\Validation\ValidationException | \Illuminate\Http\Client\RequestException $error) {
                $this->assertDatabaseCount('synced_member_pages', 6);
            }
        }
    }

    public function test_successful_snapshot_removes_unpublished_profiles(): void
    {
        DB::table('synced_member_pages')->insert(['path' => '/affiliates/withdrawn', 'content' => '{}', 'synced_at' => now()]);
        Http::fake(['*' => Http::response($this->snapshot())]);
        (new MemberContentSync)->run();
        $this->assertDatabaseMissing('synced_member_pages', ['path' => '/affiliates/withdrawn']);
        $this->assertSame(404, (new VocaAffiliateController)->index('withdrawn')->getStatusCode());
    }

    public function test_missing_initial_snapshot_returns_setup_error(): void
    {
        $this->assertSame(503, (new VocaAffiliateController)->index()->getStatusCode());
        Http::assertNothingSent();
    }
}
