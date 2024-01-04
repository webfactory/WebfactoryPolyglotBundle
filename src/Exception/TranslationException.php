<?php

namespace Webfactory\Bundle\PolyglotBundle\Exception;

class TranslationException extends \Exception
{
    public function __construct(string $message, \Throwable $previous)
    {
        parent::__construct($message, 0, $previous);
    }
}
