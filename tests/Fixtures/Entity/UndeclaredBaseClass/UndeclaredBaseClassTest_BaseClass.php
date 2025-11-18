<?php

namespace Webfactory\Bundle\PolyglotBundle\Tests\Fixtures\Entity\UndeclaredBaseClass;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Webfactory\Bundle\PolyglotBundle\Attribute as Polyglot;
use Webfactory\Bundle\PolyglotBundle\TranslatableInterface;

/**
 * Fields in this class cannot be "private" - otherwise, they would not be picked up by the
 * Doctrine mapping drivers when processing the entity subclass (UndeclaredBaseClassTest_EntityClass).
 */
class UndeclaredBaseClassTest_BaseClass
{
    #[ORM\Column(type: 'integer')]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    protected ?int $id = null;

    #[Polyglot\TranslationCollection]
    #[ORM\OneToMany(targetEntity: UndeclaredBaseClassTest_BaseClassTranslation::class, mappedBy: 'entity')]
    protected Collection $translations;

    #[Polyglot\Translatable]
    #[ORM\Column(type: 'string')]
    protected string|TranslatableInterface|null $text = null;

    public function __construct()
    {
        $this->translations = new ArrayCollection();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setText(TranslatableInterface $text): void
    {
        $this->text = $text;
    }

    public function getText(): ?TranslatableInterface
    {
        return $this->text;
    }
}
