<?php

namespace Webfactory\Bundle\PolyglotBundle\Tests\Fixtures\Entity\TranslatableWithObjectData;

class TranslatableWithObjectDataTest_Object
{
    public string $text;

    public function __construct(string $text)
    {
        $this->text = $text;
    }
}
