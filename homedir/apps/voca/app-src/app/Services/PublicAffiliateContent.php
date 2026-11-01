<?php
namespace App\Services;

use DOMDocument;
use DOMNode;
use DOMXPath;

class PublicAffiliateContent
{
    public function parse(string $html, string $base): array
    {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try { $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET); }
        finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
        $xpath = new DOMXPath($document);
        $class = fn (string $name) => "contains(concat(' ', normalize-space(@class), ' '), ' $name ')";
        $sections = [];
        $selector = '//section['.implode(' or ', array_map($class, [
            'voca-affiliate-hero', 'voca-affiliate-overview', 'voca-featured-affiliates',
            'voca-affiliate-directory', 'voca-affiliate-types', 'voca-member-world-map',
            'voca-member-group-hero', 'voca-member-group-section',
        ])).'] | //article['.$class('voca-directory-profile').']';
        foreach ($xpath->query($selector) as $section) {
            $cardSelector = implode(' or ', array_map($class, ['voca-featured-affiliate-card', 'voca-affiliate-card', 'platform-card', 'voca-member-profile-card']));
            $cards = [];
            foreach ($xpath->query('.//*['.$cardSelector.']', $section) as $card) $cards[] = $this->block($xpath, $card, $base);
            $copy = new DOMDocument();
            $copy->appendChild($copy->importNode($section, true));
            $copyXPath = new DOMXPath($copy);
            foreach (iterator_to_array($copyXPath->query('//*['.$cardSelector.']')) as $card) $card->parentNode?->removeChild($card);
            $block = $this->block($copyXPath, $copy->documentElement, $base);
            $block['cards'] = $cards;
            $block['id'] = $section->getAttribute('id');
            $sections[] = $block;
        }
        return ['sections' => $this->withFounders($sections)];
    }

    private function withFounders(array $sections): array
    {
        $names = ['anil', 'johnny', 'vincent', 'nathan'];
        $founders = [];
        $insertAt = null;
        foreach ($sections as $index => &$section) {
            $remaining = [];
            foreach ($section['cards'] as $card) {
                $name = null;
                foreach ($names as $candidate) {
                    if (preg_match('/\\b'. $candidate .'\\b/i', $card['title'])) {
                        $name = $candidate;
                        break;
                    }
                }
                if ($name === null) {
                    $remaining[] = $card;
                    continue;
                }
                $insertAt ??= $index;
                if (!isset($founders[$name])) {
                    $card['paragraphs'] = array_values(array_filter($card['paragraphs'], fn ($text) =>
                        !preg_match('/^(Visionary Leader|President|Founder|VOCA member|Individual(?:\\s+.*)?)$/i', $text)
                    ));
                    array_unshift($card['paragraphs'], 'Founder');
                    $founders[$name] = $card;
                }
            }
            $section['cards'] = $remaining;
        }
        unset($section);
        if ($insertAt !== null) {
            $section = [
                'id' => 'founders', 'title' => 'Founders', 'paragraphs' => [], 'images' => [], 'links' => [],
                'cards' => array_values(array_filter(array_replace(array_fill_keys($names, null), $founders))),
            ];
            array_splice($sections, $insertAt, 0, [$section]);
        }
        return $sections;
    }

    private function block(DOMXPath $xpath, DOMNode $node, string $base): array
    {
        $text = function (DOMNode $item) {
            if ($item->parentNode instanceof \DOMElement && str_contains($item->parentNode->getAttribute('class'), 'voca-affiliate-stats')) {
                return trim(implode(' ', array_map(fn ($child) => trim($child->textContent), iterator_to_array($item->childNodes))));
            }
            return trim(preg_replace('/\s+/u', ' ', $item->textContent));
        };
        $collect = fn (string $query) => array_values(array_filter(array_map($text, iterator_to_array($xpath->query($query, $node)))));
        $images = []; $links = [];
        foreach ($xpath->query('.//img', $node) as $image) {
            if ($url = $this->url($image->getAttribute('src'), $base)) $images[] = ['src' => $url, 'alt' => $image->getAttribute('alt')];
        }
        foreach ($xpath->query('.//a[@href]', $node) as $link) {
            if ($url = $this->url($link->getAttribute('href'), $base)) $links[] = ['url' => $url, 'label' => $text($link)];
        }
        return [
            'title' => $collect('.//h1 | .//h2 | .//h3 | .//h4')[0] ?? '',
            'paragraphs' => $collect('.//p | .//h1/following-sibling::h3 | .//div[contains(@class,"featured-affiliate-caption")]/span | .//div[contains(@class,"affiliate-meta")] | .//div[contains(@class,"affiliate-card-top")] | .//div[contains(@class,"directory-card-top")] | .//div[contains(@class,"voca-affiliate-stats")]/div'),
            'images' => $images, 'links' => $links,
        ];
    }

    private function url(string $url, string $base): ?string
    {
        if (str_starts_with($url, '//')) $url = 'https:'.$url;
        elseif (str_starts_with($url, '/')) $url = rtrim($base, '/').$url;
        elseif (str_starts_with($url, '#')) return '/affiliates'.$url;
        return filter_var($url, FILTER_VALIDATE_URL) && in_array(parse_url($url, PHP_URL_SCHEME), ['https', 'http']) ? $url : null;
    }
}
