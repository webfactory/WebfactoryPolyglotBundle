<?php

namespace Webfactory\Bundle\PolyglotBundle\Tests\Fixtures\Entity\UndeclaredBaseClass;

use Doctrine\ORM\Mapping as ORM;
use Webfactory\Bundle\PolyglotBundle\Attribute as Polyglot;

#[ORM\Entity]
class UndeclaredBaseClassTest_BaseClassTranslation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[Polyglot\Locale]
    #[ORM\Column]
    private string $locale;

    #[ORM\ManyToOne(inversedBy: 'translations')]
    private UndeclaredBaseClassTest_EntityClass $entity;

    #[ORM\Column]
    private string $text;
}
