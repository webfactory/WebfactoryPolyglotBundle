<?php

namespace Webfactory\Bundle\PolyglotBundle\Tests\Fixtures\Entity\EntityInheritance;

use Doctrine\ORM\Mapping as ORM;
use Webfactory\Bundle\PolyglotBundle\TranslatableInterface;

/**
 * A mapped superclass that carries a translatable property without any Polyglot
 * configuration. This way, subclasses can decide on their own whether they want
 * to use Polyglot or not.
 */
#[ORM\MappedSuperclass]
abstract class EntityInheritance_MappedSuperclass
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', nullable: true)]
    protected TranslatableInterface|string|null $text = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getText(): TranslatableInterface|string|null
    {
        return $this->text;
    }
}
