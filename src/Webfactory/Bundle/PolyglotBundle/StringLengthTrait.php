<?php

namespace Webfactory\Bundle\PolyglotBundle;

/**
 * Implements the count() method of TranslatableInterface.
 *
 * @see TranslatableInterface
 */
trait StringLengthTrait
{
    /**
     * @return integer
     */
    public function count()
    {
        return mb_strlen((string)$this, 'UTF-8');
    }

    /**
     * A __toString() method is required to make this work.
     *
     * @return string
     */
    abstract public function __toString();
}
