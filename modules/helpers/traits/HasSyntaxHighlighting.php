<?php

namespace helpers\traits;

use mmikkel\retcon\library\RetconDom;

trait HasSyntaxHighlighting
{
    public static function syntaxHighlight(string $body)
    {
        $dom = new RetconDom($body);
        $doc = $dom->getDoc();
        $nodes = $dom->filter("pre");
        $hl = new \Highlight\Highlighter();

        foreach ($nodes as $node) {
            $language = str_replace("lang-", "", $node->getAttribute('class'));
            $highlighted = $hl->highlight($language ?: "html", $node->nodeValue);
            $fragment = $doc->createDocumentFragment();
            $fragment->appendXML($highlighted->value);
            $node->nodeValue = "";
            foreach ($fragment->childNodes as $childNode) {
                $node->appendChild($doc->importNode($childNode->cloneNode(true), true));
            }
        }

        return $dom->getHtml();
    }
}
