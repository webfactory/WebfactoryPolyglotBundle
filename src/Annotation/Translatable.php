<?php

/*
 * (c) webfactory GmbH <info@webfactory.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webfactory\Bundle\PolyglotBundle\Annotation;

use Attribute;
use Doctrine\Common\Annotations\Annotation\NamedArgumentConstructor;

/**
 * @Annotation
 * @NamedArgumentConstructor
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class Translatable
{
    private ?string $translationFieldname;

    public function __construct(string $translationFieldname = null)
    {
        $this->translationFieldname = $translationFieldname;
    }

    public function getTranslationFieldname(): ?string
    {
        return $this->translationFieldname;
    }
}
