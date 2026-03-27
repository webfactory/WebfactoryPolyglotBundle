<?php

namespace Webfactory\Bundle\PolyglotBundle\Tests\Fixtures\Entity\EntityInheritance;

use Doctrine\ORM\Mapping as ORM;
use Webfactory\Bundle\PolyglotBundle\Attribute as Polyglot;

#[Polyglot\Locale(primary: 'en_GB')]
#[ORM\Entity]
class EntityInheritance_MappedSuperclassWithTranslations_Entity extends EntityInheritance_MappedSuperclassWithTranslations
{
}
