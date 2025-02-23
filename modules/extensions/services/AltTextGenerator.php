<?php

namespace extensions\services;

use Craft;
use craft\elements\Asset;
use craft\helpers\App;
use OpenAI;

class AltTextGenerator
{
    private $client;
    
    public function __construct()
    {
        $this->client = OpenAI::client(App::parseEnv('$OPENAI_API_KEY'));
    }

    public function generateAltText(Asset $asset): ?string
    {
        // Skip if alt text already exists
        if ($asset->alt) {
            return $asset->alt;
        }

        try {
            $response = $this->client->chat()->create([
                'model' => 'gpt-4o-mini',
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => 'Generate a concise, descriptive alt text for this image. Focus on the key visual elements and context. Keep it under 125 characters.',
                            ],
                            [
                                'type' => 'image_url',
                                'image_url' => $asset->getUrl(),
                            ],
                        ],
                    ],
                ],
                'max_tokens' => 100,
            ]);

            $altText = $response->choices[0]->message->content;

            // Store the generated alt text
            $asset->alt = $altText;
            Craft::$app->elements->saveElement($asset);

            return $altText;
        } catch (\Exception $e) {
            Craft::error('Failed to generate alt text: ' . $e->getMessage(), __METHOD__);
            return null;
        }
    }
}
