<?php

declare(strict_types=1);

namespace Webfactory\Bundle\PolyglotBundle\Exception;

use Exception;
use Throwable;

class ShouldNotHappen extends Exception
{
    public function __construct(string $message, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
