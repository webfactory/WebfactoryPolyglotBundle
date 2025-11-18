<?php

namespace Webfactory\Bundle\PolyglotBundle\Tests\Fixtures\Entity\UnmappedProperties;

use Doctrine\ORM\Mapping as ORM;
use Webfactory\Bundle\PolyglotBundle\Attribute as Polyglot;

#[ORM\Entity]
#[ORM\HasLifecycleCallbacks]
class TranslatingUnmappedPropertiesTest_Translation
{
    #[ORM\Column(type: 'integer')]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\Column(type: 'string')]
    #[Polyglot\Locale]
    private string $locale;

    #[ORM\ManyToOne(inversedBy: 'translations')]
    private TranslatingUnmappedPropertiesTest_Entity $entity;

    #[ORM\Column(type: 'string')]
    private $mappedText;

    // (!) This field is unmapped from the ORM point of view
    private $text;

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
