<?php

namespace Webfactory\Bundle\PolyglotBundle\Locale;

class DefaultLocaleProvider {

    protected $defaultLocale = 'en_GB';

    public function setDefaultLocale($locale) {
        $this->defaultLocale = $locale;
    }

    public function getDefaultLocale() {
        return $this->defaultLocale;
    }

}
