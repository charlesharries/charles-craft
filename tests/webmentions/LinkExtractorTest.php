<?php

namespace tests\webmentions;

use modules\webmentions\services\LinkExtractor;
use PHPUnit\Framework\TestCase;

class LinkExtractorTest extends TestCase
{
    public function test_extracts_outbound_links(): void
    {
        $html = '<p>Check out <a href="https://example.com/post">this post</a> and '
            . '<a href="https://another.example/thing">this one</a>.</p>';

        $links = LinkExtractor::extractOutboundLinks($html, 'charlesharri.es');

        $this->assertSame(['https://example.com/post', 'https://another.example/thing'], $links);
    }

    public function test_excludes_links_to_own_host(): void
    {
        $html = '<a href="https://charlesharri.es/writing/other-post">self link</a>'
            . '<a href="https://example.com/post">external link</a>';

        $links = LinkExtractor::extractOutboundLinks($html, 'charlesharri.es');

        $this->assertSame(['https://example.com/post'], $links);
    }

    public function test_excludes_non_http_links(): void
    {
        $html = '<a href="mailto:test@example.com">email</a>'
            . '<a href="#section">anchor</a>'
            . '<a href="https://example.com/post">external</a>';

        $links = LinkExtractor::extractOutboundLinks($html, 'charlesharri.es');

        $this->assertSame(['https://example.com/post'], $links);
    }

    public function test_deduplicates_repeated_links(): void
    {
        $html = '<a href="https://example.com/post">first</a>'
            . '<a href="https://example.com/post">second</a>';

        $links = LinkExtractor::extractOutboundLinks($html, 'charlesharri.es');

        $this->assertSame(['https://example.com/post'], $links);
    }

    public function test_returns_empty_array_for_empty_body(): void
    {
        $this->assertSame([], LinkExtractor::extractOutboundLinks('', 'charlesharri.es'));
    }
}
