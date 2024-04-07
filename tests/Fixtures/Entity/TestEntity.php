<?php

/*
 * (c) webfactory GmbH <info@webfactory.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webfactory\Bundle\PolyglotBundle\Tests\Fixtures\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Webfactory\Bundle\PolyglotBundle\Attribute as Polyglot;
use Webfactory\Bundle\PolyglotBundle\TranslatableInterface;

/**
 * Doctrine entity that is used for testing.
 */
#[Polyglot\Locale(primary: 'en_GB')]
#[ORM\Entity]
class TestEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    /**
     * Text in the primary locale. Can be set with a string and gets replaced with a TranslatableInterface by the
     * Doctrine PolyglotListener.
     */
    #[Polyglot\Translatable]
    #[ORM\Column(type: 'string')]
    private TranslatableInterface|string|null $text;

    #[Polyglot\TranslationCollection]
    #[ORM\OneToMany(targetEntity: TestEntityTranslation::class, mappedBy: 'entity')] // This property is currently not typed to avoid an error in the \Webfactory\Bundle\PolyglotBundle\Tests\Doctrine\TranslatableClassMetadataTest::can_be_serialized_and_retrieved
    private $translations;

    public function __construct($text)
    {
        $this->translations = new ArrayCollection();
        $this->text = $text;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getText(): string|TranslatableInterface|null
    {
        return $this->text;
    }
}
