<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberContentExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_requires_the_shared_key(): void
    {
        config(['services.mobile_members.key' => str_repeat('k', 32)]);
        $this->getJson('/api/mobile-members/content')->assertForbidden();
        $this->withHeader('X-Voca-Member-Key', 'wrong')->getJson('/api/mobile-members/content')->assertForbidden();
    }

    public function test_export_contains_public_website_sections_and_profiles(): void
    {
        config(['services.mobile_members.key' => str_repeat('k', 32)]);
        $response = $this->withHeader('X-Voca-Member-Key', str_repeat('k', 32))
            ->getJson('/api/mobile-members/content')->assertOk()->assertJsonPath('version', 1);
        $pages = collect($response->json('pages'))->keyBy('path');
        foreach (['/members', '/members/churches-ministries', '/members/businesses', '/members/pastors', '/members/individuals', '/members/partners', '/affiliates/anil'] as $path) {
            $this->assertTrue($pages->has($path), $path);
            $this->assertNotEmpty($pages[$path]['content']['sections']);
        }
        $sections = $pages['/members']['content']['sections'];
        $founders = collect($sections)->firstWhere('title', 'Founders');
        $this->assertCount(4, $founders['cards']);
        $this->assertSame('Apostle Anil Manilal', $founders['cards'][0]['title']);
        $this->assertStringNotContainsString('password', $response->getContent());
        $this->assertStringNotContainsString('source_data', $response->getContent());
    }
}
