<?php

namespace Webfactory\Bundle\PolyglotBundle\Tests\Fixtures\Entity\EntityInheritance;

use Doctrine\ORM\Mapping as ORM;

/**
 * A concrete entity two levels below the property declaration:
 *   EntityInheritance_MappedSuperclass (property defined here, no Polyglot config)
 *     └─ EntityInheritance_MappedSuperclassChain (#[Locale], #[TranslatedProperty], translations collection here)
 *          └─ EntityInheritance_MappedSuperclassChainEntity (bare entity, no Polyglot config needed)
 */
#[ORM\Entity]
class EntityInheritance_MappedSuperclassChainEntity extends EntityInheritance_MappedSuperclassChain
{
}
