<?php

namespace Webfactory\Bundle\PolyglotBundle\Doctrine;

interface  TranslationProxy {

    public function translate($locale = null);
    public function setTranslation($value, $locale = null);

    public function __toString();
}

