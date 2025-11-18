<?php

namespace Webfactory\Bundle\PolyglotBundle\Tests\Fixtures\Entity\StronglyTyped;

use Doctrine\ORM\Mapping as ORM;
use Webfactory\Bundle\PolyglotBundle\Attribute as Polyglot;

#[ORM\Entity]
class StronglyTypedTranslationsTest_Translation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    public ?int $id = null;

    #[Polyglot\Locale]
    #[ORM\Column]
    public string $locale;

    #[ORM\ManyToOne(targetEntity: StronglyTypedTranslationsTest_Entity::class, inversedBy: 'translations')]
    public StronglyTypedTranslationsTest_Entity $entity;

    #[ORM\Column]
    public string $text;
}
