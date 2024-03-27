<?php

/*
 * (c) webfactory GmbH <info@webfactory.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webfactory\Bundle\PolyglotBundle\Annotation;

use Doctrine\Common\Annotations\Annotation\NamedArgumentConstructor;
use Webfactory\Bundle\PolyglotBundle\Attribute\Translatable as Attribute;

/**
 * @Annotation
 * @NamedArgumentConstructor
 */
final class Translatable extends Attribute
{
    public function __construct(string $translationFieldname = null)
    {
        trigger_deprecation('webfactory/polyglot-bundle', '3.1.0', 'The %s annotation has been deprecated. Use the %s attribute instead.', self::class, parent::class);
        parent::__construct($translationFieldname);
    }
}
