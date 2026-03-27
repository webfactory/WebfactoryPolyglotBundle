<?php

namespace Webfactory\Bundle\PolyglotBundle\Tests\Fixtures\Entity\EntityInheritance;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Webfactory\Bundle\PolyglotBundle\Attribute as Polyglot;
use Webfactory\Bundle\PolyglotBundle\TranslatableInterface;

/**
 * A mapped superclass that carries a translatable property, including translations.
 * Since translations are a one-to-many relationship, the ResolveTargetEntityListener
 * must be used to make the translation class ::$entity field reference back to this
 * class here, see https://www.doctrine-project.org/projects/doctrine-orm/en/3.6/reference/inheritance-mapping.html#:~:text=ResolveTargetEntityListener.
 */
#[ORM\MappedSuperclass]
abstract class EntityInheritance_MappedSuperclassWithTranslations implements EntityInheritance_MappedSuperclassWithTranslationsInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[Polyglot\TranslationCollection]
    #[ORM\OneToMany(targetEntity: EntityInheritance_MappedSuperclassTranslation::class, mappedBy: 'entity')]
    private Collection $translations;

    #[ORM\Column(type: 'string', nullable: true)]
    #[Polyglot\Translatable]
    protected TranslatableInterface|string|null $text = null;

    public function __construct()
    {
        $this->translations = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setText(TranslatableInterface $text): void
    {
        $this->text = $text;
    }

    public function getText(): TranslatableInterface|string|null
    {
        return $this->text;
    }
}
