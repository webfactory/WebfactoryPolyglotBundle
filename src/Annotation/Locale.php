<?php

/*
 * (c) webfactory GmbH <info@webfactory.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webfactory\Bundle\PolyglotBundle\Annotation;

use Doctrine\Common\Annotations\Annotation;

/**
 * @Annotation
 *
 * @Target({"CLASS","PROPERTY"})
 */
class Locale extends Annotation
{
    protected $primary;

    public function setPrimary($value)
    {
        $this->primary = $value;
    }

    public function getPrimary()
    {
        return $this->primary;
    }
}
