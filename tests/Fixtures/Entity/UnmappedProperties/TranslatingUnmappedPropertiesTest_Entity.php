<?php

namespace Webfactory\Bundle\PolyglotBundle\Tests\Fixtures\Entity\UnmappedProperties;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Webfactory\Bundle\PolyglotBundle\Attribute as Polyglot;
use Webfactory\Bundle\PolyglotBundle\Translatable;

#[ORM\Entity]
#[ORM\HasLifecycleCallbacks]
#[Polyglot\Locale(primary: 'en_GB')]
class TranslatingUnmappedPropertiesTest_Entity
{
    #[ORM\Column(type: 'integer')]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    public ?int $id = null;

    #[ORM\OneToMany(targetEntity: TranslatingUnmappedPropertiesTest_Translation::class, mappedBy: 'entity')]
    #[Polyglot\TranslationCollection]
    protected Collection $translations;

    #[ORM\Column(type: 'string')]
    public $mappedText;

    // (!) This field is unmapped from the ORM point of view
    #[Polyglot\Translatable]
    public $text;

    public function __construct()
    {
        $this->translations = new ArrayCollection();
        $this->text = new Translatable();
    }

    #[ORM\PreFlush]
    public function copyToMappedField(): void
    {
        $this->mappedText = $this->text;
    }

    #[ORM\PostLoad]
    public function copyFromMappedField(): void
    {
        $this->text = $this->mappedText;
    }
}
