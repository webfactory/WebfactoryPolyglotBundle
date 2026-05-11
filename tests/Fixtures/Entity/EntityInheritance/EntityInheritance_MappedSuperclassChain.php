<?php

namespace Webfactory\Bundle\PolyglotBundle\Tests\Fixtures\Entity\EntityInheritance;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Webfactory\Bundle\PolyglotBundle\Attribute as Polyglot;
use Webfactory\Bundle\PolyglotBundle\TranslatableInterface;

/**
 * An intermediate mapped superclass that extends the base mapped superclass and carries
 * the full Polyglot configuration: the #[TranslatedProperty] declaration for the inherited
 * property, and the translations collection. The concrete entity class further down the
 * chain needs no Polyglot configuration at all (beyond #[Locale] on the entity itself).
 */
#[ORM\MappedSuperclass]
#[Polyglot\Locale(primary: 'en_GB')]
#[Polyglot\TranslatedProperty('text')]
abstract class EntityInheritance_MappedSuperclassChain extends EntityInheritance_MappedSuperclass
{
    #[Polyglot\TranslationCollection]
    #[ORM\OneToMany(targetEntity: EntityInheritance_MappedSuperclassChainEntityTranslation::class, mappedBy: 'entity')]
    private Collection $translations;

    public function __construct()
    {
        $this->translations = new ArrayCollection();
    }

    public function setText(TranslatableInterface $text): void
    {
        $this->text = $text;
    }
}
