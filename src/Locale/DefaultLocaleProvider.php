<?php

/*
 * (c) webfactory GmbH <info@webfactory.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webfactory\Bundle\PolyglotBundle\Locale;

final class DefaultLocaleProvider
{
    public function __construct(
        private string $defaultLocale = 'en_GB',
    ) {
    }

    public function setDefaultLocale(string $locale): void
    {
        $this->defaultLocale = $locale;
    }

    public function getDefaultLocale(): string
    {
        return $this->defaultLocale;
    }

    public function __toString(): string
    {
        return $this->defaultLocale;
    }
}
