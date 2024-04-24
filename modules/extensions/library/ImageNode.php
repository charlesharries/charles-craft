<?php

namespace extensions\library;

use Craft;
use craft\elements\Asset;
use DOMElement;

class ImageNode
{
    public DOMElement $element;

    private array $sizes = ['sm', 'md', 'lg'];

    /**
     * Pass the element by reference so that changes to $this->element
     * are reflected in the DOM that this element comes from.
     */
    public function __construct(DOMElement &$element)
    {
        $this->element = $element;
    }

    /**
     * Apply the relevant attributes to our element, based on the
     * sizes of the associated Asset.
     */
    public function processNode(Asset $asset)
    {
        $set = [];
        foreach ($this->sizes as $size) {
            $url = $asset->getUrl($size);
            $transform = Craft::$app->getImageTransforms()->getTransformByHandle($size);
            $width = $transform->width;
            $set[] = "{$url} {$width}w";
        }

        $srcset = \implode(", ", $set);
        $this->element->setAttribute('srcset', $srcset);
        $this->element->setAttribute('width', $asset->width);
        $this->element->setAttribute('height', $asset->height);

        return $this->element;
    }

    /**
     * Get the Asset associated with the src of our DOM element.
     */
    public function getAsset(): Asset|null
    {
        return Asset::find()
            ->volume($this->volume())
            ->filename($this->filename())
            ->one();
    }

    /**
     * We need the filename because that's how we query Craft for the
     * Asset itself.
     */
    private function filename()
    {
        $filepath = \basename($this->element->getAttribute('src'));
        return explode("#", $filepath)[0];
    }

    /**
     * We need the volume because it's possible that both volumes
     * could have a file with the same name. It's actually possible
     * that the same filename could exists in different directories on
     * each filesystem, in which case we might run into some trouble,
     * but I'm going to strategically ignore that.
     */
    private function volume()
    {
        return $this->isS3() ? 's3' : 'local';
    }

    /**
     * Check whether our Asset is on S3. This requires S3 to be in the
     * URL, which is an arbitrary constraint I've put on my routes--
     * but it helps out here!
     */
    private function isS3()
    {
        return \str_contains($this->element->getAttribute('src'), 's3');
    }
}
