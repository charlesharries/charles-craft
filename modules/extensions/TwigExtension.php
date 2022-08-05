<?php

namespace extensions;

class TwigExtension extends \Twig\Extension\AbstractExtension
{
    public function getFunctions()
    {
        return [
            new \Twig\TwigFunction('percentThroughDay', function () {
                $start = strtotime("today");
                $end = strtotime("tomorrow");
                $now = time();
                $secondstoday = $end - $start;
                $secondselapsed = $now - $start;
                return $secondselapsed / $secondstoday;
            }),
        ];
    }
}
