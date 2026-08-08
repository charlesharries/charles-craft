<?php

namespace modules\atproto;

use craft\elements\Entry;
use modules\atproto\services\StandardSiteService;
use Twig\TwigFunction;

class TwigExtension extends \Twig\Extension\AbstractExtension
{
    public function getFunctions()
    {
        return [
            new TwigFunction('standardSiteDocumentUri', [StandardSiteService::class, 'documentUriForEntry']),
        ];
    }
}
