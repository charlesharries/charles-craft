<?php

namespace extensions;

use Craft;
use craft\elements\Asset;
use craft\elements\Entry;
use extensions\services\AltTextGenerator;
use craft\helpers\App;
use DateTime;
use extensions\jobs\NotifyUmami;
use extensions\library\ImageNode;
use helpers\utils\SyntaxHighlighter;
use mmikkel\retcon\library\RetconDom;
use mmikkel\retcon\Retcon;
use Twig\TwigFilter;
use Twig\TwigFunction;

class TwigExtension extends \Twig\Extension\AbstractExtension
{
    public function getFunctions()
    {
        return [
            new TwigFunction('percentThroughDay', [$this, 'percentThroughDay']),
            new TwigFunction('sortByYear', [$this, 'sortByYear']),
            new TwigFunction('trackPageview', [$this, 'trackPageview']),
        ];
    }

    public function percentThroughDay($time = null)
    {
        $start = strtotime("today");
        $end = strtotime("tomorrow");
        $now = time();
        if ($time) {
            $now = (new DateTime($time))->format('U');
        }
        $secondstoday = $end - $start;
        $secondselapsed = $now - $start;
        return $secondselapsed / $secondstoday;
    }

    public function trackPageview()
    {
        if (App::env('ENVIRONMENT') === 'dev') {
            return;
        }

        if (Craft::$app->user->identity) {
            return;
        }

        \craft\helpers\Queue::push(new NotifyUmami([
            "url" => Craft::$app->request->url,
            "ip" => Craft::$app->request->userIP,
            "userAgent" => Craft::$app->request->userAgent,
            "referrer" => Craft::$app->request->referrer,
            "host" => Craft::$app->request->hostName,
            "screen" => $this->getViewport(),
        ]));
    }

    protected function getViewport()
    {
        $mobileDetect = new \Detection\MobileDetect();

        if ($mobileDetect->isTablet()) {
            return "768x1024";
        }

        if ($mobileDetect->isMobile()) {
            return "375x812";
        }

        return "1920x1280";
    }

    public function getFilters()
    {
        return [
            new TwigFilter('syntaxHighlight', [$this, 'syntaxHighlight']),
            new TwigFilter('srcset', [$this, 'srcset']),
            new TwigFilter('groupFigures', [$this, 'groupFigures']),
            new TwigFilter('ratings', [$this, 'ratings']),
        ];
    }

    /**
     * @param Entry[] $entries
     */
    public function sortByYear($entries)
    {
        $hash = [];

        foreach ($entries as $entry) {
            $year = $entry->postDate->format('Y');
            $month = $entry->postDate->format('F');
            $hash[$year] = $hash[$year] ?? [];
            $hash[$year][$month] = $hash[$year][$month] ?? [];
            $hash[$year][$month][] = $entry;
        }

        return $hash;
    }

    public function syntaxHighlight($string)
    {
        if (empty($string)) {
            return $string;
        }

        return SyntaxHighlighter::highlight($string);
    }

    public function ratings($string)
    {
        if (empty($string)) {
            return $string;
        }

        $renderStars = function ($matches) use ($string) {
            if (! $matches) {
                return $string;
            }

            return Craft::$app->getView()->renderTemplate('_partials/stars', ['count' => $matches[1]]);
        };

        return preg_replace_callback('/(\d)\/5 stars\.?/i', $renderStars, $string);
    }

    private $altTextGenerator;

    public function __construct()
    {
        $this->altTextGenerator = new AltTextGenerator();
    }

    public function srcset($string)
    {
        if (empty($string)) {
            return $string;
        }

        $dom = new RetconDom($string);
        $elements = $dom->filter('img:not([src$=".gif"])');

        foreach ($elements as $element) {
            $element->setAttribute("loading", "lazy");

            $image = new ImageNode($element);
            $asset = $image->getAsset();

            if (!$asset) {
                continue;
            }

            $image->processNode($asset);
            
            // Generate and set alt text if needed
            // if (!$element->getAttribute('alt')) {
            //     $altText = $this->altTextGenerator->generateAltText($asset);
            //     if ($altText) {
            //         $element->setAttribute('alt', $altText);
            //     }
            // }
        }

        return $dom->getHtml();
    }

    public function groupFigures($string)
    {
        if (empty($string)) {
            return $string;
        }

        $dom = new RetconDom($string);
        /** @var \DOMElement[] */
        $elements = $dom->filter("retcon > * ");
        $parent = $dom->filter("retcon")[0];
        $wrapperNode = null;
        foreach ($elements as $node) {
            if ($node->nodeName !== "figure") {
                $wrapperNode = null;
                continue;
            }

            $ratio = null;

            /** @var \DOMElement */
            $img = $node->firstElementChild;
            if ($img && $img->attributes->getNamedItem("width")) {
                $ratio = (
                    $img->attributes->getNamedItem("width")->nodeValue /
                    $img->attributes->getNamedItem("height")->nodeValue
                );
                $node->setAttribute("style", "flex: {$ratio};");
            }

            if (!$wrapperNode) {
                $wrapperNode = $dom->getDoc()->createElement("div");
                $wrapperNode->setAttribute("class", "gallery");
                $parent->insertBefore($wrapperNode, $node);
            }

            $wrapperNode->appendChild($node);
        }

        return $dom->getHtml();
    }
}
