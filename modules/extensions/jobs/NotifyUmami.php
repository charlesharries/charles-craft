<?php

namespace extensions\jobs;

use Craft;
use craft\helpers\App;

class NotifyUmami extends \craft\queue\BaseJob
{
    public $url;
    public $ip;
    public $userAgent;
    public $referrer;

    protected ?\GuzzleHttp\Client $client = null;

    protected string $accessToken;

    private $authEndpoint = "/api/auth/login";
    private $endpoint = "/api/collect";

    protected function defaultDescription(): string
    {
        return Craft::t("app", "umami.pageview");
    }

    private function getClient()
    {
        if (!$this->client) {
            $this->client = Craft::createGuzzleClient([
                "base_uri" => "https://stats.charlesharri.es",
            ]);
        }

        return $this->client;
    }

    public function execute($queue): void
    {
        $this->accessToken = $this->getAccessToken();

        $this->getClient()->request("POST", $this->endpoint, [
            "headers" => [
                "Authorization" => "Bearer $this->accessToken",
                "Content-Type" => "application/json",
                "User-Agent" => $this->userAgent,
                "x-client-ip" => $this->ip,
            ],
            "http_errors" => false,
            "json" => [
                "payload" => [
                    "url" => $this->url,
                    "website" => App::env("UMAMI_SITE_ID"),
                    "name" => "pageview",
                    "referrer" => $this->referrer,
                ],
                "type" => "pageview"
            ],
        ]);
    }

    private function getAccessToken()
    {
        $res = $this->getClient()->get($this->authEndpoint, [
            "form_params" => [
                "username" => App::env("UMAMI_USERNAME"),
                "password" => App::env("UMAMI_PASSWORD"),
            ],
        ]);

        $body = json_decode($res->getBody());
        return $body->token;
    }
}
