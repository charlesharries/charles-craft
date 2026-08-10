<?php

namespace modules\api\controllers;

use Craft;
use craft\helpers\App;
use craft\web\Controller;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use yii\web\BadRequestHttpException;
use yii\web\HttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Proxies release cover art from the Cover Art Archive, so that visitors to
 * /music don't hotlink it a couple of dozen times per page load.
 */
class AlbumArtController extends Controller
{
    protected array|bool|int $allowAnonymous = self::ALLOW_ANONYMOUS_LIVE;

    const COVER_ART_BASE = 'https://coverartarchive.org/release';

    const MISS_CACHE_TTL = 60 * 60 * 24;

    public function actionGet(?string $mbid = null): Response
    {
        $mbid = self::normalizeMbid($mbid);

        if (!$mbid) {
            throw new BadRequestHttpException('invalid release id');
        }

        $cacheTime = (int) (App::env('ALBUM_ART_CACHE_TTL') ?: 60 * 60 * 24 * 30);

        // Not getOrSet(): hits and misses are cached for different durations,
        // and getOrSet() takes a single duration up front.
        $art = Craft::$app->cache->get(['album-art', $mbid]);

        if ($art === false) {
            $art = $this->fetch($mbid);

            Craft::$app->cache->set(
                ['album-art', $mbid],
                $art,
                isset($art['found']) ? self::MISS_CACHE_TTL : $cacheTime,
            );
        }

        if (isset($art['found'])) {
            throw new NotFoundHttpException('no cover art for this release');
        }

        $headers = Craft::$app->response->headers;
        $headers->add('Content-Type', $art['contentType']);
        $headers->add('Cache-Control', "public, max-age=$cacheTime");
        $headers->add('Expires', gmdate('D, d M Y H:i:s \G\M\T', time() + $cacheTime));

        return $this->asRaw($art['body']);
    }

    /**
     * Lowercased MBID, or null if it isn't a UUID.
     *
     * Deliberately more permissive than craft\helpers\StringHelper::isUUID(),
     * which insists on a v4 UUID; not every MusicBrainz ID sets those bits.
     */
    public static function normalizeMbid(?string $mbid): ?string
    {
        $mbid = strtolower(trim((string) $mbid));

        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $mbid)
            ? $mbid
            : null;
    }

    /**
     * @return array{contentType: string, body: string}|array{found: false}
     */
    private function fetch(string $mbid): array
    {
        $client = Craft::createGuzzleClient([
            'timeout' => 10,
            // The Cover Art Archive asks callers to identify themselves.
            'headers' => ['User-Agent' => 'charles.craft/1.0 (+' . App::env('PRIMARY_SITE_URL') . ')'],
        ]);

        try {
            // Resolves via a 307 to archive.org, which Guzzle follows for us.
            $response = $client->get(self::COVER_ART_BASE . "/$mbid/front-250");
        } catch (ClientException $e) {
            return ['found' => false];
        } catch (GuzzleException $e) {
            throw new HttpException(502, 'cover art archive unavailable', 0, $e);
        }

        $contentType = $response->getHeaderLine('Content-Type');

        if (!str_starts_with($contentType, 'image/')) {
            return ['found' => false];
        }

        return ['contentType' => $contentType, 'body' => (string) $response->getBody()];
    }
}
