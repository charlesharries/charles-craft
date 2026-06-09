<?php

namespace modules\standardsite;

use craft\elements\Entry;
use modules\standardsite\services\StandardSiteService;
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
