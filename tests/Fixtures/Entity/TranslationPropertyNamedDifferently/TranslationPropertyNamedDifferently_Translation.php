<?php

namespace Webfactory\Bundle\PolyglotBundle\Tests\Fixtures\Entity\TranslationPropertyNamedDifferently;

use Doctrine\ORM\Mapping as ORM;
use Webfactory\Bundle\PolyglotBundle\Attribute as Polyglot;

#[ORM\Entity]
class TranslationPropertyNamedDifferently_Translation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[Polyglot\Locale]
    #[ORM\Column]
    private string $locale;

    #[ORM\ManyToOne(inversedBy: 'translations')]
    private TranslationPropertyNamedDifferently_Entity $entity;

    #[ORM\Column]
    private string $textOtherName;
}
