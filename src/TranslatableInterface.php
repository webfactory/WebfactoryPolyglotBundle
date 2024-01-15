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
 *
 * @template Tunderlying_type
 */
interface TranslatableInterface
{
    /**
     * Returns the translation for the given locale.
     *
     * @param string|null $locale The target locale or null for the current locale.
     *
     * @return Tunderlying_type The translation or null if not available.
     */
    public function translate(string $locale = null): mixed;

    /**
     * Overwrites the translation for the given locale.
     *
     * @param Tunderlying_type $value The value to set for the particular locale
     * @param string|null $locale The target locale or null for the current locale.
     */
    public function setTranslation(mixed $value, string $locale = null): void;

    /**
     * Returns wether the text is translated into the target locale.
     *
     * @param string $locale The target locale.
     */
    public function isTranslatedInto(string $locale): bool;

    /**
     * Casts the underlying value for the current default locale into a string
     */
    public function __toString(): string;
}
