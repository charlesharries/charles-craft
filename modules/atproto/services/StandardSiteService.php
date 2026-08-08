<?php

namespace modules\atproto\services;

use Craft;
use craft\elements\Entry;
use modules\atproto\builders\StandardSiteRecordBuilder;

class StandardSiteService
{
    private AtProtoClient $client;

    private const PUBLICATION_COLLECTION = 'site.standard.publication';
    private ?string $publicationRkey = null;

    public function __construct()
    {
        $this->client = new AtProtoClient();
    }

    public function authenticate(): void
    {
        $this->client->authenticate();
    }

    public function createOrUpdatePublication(): string
    {
        $existingUri = $this->getPublicationUri();
        $rkey = $existingUri ? basename($existingUri) : Tid::generate();

        $record = [
            '$type' => self::PUBLICATION_COLLECTION,
            'url' => 'https://charlesharri.es',
            'name' => 'Charles Harries',
            'description' => "I'm a software developer working on the web in the North East of England.",
            'createdAt' => date('c'),
        ];

        $result = $this->client->putRecord(
            self::PUBLICATION_COLLECTION,
            $rkey,
            $record
        );

        $uri = $result['uri'];
        Craft::$app->projectConfig->set('atproto.publicationUri', $uri);

        return $uri;
    }

    public function getPublicationUri(): ?string
    {
        return Craft::$app->projectConfig->get('atproto.publicationUri');
    }

    public static function documentUriForEntry(Entry $entry): ?string
    {
        $publicationUri = Craft::$app->projectConfig->get('atproto.publicationUri');
        if (!$publicationUri) {
            return null;
        }

        $parts = explode('/', $publicationUri);
        $did = $parts[2] ?? null;
        if (!$did) {
            return null;
        }

        $rkey = Tid::forEntry($entry);
        return 'at://' . $did . '/' . StandardSiteRecordBuilder::collection() . '/' . $rkey;
    }
}
