<?php
namespace modules\bluesky\services;

use Craft;
use craft\elements\Entry;
use GuzzleHttp\Client;
use craft\records\Section as SectionRecord;
use craft\records\EntryType;

class BlueskyAuthorFactory
{
    public static function new()
    {
        $entryType = EntryType::find()
            ->where(['handle' => 'blueskyAuthors'])
            ->one();

        $author = new Entry();
        $author->typeId = $entryType->id;
        return $author;
    }
}

class BlueskyService
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
            return;
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
    
    public function fetchNewPosts(): array
    {
        // Get last synced cursor from project config or plugin settings
        $lastCursor = $this->getLastCursor();
        
        $params = [
            'actor' => $this->did,
            'limit' => 100,
        ];
        
        if ($lastCursor) {
            $params['cursor'] = $lastCursor;
        }
        
        $response = $this->client->get('app.bsky.feed.getAuthorFeed', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->accessToken,
            ],
            'query' => $params,
        ]);
        
        $data = json_decode($response->getBody(), true);
        
        // Save cursor for next sync
        if (isset($data['cursor'])) {
            $this->saveLastCursor($data['cursor']);
        }
        
        return $data['feed'] ?? [];
    }
    
    public function savePost(array $postData): void
    {
        $post = $postData['post'];
        $uri = $post['uri'];
        
        // Only want to sync my own posts, I don't care about reposts
        if ($post['author']['did'] != $this->did) {
            return;
        }
        
        // I don't care about replies... for now!
        if (!empty($postData['reply'])) {
            return;
        }

        $sectionRecord = SectionRecord::find()
            ->where(['handle' => 'blueskyPosts'])
            ->one();

        if (!$sectionRecord) {
            throw new \RuntimeException('Section "blueskyPosts" not found');
        }

        $entryType = EntryType::find()
            ->where(['handle' => 'blueskyPosts'])
            ->one();

        $entry = Entry::find()
            ->section('blueskyPosts')
            ->blueskyUri($uri)
            ->one();

        if (!$entry) {
            $entry = new Entry();
            $entry->sectionId = $sectionRecord->id;
            $entry->typeId = $entryType->id;
            $entry->title = substr($post['record']['text'] ?? '', 0, 50) ?: 'Untitled';
        }

        $fieldValues = [
            'blueskyUri' => $uri,
            'blueskyText' => $post['record']['text'] ?? '',
            'blueskyCreatedAt' => $post['record']['createdAt'] ?? null,
            'blueskyLikeCount' => $post['likeCount'] ?? 0,
            'blueskyRepostCount' => $post['repostCount'] ?? 0,
            'blueskyReplyCount' => $post['replyCount'] ?? 0,
            'blueskyEmbeds' => $this->getEmbeds($post),
            'blueskyAuthor' => $this->getAuthor($post['author']),
        ];

        // if (isset($postData['reply'])) {
        //     $fieldValues['blueskyReply'] = $this->getReply($postData['reply']);
        // }

        // Set custom fields
        $entry->setFieldValues($fieldValues);

        // Save entry
        if (!Craft::$app->getElements()->saveElement($entry)) {
            $errors = implode(', ', $entry->getErrorSummary(true));
            throw new \RuntimeException("Failed to save entry: $errors");
        }
    }

    private function getEmbeds(array $post)
    {
        $embeds = [];

        if (empty($post['embed'])) {
            return $embeds;
        }

        if (!isset($post['embed']['images']) || empty($post['embed']['images'])) {
            return $embeds;
        }

        foreach ($post['embed']['images'] as $image) {
            $embeds[] = [
                'url' => $image['fullsize'],
                'alt' => $image['alt'],
                'width' => $image['aspectRatio']['width'],
                'height' => $image['aspectRatio']['height'],
            ];
        }

        return $embeds;
    }

    private function getReply(array $reply) {
        return null;
    }

    private function getAuthor(array $authorData) {
        return [
            [
                'type' => 'blueskyAuthors',
                'fields' => [
                    'did' => $authorData['did'],
                    'blueskyHandle' => $authorData['handle'],
                    'displayName' => $authorData['displayName'],
                    'avatar' => $authorData['avatar'],
                ]
            ]
        ];
    }
    
    private function getLastCursor(): ?string
    {
        // Store in project config or a plugin settings table
        return Craft::$app->projectConfig->get('bluesky.lastCursor');
    }
    
    private function saveLastCursor(string $cursor): void
    {
        Craft::$app->projectConfig->set('bluesky.lastCursor', $cursor);
    }
}