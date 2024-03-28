<?php

/*
 * (c) webfactory GmbH <info@webfactory.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webfactory\Bundle\PolyglotBundle\Annotation;

use Doctrine\Common\Annotations\Annotation\NamedArgumentConstructor;
use Webfactory\Bundle\PolyglotBundle\Attribute\Locale as Attribute;

/**
 * @Annotation
 * @NamedArgumentConstructor
 * @Target({"CLASS","PROPERTY"})
 */
final class Locale extends Attribute
{
    public function __construct(string $primary = null)
    {
        trigger_deprecation('webfactory/polyglot-bundle', '3.1.0', 'The %s annotation has been deprecated and will be removed in the 4.0 release. Use the %s attribute instead.', self::class, parent::class);
        parent::__construct($primary);
    }
}
