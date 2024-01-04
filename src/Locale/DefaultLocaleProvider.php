<?php

/*
 * (c) webfactory GmbH <info@webfactory.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webfactory\Bundle\PolyglotBundle\Locale;

/**
 * @final
 */
class DefaultLocaleProvider
{
    private $defaultLocale;

    public function __construct($locale = 'en_GB')
    {
        $this->defaultLocale = $locale;
    }

    public function setDefaultLocale($locale)
    {
        $this->defaultLocale = $locale;
    }

    public function getDefaultLocale()
    {
        return $this->defaultLocale;
    }

    public function __toString()
    {
        return $this->defaultLocale;
    }
}
