<?php

namespace Webfactory\Bundle\PolyglotBundle\Tests;

use Doctrine\ORM\Mapping as ORM;
use Webfactory\Bundle\PolyglotBundle\Entity\BaseTranslation;

/**
 * Translation Entity of the Doctrine entity that is used for testing.
 *
 * @ORM\Entity
 */
class TestEntityTranslation extends BaseTranslation
{
    /**
     * @ORM\Column(nullable = true)
     */
    public $name;

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
