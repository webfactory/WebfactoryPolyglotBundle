<?php

/*
 * (c) webfactory GmbH <info@webfactory.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webfactory\Bundle\PolyglotBundle;

/**
 * Represents a text with multiple translations.
 */
interface TranslatableInterface extends \Countable
{
    /**
     * Returns the translation for the given locale.
     *
     * @param string|null $locale The target locale or null for the current locale.
     * @return string|null The translation or null if not available.
     */
    public function translate($locale = null);

    /**
     * Overwrites the translation for the given locale.
     *
     * @param string $value
     * @param string|null $locale The target locale or null for the current locale.
     */
    public function setTranslation($value, $locale = null);

    /**
     * Returns the translation for the current locale.
     *
     * @return string
     */
    public function __toString();

    /**
     * Returns the length of the translation in the current locale.
     *
     * @return integer
     */
    public function count();
}
