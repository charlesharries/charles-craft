<?php

namespace helpers\tealfm;

use Craft;
use craft\helpers\App;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;

/**
 * Reads release cover art from the Cover Art Archive.
 *
 * Only the sync path calls this - art is fetched once, when a release is first
 * seen, and served from S3 forever after. The archive is run by volunteers and
 * asks callers to identify themselves and pace themselves; both are honoured
 * here and in TealFmSyncService's loop.
 */
class CoverArtArchive
{
    const BASE = 'https://coverartarchive.org/release';

    const SIZE = 'front-250';

    const TIMEOUT = 10;

    protected ?Client $client = null;

    /**
     * Lowercased MBID, or null if it isn't a UUID.
     */
    public static function normalizeMbid(?string $mbid): ?string
    {
        $mbid = strtolower(trim((string) $mbid));

        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $mbid)
            ? $mbid
            : null;
    }

    /**
     * The front cover for a release, or null when the archive holds none.
     *
     * @return array{contentType: string, body: string}|null
     */
    public function fetch(string $mbid): ?array
    {
        try {
            // Resolves via a 307 to archive.org, which Guzzle follows for us.
            $response = $this->client()->get(self::BASE . "/$mbid/" . self::SIZE);
        } catch (ClientException $e) {
            return null;
        }

        $contentType = $response->getHeaderLine('Content-Type');

        if (!str_starts_with($contentType, 'image/')) {
            return null;
        }

        return ['contentType' => $contentType, 'body' => (string) $response->getBody()];
    }

    protected function client(): Client
    {
        return $this->client ??= Craft::createGuzzleClient([
            'timeout' => self::TIMEOUT,
            // The Cover Art Archive asks callers to identify themselves.
            'headers' => ['User-Agent' => 'charles.craft/1.0 (+' . App::env('PRIMARY_SITE_URL') . ')'],
        ]);
    }
}
