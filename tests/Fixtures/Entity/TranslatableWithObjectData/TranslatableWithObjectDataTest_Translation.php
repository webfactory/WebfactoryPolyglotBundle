<?php

namespace Webfactory\Bundle\PolyglotBundle\Tests\Fixtures\Entity\TranslatableWithObjectData;

use Doctrine\ORM\Mapping as ORM;
use Webfactory\Bundle\PolyglotBundle\Attribute as Polyglot;

#[ORM\Entity]
class TranslatableWithObjectDataTest_Translation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[Polyglot\Locale]
    #[ORM\Column]
    private string $locale;

    #[ORM\ManyToOne(inversedBy: 'translations')]
    private TranslatableWithObjectDataTest_Entity $entity;

    #[ORM\Column(type: 'object')]
    private TranslatableWithObjectDataTest_Object $data;
}
