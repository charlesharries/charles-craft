<?php

namespace modules\standardsite\services;

use GuzzleHttp\Client;

class AtProtoClient
{
    private Client $client;
    private ?string $accessToken = null;
    private string $did;

    public function __construct()
    {
        $this->client = new Client([
            'base_uri' => 'https://bsky.social/xrpc/',
            'timeout' => 30,
        ]);
    }

    public function authenticate(): void
    {
        $identifier = getenv('BLUESKY_IDENTIFIER');
        $password = getenv('BLUESKY_APP_PASSWORD');

        if (!$identifier || !$password) {
            throw new \RuntimeException('BLUESKY_IDENTIFIER and BLUESKY_APP_PASSWORD must be set');
        }

        $response = $this->client->post('com.atproto.server.createSession', [
            'json' => [
                'identifier' => $identifier,
                'password' => $password,
            ]
        ]);

        $data = json_decode($response->getBody(), true);
        $this->accessToken = $data['accessJwt'];
        $this->did = $data['did'];
    }

    public function getDid(): string
    {
        return $this->did;
    }

    public function putRecord(string $collection, string $rkey, array $record): array
    {
        $response = $this->client->post('com.atproto.repo.putRecord', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->accessToken,
            ],
            'json' => [
                'repo' => $this->did,
                'collection' => $collection,
                'rkey' => $rkey,
                'record' => $record,
            ],
        ]);

        return json_decode($response->getBody(), true);
    }

    public function getRecord(string $collection, string $rkey): ?array
    {
        try {
            $response = $this->client->get('com.atproto.repo.getRecord', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->accessToken,
                ],
                'query' => [
                    'repo' => $this->did,
                    'collection' => $collection,
                    'rkey' => $rkey,
                ],
            ]);

            return json_decode($response->getBody(), true);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            if ($e->getResponse()->getStatusCode() === 404) {
                return null;
            }
            throw $e;
        }
    }

    public function listRecords(string $collection, int $limit = 100, ?string $cursor = null): array
    {
        $query = [
            'repo' => $this->did,
            'collection' => $collection,
            'limit' => $limit,
        ];

        if ($cursor) {
            $query['cursor'] = $cursor;
        }

        $response = $this->client->get('com.atproto.repo.listRecords', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->accessToken,
            ],
            'query' => $query,
        ]);

        return json_decode($response->getBody(), true);
    }

    public function deleteRecord(string $collection, string $rkey): void
    {
        $this->client->post('com.atproto.repo.deleteRecord', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->accessToken,
            ],
            'json' => [
                'repo' => $this->did,
                'collection' => $collection,
                'rkey' => $rkey,
            ],
        ]);
    }
}
