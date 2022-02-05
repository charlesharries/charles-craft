<?php

namespace helpers\spotify;

use Craft;
use craft\helpers\App;
use yii\caching\Cache;

class Track
{
    protected \GuzzleHttp\Client $client;

    protected string $trackID;

    const TOKEN_ENDPOINT = 'https://accounts.spotify.com/api/token';

    const TRACK_ENDPOINT = 'https://api.spotify.com/v1/tracks';

    public function __construct(string $trackID)
    {
        $this->trackID = $trackID;
    }

    public static function extractFields($track)
    {
        $toArtist = fn ($artist) => ['id' => $artist->id, 'name' => $artist->name];

        return [
            'id' => $track->id,
            'album' => [
                'id' => $track->album->id,
                'images' => $track->album->images,
                'name' => $track->album->name,
                'artists' => array_map($toArtist, $track->album->artists),
                'release_date' => $track->album->release_date
            ],
            'artists' => array_map($toArtist, $track->artists),
            'name' => $track->name,
            'popularity' => $track->popularity,
            'preview_url' => $track->preview_url,
            'track_number' => $track->track_number,
            'duration_ms' => $track->duration_ms,
        ];
    }

    public function transform()
    {
        $cache = Craft::$app->getCache();
        $cacheKey = 'track:' . $this->trackID;

        return $cache->getOrSet($cacheKey, function () use ($cache, $cacheKey) {
            $accessToken = $this->getAccessToken();
            $client = Craft::createGuzzleClient();

            $res = $client->request('GET', $this->endpoint(), [
                'headers' => ['Authorization' => "Bearer $accessToken"],
            ]);

            return self::extractFields(json_decode($res->getBody()));
        }, 7 * 24 * 60 * 60);
    }

    protected function auth()
    {
        $auth = App::env('SPOTIFY_CLIENT_ID') . ':' . App::env('SPOTIFY_CLIENT_SECRET');

        return base64_encode($auth);
    }

    protected function endpoint()
    {
        return self::TRACK_ENDPOINT . '/' . $this->trackID;
    }

    protected function getAccessToken()
    {
        $client = Craft::createGuzzleClient(['base_uri' => 'https://accounts.spotify.com']);

        $res = $client->post('/api/token', [
            'auth' => [App::env('SPOTIFY_CLIENT_ID'), App::env('SPOTIFY_CLIENT_SECRET')],
            'form_params' => [
                'grant_type' => 'refresh_token',
                'refresh_token' => App::env('SPOTIFY_REFRESH_TOKEN'),
            ],
        ]);

        $body = json_decode($res->getBody());

        return $body->access_token;
    }
}
