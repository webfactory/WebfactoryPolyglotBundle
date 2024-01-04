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
 * @final
 */
class Translatable extends Annotation
{
    protected $translationFieldname;

    public function setTranslationFieldname($value)
    {
        $this->translationFieldname = $value;
    }

    public function getTranslationFieldname()
    {
        return $this->translationFieldname;
    }
}
