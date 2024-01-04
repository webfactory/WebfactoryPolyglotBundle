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
 * @Target({"CLASS","PROPERTY"})
 *
 * @final
 */
class Locale extends Annotation
{
    /**
     * @var string
     */
    protected $primary;

    public function setPrimary(string $value)
    {
        $this->primary = $value;
    }

    /**
     * @return string
     */
    public function getPrimary()
    {
        return $this->primary;
    }
}
