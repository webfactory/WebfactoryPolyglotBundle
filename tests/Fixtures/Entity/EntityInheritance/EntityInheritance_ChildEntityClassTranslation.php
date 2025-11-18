<?php

namespace Webfactory\Bundle\PolyglotBundle\Tests\Fixtures\Entity\EntityInheritance;

use Doctrine\ORM\Mapping as ORM;
use Webfactory\Bundle\PolyglotBundle\Attribute as Polyglot;

#[ORM\Entity]
class EntityInheritance_ChildEntityClassTranslation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[Polyglot\Locale]
    #[ORM\Column]
    private string $locale;

    #[ORM\ManyToOne(inversedBy: 'extraTranslations')]
    private EntityInheritance_ChildEntityClass $entity;

    #[ORM\Column]
    private string $extraText;
}
