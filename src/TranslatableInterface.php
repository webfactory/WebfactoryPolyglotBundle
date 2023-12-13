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
interface TranslatableInterface
{
    /**
     * Returns the translation for the given locale.
     *
     * @param ?string $locale The target locale or null for the current locale.
     *
     * @return mixed The translation or null if not available.
     */
    public function translate(string $locale = null): mixed;

    /**
     * Overwrites the translation for the given locale.
     *
     * @param ?string $locale The target locale or null for the current locale.
     */
    public function setTranslation(mixed $value, string $locale = null): void;

    /**
     * Returns wether the text is translated into the target locale.
     */
    public function isTranslatedInto(string $locale): bool;

    /**
     * Returns the translation for the current locale.
     *
     * @return string
     */
    public function __toString();
}
