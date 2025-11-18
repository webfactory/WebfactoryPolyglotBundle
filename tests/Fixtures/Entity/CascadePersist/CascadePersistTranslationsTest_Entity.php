<?php

namespace Webfactory\Bundle\PolyglotBundle\Tests\Fixtures\Entity\CascadePersist;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Webfactory\Bundle\PolyglotBundle\Attribute as Polyglot;
use Webfactory\Bundle\PolyglotBundle\Translatable;
use Webfactory\Bundle\PolyglotBundle\TranslatableInterface;

#[Polyglot\Locale(primary: 'en_GB')]
#[ORM\Entity]
class CascadePersistTranslationsTest_Entity
{
    #[ORM\Column]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    /**
     * (!) There is *not* cascade="persist" configuration here.
     */
    #[Polyglot\TranslationCollection]
    #[ORM\OneToMany(targetEntity: CascadePersistTranslationsTest_Translation::class, mappedBy: 'entity')]
    protected Collection $translations;

    #[Polyglot\Translatable]
    #[ORM\Column(type: 'string')]
    protected string|TranslatableInterface $text;

    public function __construct()
    {
        $this->text = new Translatable('test en_GB');
        $this->translations = new ArrayCollection();
    }

    public function addTranslation(string $locale, mixed $text): void
    {
        $this->text->setTranslation($text, $locale);
    }
}
