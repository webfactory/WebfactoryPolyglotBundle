<?php

/*
 * (c) webfactory GmbH <info@webfactory.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webfactory\Bundle\PolyglotBundle\Tests;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Webfactory\Bundle\PolyglotBundle\Annotation as Polyglot;
use Webfactory\Bundle\PolyglotBundle\TranslatableInterface;

/**
 * Doctrine entity that is used for testing.
 *
 * @ORM\Entity()
 *
 * @Polyglot\Locale(primary="en_GB")
 */
class TestEntity
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private ?int $id = null;

    /**
     * Text in the primary locale. Can be set with a string and gets replaced with a TranslatableInterface by the
     * Doctrine PolyglotListener.
     *
     * @ORM\Column(type="string")
     *
     * @Polyglot\Translatable
     */
    private TranslatableInterface|string|null $text;

    /**
     * @ORM\OneToMany(targetEntity="TestEntityTranslation", mappedBy="entity")
     *
     * @Polyglot\TranslationCollection
     */
    private Collection $translations;

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
