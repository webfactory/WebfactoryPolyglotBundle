<?php

namespace Webfactory\Bundle\PolyglotBundle\Exception;


class TranslationException extends \Exception
{
    /**
     * TranslationException constructor.
     * @param string $message
     * @param \Exception $previous
     */
    public function __construct($message, \Exception $previous)
    {
        parent::__construct($message, 0, $previous);
    }
}