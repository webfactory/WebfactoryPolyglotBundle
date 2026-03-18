<?php

namespace Webfactory\Bundle\PolyglotBundle\Tests\Fixtures\Entity\EntityInheritance;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Webfactory\Bundle\PolyglotBundle\Attribute as Polyglot;

/**
 * An entity extending a mapped superclass, adding Polyglot support. The full Polyglot
 * configuration — including marking the inherited property for translation via
 * the class-level #[TranslatedProperty] attribute — lives here.
 */
#[Polyglot\Locale(primary: 'en_GB')]
#[Polyglot\TranslatedProperty('text')]
#[ORM\Entity]
class EntityInheritance_MappedSuperclassEntity extends EntityInheritance_MappedSuperclass
{
    #[Polyglot\TranslationCollection]
    #[ORM\OneToMany(targetEntity: EntityInheritance_MappedSuperclassEntityTranslation::class, mappedBy: 'entity')]
    private Collection $translations;

    public function __construct()
    {
        $this->translations = new ArrayCollection();
    }
}
