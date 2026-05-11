<?php

namespace Webfactory\Bundle\PolyglotBundle\Tests\Fixtures\Entity\EntityInheritance;

use Doctrine\ORM\Mapping as ORM;
use Webfactory\Bundle\PolyglotBundle\Attribute as Polyglot;

/**
 * A second concrete entity extending the chain, overriding the locale from the
 * intermediate mapped superclass with a different primary locale.
 * Used to verify that a subclass's #[Locale] takes priority over a parent class's.
 */
#[Polyglot\Locale(primary: 'de_DE')]
#[ORM\Entity]
class EntityInheritance_MappedSuperclassChainEntityLocaleOverride extends EntityInheritance_MappedSuperclassChain
{
}
