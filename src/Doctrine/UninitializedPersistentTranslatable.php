<?php

namespace Webfactory\Bundle\PolyglotBundle\Doctrine;

use LogicException;
use Webfactory\Bundle\PolyglotBundle\TranslatableInterface;

/**
 * @psalm-internal Webfactory\Bundle\PolyglotBundle
 */
final class UninitializedPersistentTranslatable implements TranslatableInterface
{
    public function __construct(private readonly string $primaryValue)
    {
    }

    public function translate(?string $locale = null): mixed
    {
        throw new LogicException('this method is not supposed to be called');
    }

    public function setTranslation(mixed $value, ?string $locale = null): void
    {
        throw new LogicException('this method is not supposed to be called');
    }

    public function isTranslatedInto(string $locale): bool
    {
        throw new LogicException('this method is not supposed to be called');
    }

    public function __toString(): string
    {
        throw new LogicException('this method is not supposed to be called');
    }

    /**
     * @psalm-internal Webfactory\Bundle\PolyglotBundle
     */
    public function getPrimaryValue(): string
    {
        return $this->primaryValue;
    }
}
