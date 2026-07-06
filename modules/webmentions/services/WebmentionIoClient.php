<?php

namespace modules\webmentions\services;

use Craft;

class WebmentionIoClient
{
    private const BASE_URI = 'https://webmention.io';
    private const PER_PAGE = 100;

    public function __construct(private readonly string $token)
    {
    }

    /**
     * Fetches all mentions with a wm-id greater than $sinceId, oldest first, paginating as needed.
     *
     * @return array[] JF2 mention items
     */
    public function fetchMentions(?string $sinceId): array
    {
        $client = Craft::createGuzzleClient(['base_uri' => self::BASE_URI]);

        $mentions = [];
        $page = 0;

        do {
            $query = [
                'token' => $this->token,
                'per-page' => self::PER_PAGE,
                'page' => $page,
            ];

            if ($sinceId) {
                $query['since_id'] = $sinceId;
            }

            $response = $client->request('GET', '/api/mentions.jf2', [
                'query' => $query,
                'http_errors' => false,
            ]);

            $body = json_decode((string)$response->getBody(), true);
            $children = $body['children'] ?? [];

            $mentions = array_merge($mentions, $children);
            $page++;
        } while (count($children) === self::PER_PAGE);

        usort($mentions, fn($a, $b) => ($a['wm-id'] ?? 0) <=> ($b['wm-id'] ?? 0));

        return $mentions;
    }
}
