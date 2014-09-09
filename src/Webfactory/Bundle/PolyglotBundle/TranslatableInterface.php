<?php

/*
 * (c) webfactory GmbH <info@webfactory.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webfactory\Bundle\PolyglotBundle;

interface TranslatableInterface
{
    public function translate($locale = null);

    public function setTranslation($value, $locale = null);

    public function __toString();
}
