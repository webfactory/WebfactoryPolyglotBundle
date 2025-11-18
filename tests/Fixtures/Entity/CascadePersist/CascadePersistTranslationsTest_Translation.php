<?php

namespace Webfactory\Bundle\PolyglotBundle\Tests\Fixtures\Entity\CascadePersist;

use Doctrine\ORM\Mapping as ORM;
use Webfactory\Bundle\PolyglotBundle\Attribute as Polyglot;

#[ORM\Entity]
class CascadePersistTranslationsTest_Translation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[Polyglot\Locale]
    #[ORM\Column]
    private string $locale;

    #[ORM\ManyToOne(inversedBy: 'translations')]
    private CascadePersistTranslationsTest_Entity $entity;

    #[ORM\Column]
    private string $text;
}
