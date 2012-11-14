<?php

namespace Webfactory\Bundle\PolyglotBundle\Annotation;

/** @Annotation */
class Translatable extends \Doctrine\Common\Annotations\Annotation {
    protected $translationFieldname;
    public function setTranslationFieldname($value) { $this->translationFieldname = $value; }
    public function getTranslationFieldname() { return $this->translationFieldname; }
}
