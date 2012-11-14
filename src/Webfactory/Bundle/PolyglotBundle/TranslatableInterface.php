<?php

namespace Webfactory\Bundle\PolyglotBundle;

interface TranslatableInterface {

    public function translate($locale = null);
    public function setTranslation($value, $locale = null);

    public function __toString();
}

