<?php
namespace Tests\Feature;

use App\Http\Controllers\VocaAffiliateController;
use App\Services\PublicAffiliateContent;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\HttpException;
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

    public function test_founders_appear_before_affiliates_without_duplicate_cards(): void
    {
        $html = '<section class="voca-featured-affiliates"><h2>Featured Affiliates</h2>';
        foreach (['Apostle Vincent Rhoda', 'Dr Johnny Johnson', 'Apostle Anil Manilal', 'Dr Francois Engelbrecht'] as $name) {
            $html .= '<div class="voca-featured-affiliate-card"><h3>'.$name.'</h3><div class="featured-affiliate-caption"><span>Visionary Leader</span><p>Ministry</p></div></div>';
        }
        $html .= '</section><section class="voca-affiliate-directory"><h2>Affiliates</h2><div class="voca-affiliate-card"><h3>Nathan Momsen</h3><div class="affiliate-card-top">Individual Cape Town</div><p>VOCA member</p></div></section>';
        $sections = (new PublicAffiliateContent)->parse($html, 'https://voca.africa')['sections'];
        $this->assertSame('Founders', $sections[0]['title']);
        $this->assertSame(['Apostle Anil Manilal', 'Dr Johnny Johnson', 'Apostle Vincent Rhoda', 'Nathan Momsen'], array_column($sections[0]['cards'], 'title'));
        foreach ($sections[0]['cards'] as $card) $this->assertSame('Founder', $card['paragraphs'][0]);
        $this->assertSame(['Founder', 'Ministry'], $sections[0]['cards'][0]['paragraphs']);
        $this->assertSame(['Founder'], $sections[0]['cards'][3]['paragraphs']);
        $this->assertSame(['Dr Francois Engelbrecht'], array_column($sections[1]['cards'], 'title'));
        $this->assertSame([], $sections[2]['cards']);
    }

    public function test_profiles_and_pathway_cards_are_read_as_app_content(): void
    {
        $parser = new PublicAffiliateContent;
        $profile = $parser->parse('<article class="voca-directory-profile"><h1>Example Leader</h1><p>Public biography</p></article>', 'https://voca.africa');
        $this->assertSame('Public biography', $profile['sections'][0]['paragraphs'][0]);
        $pathway = $parser->parse('<section class="voca-member-group-section"><h2>Churches</h2><div class="voca-member-profile-card"><h3>Example Church</h3><p>Public description</p></div></section>', 'https://voca.africa');
        $this->assertSame('Example Church', $pathway['sections'][0]['cards'][0]['title']);
    }

    public function test_backend_reads_the_website_and_caches_content(): void
    {
        config(['services.voca_members.url' => 'https://voca.africa']);
        Http::preventStrayRequests();
        Http::fake(['https://voca.africa/affiliates' => Http::response('<section class="voca-affiliate-hero"><h1>Our network</h1></section>')]);
        $controller = new VocaAffiliateController;
        $response = $controller->index(new PublicAffiliateContent);
        $this->assertSame('Our network', $response->getData(true)['sections'][0]['title']);
        $controller->index(new PublicAffiliateContent);
        Http::assertSentCount(1);
    }

    public function test_unrecognized_website_markup_is_not_treated_as_an_empty_directory(): void
    {
        config(['services.voca_members.url' => 'https://voca.africa']);
        Http::fake(['*' => Http::response('<html>Maintenance</html>')]);
        try {
            (new VocaAffiliateController)->index(new PublicAffiliateContent);
            $this->fail('Expected an unavailable response.');
        } catch (HttpException $error) {
            $this->assertSame(503, $error->getStatusCode());
        }
    }

    public function test_connection_failure_returns_a_retryable_error(): void
    {
        config(['services.voca_members.url' => 'https://voca.africa']);
        Http::fake(['*' => Http::failedConnection()]);
        $response = (new VocaAffiliateController)->index(new PublicAffiliateContent);
        $this->assertSame(503, $response->getStatusCode());
    }
}
