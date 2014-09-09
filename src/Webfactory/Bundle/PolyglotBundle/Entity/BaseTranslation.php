<?php

namespace Webfactory\Bundle\PolyglotBundle\Entity;

use Webfactory\Bundle\PolyglotBundle\Annotation as Polyglot;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\MappedSuperclass
 */
class BaseTranslation
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

    public function getLocale()
    {
        return $this->locale;
    }
}
