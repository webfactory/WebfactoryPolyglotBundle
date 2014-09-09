<?php

namespace Webfactory\Bundle\PolyglotBundle\Tests;

use Doctrine\ORM\Mapping as ORM;
use Webfactory\Bundle\PolyglotBundle\Annotation as Polyglot;

/**
 * Translation Entity of the Doctrine entity that is used for testing.
 *
 * @ORM\Entity
 */
class TestEntityTranslation
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    protected $id;

    /**
     * @ORM\Column
     * @Polyglot\Locale
     */
    protected $locale;

    /**
     * @ORM\JoinColumn(name="entity_id", referencedColumnName="id", nullable=false)
     */
    protected $entity;

    /**
     * @ORM\Column(nullable = true)
     */
    public $name;

    public function getLocale()
    {
        return $this->locale;
    }

    /**
     * @param string $locale
     */
    public function setLocale($locale)
    {
        $this->locale = $locale;
    }

    /**
     * @param mixed $entity
     */
    public function setEntity($entity)
    {
        $this->entity = $entity;
    }
}
