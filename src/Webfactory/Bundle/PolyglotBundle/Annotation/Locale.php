<?php

namespace Webfactory\Bundle\PolyglotBundle\Annotation;

/**
 * @Annotation
 * @Target({"CLASS","PROPERTY"})
 */
class Locale extends \Doctrine\Common\Annotations\Annotation {

    protected $primary;
    public function setPrimary($value) { $this->primary = $value; }
    public function getPrimary() { return $this->primary; }

}
