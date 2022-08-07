<?php

namespace extensions;

use craft\elements\Entry;
use DateTime;
use helpers\utils\SyntaxHighlighter;
use Twig\TwigFilter;
use Twig\TwigFunction;

class TwigExtension extends \Twig\Extension\AbstractExtension
{
    public function getFunctions()
    {
        return [
            new TwigFunction('percentThroughDay', [$this, 'percentThroughDay']),
            new TwigFunction('sortByYear', [$this, 'sortByYear']),
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
