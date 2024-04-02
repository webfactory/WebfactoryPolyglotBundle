<?php

/*
 * (c) webfactory GmbH <info@webfactory.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webfactory\Bundle\PolyglotBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Webfactory\Bundle\PolyglotBundle\Annotation as PolyglotAnnotation;
use Webfactory\Bundle\PolyglotBundle\Attribute as Polyglot;

/**
 * @ORM\MappedSuperclass
 */
class BaseTranslation
{
    /**
     * @ORM\Id
     *
     * @ORM\GeneratedValue
     *
     * @ORM\Column(type="integer")
     */
    protected $id;

    /**
     * @ORM\Column
     *
     * @PolyglotAnnotation\Locale
     */
    #[Polyglot\Locale]
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
