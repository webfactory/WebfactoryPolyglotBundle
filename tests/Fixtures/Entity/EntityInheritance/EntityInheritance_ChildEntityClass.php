<?php

namespace Webfactory\Bundle\PolyglotBundle\Tests\Fixtures\Entity\EntityInheritance;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Webfactory\Bundle\PolyglotBundle\Attribute as Polyglot;
use Webfactory\Bundle\PolyglotBundle\TranslatableInterface;

#[ORM\Entity]
class EntityInheritance_ChildEntityClass extends EntityInheritance_BaseEntityClass
{
    #[Polyglot\Translatable]
    #[ORM\Column(type: 'string')]
    private TranslatableInterface|string|null $extraText = null;

    #[Polyglot\TranslationCollection]
    #[ORM\OneToMany(targetEntity: EntityInheritance_ChildEntityClassTranslation::class, mappedBy: 'entity')]
    private Collection $extraTranslations;

    public function __construct()
    {
        parent::__construct();
        $this->extraTranslations = new ArrayCollection();
    }

    public function setExtra(TranslatableInterface $extraText): void
    {
        $this->extraText = $extraText;
    }

    public function getExtraText(): TranslatableInterface
    {
        return $this->extraText;
    }
}
