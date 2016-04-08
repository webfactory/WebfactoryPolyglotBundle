<?php

/*
 * (c) webfactory GmbH <info@webfactory.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webfactory\Bundle\PolyglotBundle\Tests;

use Doctrine\ORM\Mapping as ORM;
use Webfactory\Bundle\PolyglotBundle\Annotation as Polyglot;
use Webfactory\Bundle\PolyglotBundle\Entity\BaseTranslation;

/**
 * Translation entity of the Doctrine entity that is used for testing.
 *
 * @ORM\Entity
 */
class TestEntityTranslation extends BaseTranslation
{
    /**
     * @ORM\ManyToOne(targetEntity="TestEntity", inversedBy="translations")
     * @var TestEntity
     */
    protected $entity;

    /**
     * Contains the translation.
     *
     * Must be protected to be usable when this class is used as base for a mock.
     *
     * @ORM\Column(type="string")
     * @var string
     */
    protected $text;

    /**
     * @param string|null $locale, e.g. 'de_DE'
     * @param string|null $text
     * @param TestEntity|null $entity
     */
    function __construct($locale = null, $text = null, TestEntity $entity = null)
    {
        $this->locale = $locale;
        $this->text = $text;
        $this->entity = $entity;
    }
}
