<?php

namespace Webfactory\Bundle\PolyglotBundle;

use Closure;

/**
 * Chain of multiple translatable objects.
 *
 * Goes through a list of `TranslatableInterface` instances and returns the first
 * (optionally, non-empty) translation found. Updates are passed to the primary translatable.
 */
final class TranslatableChain implements TranslatableInterface
{
    /**
     * @var list<TranslatableInterface>
     */
    private array $translatables;

    public static function firstNonEmpty(TranslatableInterface ...$translatables): self
    {
        return new self(function ($value) {
            return null !== $value && '' !== trim($value);
        }, ...$translatables);
    }

    public static function firstTranslation(TranslatableInterface ...$translatables): self
    {
        return new self(function ($value) {
            return null !== $value;
        }, ...$translatables);
    }

    private function __construct(
        private readonly Closure $comparator,
        TranslatableInterface ...$translatables,
    )
    {
        $this->translatables = $translatables;
    }

    public function translate(string $locale = null): mixed
    {
        $c = $this->comparator;
        foreach ($this->translatables as $translation) {
            $value = $translation->translate($locale);
            if ($c($value)) {
                return $value;
            }
        }

        return null;
    }

    public function setTranslation(mixed $value, string $locale = null): void
    {
        $this->translatables[0]->setTranslation($value, $locale);
    }

    public function isTranslatedInto(string $locale): bool
    {
        foreach ($this->translatables as $translation) {
            if ($translation->isTranslatedInto($locale)) {
                return true;
            }
        }

        return false;
    }

    public function __toString(): string
    {
        return $this->translate();
    }
}
