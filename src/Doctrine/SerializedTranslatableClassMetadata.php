<?php

/*
 * (c) webfactory GmbH <info@webfactory.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webfactory\Bundle\PolyglotBundle\Doctrine;

final class SerializedTranslatableClassMetadata
{
    /**
     * @var class-string<object>
     */
    public string $class;

    /**
     * @var class-string<object>
     */
    public string $translationClass;

    /**
     * @var array<string, array{0: class-string<object>, 1: string}>
     */
    public array $translationFieldMapping;

    /**
     * @var array<string, array{0: class-string<object>, 1: string}>
     */
    public array $translatedProperties;

    /**
     * @var array{0: class-string<object>, 1: string}
     */
    public array $translationLocaleProperty;

    /**
     * @var array{0: class-string<object>, 1: string}
     */
    public array $translationsCollectionProperty;

    /**
     * @var array{0: class-string<object>, 1: string}
     */
    public array $translationMappingProperty;

    public string $primaryLocale;
}
