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
     *
     * Deliberately more permissive than craft\helpers\StringHelper::isUUID(),
     * which insists on a v4 UUID; not every MusicBrainz ID sets those bits.
     *
     * Duplicated from modules\api\controllers\AlbumArtController, which goes
     * away with the client-rendered /music page - collapse the two when it does.
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
     * A 4xx is a settled fact about the release, and comes back as null for the
     * caller to record as a miss. Anything else - a timeout, a 5xx, the 503
     * archive.org serves when it's throttling - is a fact about the network
     * instead, and throws: recording those as misses would leave releases
     * permanently marked art-less over one bad afternoon.
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
