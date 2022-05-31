<?php

namespace Webfactory\Bundle\PolyglotBundle\Exception;

use Exception;

class TranslationException extends Exception
{
    public function __construct(string $message, Exception $previous)
    {
        parent::__construct($message, 0, $previous);
    }
}
