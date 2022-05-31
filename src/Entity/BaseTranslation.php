<?php

/*
 * (c) webfactory GmbH <info@webfactory.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webfactory\Bundle\PolyglotBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Webfactory\Bundle\PolyglotBundle\Annotation as Polyglot;

/**
 * @ORM\MappedSuperclass
 */
class BaseTranslation
{
    /**
     * @var int
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    protected $id;

    /**
     * @var string
     * @ORM\Column
     * @Polyglot\Locale
     */
    protected $locale;

    /**
     * @var object
     * @ORM\JoinColumn(name="entity_id", referencedColumnName="id", nullable=false)
     */
    protected $entity;

    /**
     * @return string
     */
    public function getLocale()
    {
        return $this->locale;
    }
}
