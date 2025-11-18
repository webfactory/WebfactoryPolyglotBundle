<?php

namespace Webfactory\Bundle\PolyglotBundle\Tests\Fixtures\Entity\TranslationPropertyNamedDifferently;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Webfactory\Bundle\PolyglotBundle\Attribute as Polyglot;
use Webfactory\Bundle\PolyglotBundle\TranslatableInterface;

#[Polyglot\Locale(primary: 'en_GB')]
#[ORM\Entity]
class TranslationPropertyNamedDifferently_Entity
{
    #[ORM\Column(type: 'integer')]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[Polyglot\TranslationCollection]
    #[ORM\OneToMany(targetEntity: TranslationPropertyNamedDifferently_Translation::class, mappedBy: 'entity')]
    protected Collection $translations;

    #[Polyglot\Translatable(translationFieldname: 'textOtherName')]
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
