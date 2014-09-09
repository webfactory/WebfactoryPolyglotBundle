<?php

namespace Webfactory\Bundle\PolyglotBundle\Annotation;

use Doctrine\Common\Annotations\Annotation;

/**
 * @Annotation
 */
class Translatable extends Annotation
{
    protected $translationFieldname;

    public function setTranslationFieldname($value)
    {
        $this->translationFieldname = $value;
    }

    public function getTranslationFieldname()
    {
        return $this->translationFieldname;
    }
}
