<?php

namespace extensions;

use craft\elements\Entry;
use helpers\utils\SyntaxHighlighter;
use Twig\TwigFilter;
use Twig\TwigFunction;

class TwigExtension extends \Twig\Extension\AbstractExtension
{
    public function getFunctions()
    {
        return [
            new TwigFunction('percentThroughDay', function () {
                $start = strtotime("today");
                $end = strtotime("tomorrow");
                $now = time();
                $secondstoday = $end - $start;
                $secondselapsed = $now - $start;
                return $secondselapsed / $secondstoday;
            }),
            new TwigFunction('sortByYear', [$this, 'sortByYear']),
        ];
    }

    public function getFilters()
    {
        return [
            new TwigFilter('syntaxHighlight', [$this, 'syntaxHighlight']),
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
}
