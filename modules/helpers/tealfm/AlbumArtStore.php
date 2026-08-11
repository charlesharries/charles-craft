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

    /**
     * Where a release's art lives in the bucket.
     *
     * $environment mirrors the `subfolder` setting on the S3 filesystem, which
     * is what keeps dev and production apart in the one bucket. It's a
     * parameter rather than an App::env() call so this stays pure enough to
     * unit test - the test suite has no Craft application behind it.
     *
     * The .jpg is cosmetic: the archive's front-250 thumbnails are JPEG in
     * practice, but it's the ContentType stored on the object that the
     * assets/s3 proxy hands back, not the extension.
     */
    public static function key(string $environment, string $mbid): string
    {
        $environment = trim($environment, '/');
        $normalized = CoverArtArchive::normalizeMbid($mbid);

        // An MBID ends up in an S3 key, so anything that isn't a UUID has to
        // die here rather than reach the bucket as a path.
        if ($environment === '' || $normalized === null) {
            throw new InvalidArgumentException("can't build an album art key for '$mbid'");
        }

        return sprintf('%s/%s/%s.jpg', $environment, self::PREFIX, $normalized);
    }

    /**
     * The URL a release's art is served from. The bucket itself is private, so
     * this goes through the assets/s3 proxy like every other S3 asset here.
     */
    public static function url(string $environment, string $mbid): string
    {
        return '/assets/s3/' . self::key($environment, $mbid);
    }

    /**
     * Whether there's enough AWS configuration to store anything. A machine
     * without credentials should skip the art pass with one warning, rather
     * than fail once per release a second apart.
     */
    public static function isConfigured(): bool
    {
        return (bool) App::env('AWS_S3_BUCKET') && (bool) App::env('AWS_S3_LOCATION');
    }

    public function put(string $mbid, string $body, string $contentType): string
    {
        $key = self::key((string) App::env('ENVIRONMENT'), $mbid);

        // No ACL parameter: the bucket is private and read through the
        // assets/s3 proxy, and buckets with object ownership enforced reject
        // the parameter outright - even 'private'.
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
        // Credentials come from AWS_ACCESS_KEY_ID / AWS_SECRET_ACCESS_KEY via
        // the SDK's own provider chain, as in AssetsController::actionS3().
        return $this->client ??= new S3Client([
            'region' => App::env('AWS_S3_LOCATION'),
            'version' => '2006-03-01',
        ]);
    }
}
