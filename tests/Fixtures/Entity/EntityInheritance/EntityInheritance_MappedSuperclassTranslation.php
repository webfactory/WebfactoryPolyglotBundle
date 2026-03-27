<?php

namespace Webfactory\Bundle\PolyglotBundle\Tests\Fixtures\Entity\EntityInheritance;

use Doctrine\ORM\Mapping as ORM;
use Webfactory\Bundle\PolyglotBundle\Attribute as Polyglot;

#[ORM\Entity]
#[Polyglot\Locale(primary: 'en_GB')]
class EntityInheritance_MappedSuperclassTranslation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[Polyglot\Locale]
    #[ORM\Column]
    private string $locale;

    #[ORM\ManyToOne(targetEntity: EntityInheritance_MappedSuperclassWithTranslationsInterface::class, inversedBy: 'translations')]
    private EntityInheritance_MappedSuperclassWithTranslationsInterface $entity;

    #[ORM\Column]
    private string $text;
}
