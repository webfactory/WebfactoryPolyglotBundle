<?php

/*
 * (c) webfactory GmbH <info@webfactory.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webfactory\Bundle\PolyglotBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Webfactory\Bundle\PolyglotBundle\Attribute as Polyglot;

/**
 * @ORM\MappedSuperclass
 */
#[ORM\MappedSuperclass]
class BaseTranslation
{
    /**
     * @ORM\Id
     *
     * @ORM\GeneratedValue
     *
     * @ORM\Column(type="integer")
     */
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    protected mixed $id;

    /**
     * @ORM\Column
     */
    #[Polyglot\Locale]
    #[ORM\Column]
    protected ?string $locale;

    /**
     * @ORM\JoinColumn(name="entity_id", referencedColumnName="id", nullable=false)
     */
    #[ORM\JoinColumn(name: 'entity_id', referencedColumnName: 'id', nullable: false)]
    protected object $entity;

    public function getLocale(): ?string
    {
        return $this->locale;
    }
}
