<?php

namespace Webfactory\Bundle\PolyglotBundle\Exception;

class TranslationException extends \Exception
{
    /**
     * TranslationException constructor.
     *
     * @param string $message
     */
    public function __construct($message, \Exception $previous)
    {
        parent::__construct($message, 0, $previous);
    }
}
