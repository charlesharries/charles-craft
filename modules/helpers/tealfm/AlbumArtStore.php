<?php

namespace helpers\tealfm;

use craft\awss3\S3Client;
use craft\helpers\App;
use InvalidArgumentException;

/**
 * Puts release cover art in S3 under a key derived from the release's
 * MusicBrainz ID, so anything rendering a listen can build the URL from data it
 * already holds - no lookup, and no round trip to the Cover Art Archive on the
 * request path.
 */
class AlbumArtStore
{
    const PREFIX = 'album-art';

    const CACHE_CONTROL = 'public, max-age=31536000, immutable';

    protected ?S3Client $client = null;

    /** Where a release's art lives in the bucket. */
    public static function key(string $environment, string $mbid): string
    {
        $environment = trim($environment, '/');
        $normalized = CoverArtArchive::normalizeMbid($mbid);

        if ($environment === '' || $normalized === null) {
            throw new InvalidArgumentException("can't build an album art key for '$mbid'");
        }

        return sprintf('%s/%s/%s.jpg', $environment, self::PREFIX, $normalized);
    }

    /** Get the public URL for a given album art */
    public static function url(string $environment, string $mbid): string
    {
        return '/assets/s3/' . self::key($environment, $mbid);
    }

    public static function isConfigured(): bool
    {
        return (bool) App::env('AWS_S3_BUCKET') && (bool) App::env('AWS_S3_LOCATION');
    }

    public function put(string $mbid, string $body, string $contentType): string
    {
        $key = self::key((string) App::env('ENVIRONMENT'), $mbid);

        $this->client()->putObject([
            'Bucket' => (string) App::env('AWS_S3_BUCKET'),
            'Key' => $key,
            'Body' => $body,
            'ContentType' => $contentType,
            'CacheControl' => self::CACHE_CONTROL,
        ]);

        return $key;
    }

    protected function client(): S3Client
    {
        return $this->client ??= new S3Client([
            'region' => App::env('AWS_S3_LOCATION'),
            'version' => '2006-03-01',
        ]);
    }
}
