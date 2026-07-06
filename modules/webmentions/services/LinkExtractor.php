<?php

namespace modules\webmentions\services;

class LinkExtractor
{
    /**
     * Extracts unique absolute http(s) links from HTML, excluding links back to the given host.
     *
     * @return string[]
     */
    public static function extractOutboundLinks(string $html, ?string $ownHost): array
    {
        if (trim($html) === '') {
            return [];
        }

        $document = new \DOMDocument();
        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_clear_errors();

        $links = [];

        foreach ($document->getElementsByTagName('a') as $anchor) {
            $href = trim($anchor->getAttribute('href'));
            if ($href === '') {
                continue;
            }

            $scheme = parse_url($href, PHP_URL_SCHEME);
            if (!in_array($scheme, ['http', 'https'], true)) {
                continue;
            }

            $host = parse_url($href, PHP_URL_HOST);
            if ($ownHost && $host === $ownHost) {
                continue;
            }

            $links[$href] = $href;
        }

        return array_values($links);
    }
}
