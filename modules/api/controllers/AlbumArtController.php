<?php

namespace modules\api\controllers;

use Craft;
use craft\helpers\App;
use craft\web\Controller;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use helpers\tealfm\AlbumArtStore;
use helpers\tealfm\TealFmAlbumArtRepository;
use yii\web\BadRequestHttpException;
use yii\web\HttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Points callers at the release cover art the teal.fm sync stored in S3.
 *
 * A compatibility shim, really: art now arrives via TealFmSyncService, and
 * anything that can derive the URL from a release's MBID should call
 * AlbumArtStore::url() and skip the round trip. What's left here is the
 * fallback to the Cover Art Archive for releases the sync hasn't resolved yet.
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

        $status = (new TealFmAlbumArtRepository())->status($mbid);

        if ($status === TealFmAlbumArtRepository::STATUS_STORED) {
            Craft::$app->response->headers->add('Cache-Control', "public, max-age=$cacheTime");

            return $this->redirect(AlbumArtStore::url((string) App::env('ENVIRONMENT'), $mbid));
        }

        if ($status === TealFmAlbumArtRepository::STATUS_MISSING) {
            throw new NotFoundHttpException('no cover art for this release');
        }

        throw new NotFoundHttpException('release cover art not yet synced');
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
