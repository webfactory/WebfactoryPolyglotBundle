<?php

namespace Webfactory\Bundle\PolyglotBundle\Tests\Fixtures\Entity\UndeclaredBaseClass;

use Doctrine\ORM\Mapping as ORM;
use Webfactory\Bundle\PolyglotBundle\Attribute as Polyglot;

#[Polyglot\Locale(primary: 'en_GB')]
#[ORM\Entity]
class UndeclaredBaseClassTest_EntityClass extends UndeclaredBaseClassTest_BaseClass
{
}
