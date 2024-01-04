<?php

/*
 * (c) webfactory GmbH <info@webfactory.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webfactory\Bundle\PolyglotBundle\Tests;

use Doctrine\Common\Collections\ArrayCollection;
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
     *
     * @ORM\Column(type="integer", name="id")
     *
     * @ORM\GeneratedValue
     *
     * @var int|null
     */
    private $id;

    /**
     * Text in the primary locale. Can be set with a string and gets replaced with a TranslatableInterface by the
     * Doctrine PolyglotListener.
     *
     * @ORM\Column(type="string")
     *
     * @Polyglot\Translatable
     *
     * @var TranslatableInterface|string|null
     */
    protected $text;

    /**
     * @ORM\OneToMany(targetEntity="TestEntityTranslation", mappedBy="entity")
     *
     * @Polyglot\TranslationCollection
     */
    protected $translations;

    public function __construct($text)
    {
        $this->translations = new ArrayCollection();
        $this->text = $text;
    }

    /**
     * @return int|null
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return TranslatableInterface|string|null
     */
    public function getText()
    {
        return $this->text;
    }
}
