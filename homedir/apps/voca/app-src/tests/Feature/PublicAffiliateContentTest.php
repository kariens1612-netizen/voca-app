<?php
namespace Tests\Feature;

use App\Services\PublicAffiliateContent;
use Tests\TestCase;

class PublicAffiliateContentTest extends TestCase
{
    public function test_public_content_excludes_navigation_scripts_and_unsafe_links(): void
    {
        $data = (new PublicAffiliateContent)->parse('<header>Private navigation</header><section class="voca-featured-affiliates"><h2>Featured Affiliates</h2><div class="voca-featured-affiliate-card"><img src="/photo.jpg" alt="Portrait"><h3>Example Leader</h3><p>Example Ministry</p><a href="/affiliates/example">More info</a><a href="javascript:alert(1)">Unsafe</a><script>alert(1)</script></div></section>', 'https://voca.africa');
        $section = $data['sections'][0];
        $this->assertSame('Featured Affiliates', $section['title']);
        $this->assertSame([], $section['paragraphs']);
        $this->assertSame('Example Leader', $section['cards'][0]['title']);
        $this->assertSame(['Example Ministry'], $section['cards'][0]['paragraphs']);
        $this->assertSame('https://voca.africa/photo.jpg', $section['cards'][0]['images'][0]['src']);
        $this->assertCount(1, $section['cards'][0]['links']);
        $this->assertStringNotContainsString('alert', json_encode($data));
        $this->assertStringNotContainsString('Private navigation', json_encode($data));
    }

    public function test_website_order_and_member_roles_are_preserved(): void
    {
        $html = '<section class="voca-featured-affiliates"><h2>Founders</h2><div class="voca-featured-affiliate-card"><h3>Nathan Momsen</h3><p>Founder</p></div></section><section class="voca-affiliate-directory"><h2>Affiliates</h2><div class="voca-affiliate-card"><h3>Nathan Momsen</h3><p>VOCA member</p></div></section>';
        $sections = (new PublicAffiliateContent)->parse($html, 'https://voca.africa')['sections'];
        $this->assertCount(2, $sections);
        $this->assertSame('Founders', $sections[0]['title']);
        $this->assertSame(['Founder'], $sections[0]['cards'][0]['paragraphs']);
        $this->assertSame('Affiliates', $sections[1]['title']);
        $this->assertSame('Nathan Momsen', $sections[1]['cards'][0]['title']);
        $this->assertSame(['VOCA member'], $sections[1]['cards'][0]['paragraphs']);
    }

    public function test_profiles_and_pathway_cards_are_read_as_app_content(): void
    {
        $parser = new PublicAffiliateContent;
        $profile = $parser->parse('<article class="voca-directory-profile"><h1>Example Leader</h1><p>Public biography</p></article>', 'https://voca.africa');
        $this->assertSame('Public biography', $profile['sections'][0]['paragraphs'][0]);
        $pathway = $parser->parse('<section class="voca-member-group-section"><h2>Churches</h2><div class="voca-member-profile-card"><h3>Example Church</h3><p>Public description</p></div></section>', 'https://voca.africa');
        $this->assertSame('Example Church', $pathway['sections'][0]['cards'][0]['title']);
    }

}
