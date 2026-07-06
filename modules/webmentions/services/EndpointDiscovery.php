<?php

namespace modules\webmentions\services;

use Craft;
use GuzzleHttp\Exception\GuzzleException;

class EndpointDiscovery
{
    /**
     * Discovers a page's webmention endpoint, per https://www.w3.org/TR/webmention/#sender-discovers-receiver-webmention-endpoint
     */
    public function discover(string $url): ?string
    {
        try {
            $response = Craft::createGuzzleClient()->request('GET', $url, [
                'http_errors' => false,
                'allow_redirects' => ['track_redirects' => true],
                'timeout' => 10,
            ]);
        } catch (GuzzleException $e) {
            Craft::warning("Failed to fetch $url for webmention endpoint discovery: {$e->getMessage()}", 'webmentions');
            return null;
        }

        $redirects = array_filter(explode(',', $response->getHeaderLine('X-Guzzle-Redirect-History')));
        $finalUrl = trim((string)(end($redirects) ?: $url));

        $linkHeader = self::endpointFromLinkHeader($response->getHeaderLine('Link'));
        if ($linkHeader) {
            return self::resolve($linkHeader, $finalUrl);
        }

        $body = (string)$response->getBody();
        $endpoint = self::endpointFromHtml($body);
        if ($endpoint) {
            return self::resolve($endpoint, $finalUrl);
        }

        return null;
    }

    public static function endpointFromLinkHeader(string $linkHeader): ?string
    {
        if ($linkHeader === '') {
            return null;
        }

        foreach (explode(',', $linkHeader) as $link) {
            if (preg_match('/rel="?webmention"?/i', $link) && preg_match('/<([^>]+)>/', $link, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

    public static function endpointFromHtml(string $html): ?string
    {
        if (trim($html) === '') {
            return null;
        }

        $document = new \DOMDocument();
        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_clear_errors();

        foreach (['link', 'a'] as $tag) {
            foreach ($document->getElementsByTagName($tag) as $element) {
                $rel = strtolower(trim($element->getAttribute('rel')));
                if ($rel === 'webmention' && $element->getAttribute('href') !== '') {
                    return $element->getAttribute('href');
                }
            }
        }

        return null;
    }

    public static function resolve(string $endpoint, string $baseUrl): string
    {
        if (parse_url($endpoint, PHP_URL_SCHEME)) {
            return $endpoint;
        }

        $base = parse_url($baseUrl);
        $scheme = $base['scheme'] ?? 'https';
        $host = $base['host'] ?? '';

        if (str_starts_with($endpoint, '//')) {
            return "$scheme:$endpoint";
        }

        if (str_starts_with($endpoint, '/')) {
            return "$scheme://$host$endpoint";
        }

        $basePath = rtrim(dirname($base['path'] ?? '/'), '/');
        return "$scheme://$host$basePath/$endpoint";
    }
}
