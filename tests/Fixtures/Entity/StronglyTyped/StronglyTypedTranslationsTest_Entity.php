<?php

namespace Webfactory\Bundle\PolyglotBundle\Tests\Fixtures\Entity\StronglyTyped;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Webfactory\Bundle\PolyglotBundle\Attribute as Polyglot;
use Webfactory\Bundle\PolyglotBundle\Translatable;
use Webfactory\Bundle\PolyglotBundle\TranslatableInterface;

#[Polyglot\Locale(primary: 'en_GB')]
#[ORM\Entity]
class StronglyTypedTranslationsTest_Entity
{
    #[ORM\Column(type: 'integer')]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    public ?int $id = null;

    #[Polyglot\TranslationCollection]
    #[ORM\OneToMany(targetEntity: StronglyTypedTranslationsTest_Translation::class, mappedBy: 'entity')]
    public Collection $translations;

    /**
     * @var TranslatableInterface<string>
     */
    #[Polyglot\Translatable]
    #[ORM\Column(type: 'translatable_string', options: ['use_text_column' => true])]
    public TranslatableInterface $text;

    public function __construct()
    {
        $this->text = new Translatable();
        $this->translations = new ArrayCollection();
    }
}
