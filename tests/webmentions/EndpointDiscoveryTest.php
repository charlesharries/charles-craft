<?php

namespace tests\webmentions;

use modules\webmentions\services\EndpointDiscovery;
use PHPUnit\Framework\TestCase;

class EndpointDiscoveryTest extends TestCase
{
    public function test_finds_endpoint_in_link_header(): void
    {
        $header = '<https://example.com/webmention>; rel="webmention"';

        $this->assertSame(
            'https://example.com/webmention',
            EndpointDiscovery::endpointFromLinkHeader($header)
        );
    }

    public function test_finds_endpoint_among_multiple_link_header_values(): void
    {
        $header = '<https://example.com/other>; rel="alternate", <https://example.com/webmention>; rel=webmention';

        $this->assertSame(
            'https://example.com/webmention',
            EndpointDiscovery::endpointFromLinkHeader($header)
        );
    }

    public function test_returns_null_when_no_webmention_link_header(): void
    {
        $this->assertNull(EndpointDiscovery::endpointFromLinkHeader(''));
        $this->assertNull(EndpointDiscovery::endpointFromLinkHeader('<https://example.com>; rel="alternate"'));
    }

    public function test_finds_endpoint_in_link_tag(): void
    {
        $html = '<html><head><link rel="webmention" href="https://example.com/webmention"></head></html>';

        $this->assertSame('https://example.com/webmention', EndpointDiscovery::endpointFromHtml($html));
    }

    public function test_finds_endpoint_in_anchor_tag(): void
    {
        $html = '<html><body><a rel="webmention" href="/webmention">webmention</a></body></html>';

        $this->assertSame('/webmention', EndpointDiscovery::endpointFromHtml($html));
    }

    public function test_returns_null_when_no_endpoint_in_html(): void
    {
        $this->assertNull(EndpointDiscovery::endpointFromHtml('<html><body>hello</body></html>'));
        $this->assertNull(EndpointDiscovery::endpointFromHtml(''));
    }

    public function test_resolves_absolute_endpoint_unchanged(): void
    {
        $this->assertSame(
            'https://example.com/webmention',
            EndpointDiscovery::resolve('https://example.com/webmention', 'https://example.com/post')
        );
    }

    public function test_resolves_root_relative_endpoint(): void
    {
        $this->assertSame(
            'https://example.com/webmention',
            EndpointDiscovery::resolve('/webmention', 'https://example.com/blog/post')
        );
    }

    public function test_resolves_protocol_relative_endpoint(): void
    {
        $this->assertSame(
            'https://cdn.example.com/webmention',
            EndpointDiscovery::resolve('//cdn.example.com/webmention', 'https://example.com/post')
        );
    }

    public function test_resolves_path_relative_endpoint(): void
    {
        $this->assertSame(
            'https://example.com/blog/webmention',
            EndpointDiscovery::resolve('webmention', 'https://example.com/blog/post')
        );
    }
}
