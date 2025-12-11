<?php

namespace Webfactory\Bundle\PolyglotBundle\Tests\Fixtures\Entity\TranslatableWithObjectData;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Webfactory\Bundle\PolyglotBundle\Attribute as Polyglot;
use Webfactory\Bundle\PolyglotBundle\Translatable;
use Webfactory\Bundle\PolyglotBundle\TranslatableInterface;

#[Polyglot\Locale(primary: 'en_GB')]
#[ORM\Entity]
class TranslatableWithObjectDataTest_Entity
{
    #[ORM\Column]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    public ?int $id = null;

    #[Polyglot\TranslationCollection]
    #[ORM\OneToMany(targetEntity: TranslatableWithObjectDataTest_Translation::class, mappedBy: 'entity')]
    public Collection $translations;

    #[Polyglot\Translatable]
    #[ORM\Column(type: 'my_object')]
    public TranslatableInterface|TranslatableWithObjectDataTest_Object $data;

    public function __construct()
    {
        $this->translations = new ArrayCollection();
        $this->data = new Translatable();
    }
}
