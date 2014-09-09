<?php

namespace Webfactory\Bundle\PolyglotBundle\Annotation;

use Doctrine\Common\Annotations\Annotation;

/**
 * @Annotation
 * @Target({"CLASS","PROPERTY"})
 */
class Locale extends Annotation
{
    protected $primary;

    public function setPrimary($value)
    {
        $this->primary = $value;
    }

    public function getPrimary()
    {
        return $this->primary;
    }
}
