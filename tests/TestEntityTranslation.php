<?php

/*
 * (c) webfactory GmbH <info@webfactory.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webfactory\Bundle\PolyglotBundle\Tests;

use Doctrine\ORM\Mapping as ORM;
use Webfactory\Bundle\PolyglotBundle\Entity\BaseTranslation;

/**
 * Translation entity of the Doctrine entity that is used for testing.
 */
#[ORM\Entity]
class TestEntityTranslation extends BaseTranslation
{
    /**
     * @var TestEntity
     */
    #[ORM\ManyToOne(targetEntity: \TestEntity::class, inversedBy: 'translations')]
    protected $entity;

    /**
     * Contains the translation.
     *
     * Must be protected to be usable when this class is used as base for a mock.
     *
     * @var string
     */
    #[ORM\Column(type: 'string')]
    protected $text;

    /**
     * @param string|null $text
     */
    public function __construct($locale = null, $text = null, TestEntity $entity = null)
    {
        $this->locale = $locale;
        $this->text = $text;
        $this->entity = $entity;
    }
}
