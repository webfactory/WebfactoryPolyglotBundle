<?php

/*
 * (c) webfactory GmbH <info@webfactory.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webfactory\Bundle\PolyglotBundle\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final class TranslatedProperty
{
    public function __construct(
        private readonly string $propertyName,
        private readonly ?string $translationFieldname = null,
    ) {
    }

    public function getPropertyName(): string
    {
        return $this->propertyName;
    }

    public function getTranslationFieldname(): ?string
    {
        return $this->translationFieldname;
    }
}
