<?php

namespace modules\webmentions\jobs;

use Craft;
use modules\webmentions\services\EndpointDiscovery;

class SendWebmention extends \craft\queue\BaseJob
{
    public string $source;
    public string $target;

    protected function defaultDescription(): string
    {
        return Craft::t('app', 'webmentions.send');
    }

    public function execute($queue): void
    {
        $endpoint = (new EndpointDiscovery())->discover($this->target);
        if (!$endpoint) {
            return;
        }

        try {
            Craft::createGuzzleClient()->request('POST', $endpoint, [
                'http_errors' => false,
                'form_params' => [
                    'source' => $this->source,
                    'target' => $this->target,
                ],
            ]);
        } catch (\Throwable $e) {
            Craft::warning(
                "Failed to send webmention from {$this->source} to {$this->target}: {$e->getMessage()}",
                'webmentions'
            );
        }
    }
}
