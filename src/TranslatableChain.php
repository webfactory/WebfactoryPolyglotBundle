<?php

namespace Webfactory\Bundle\PolyglotBundle;

/**
 * Chain of multiple translatable objects.
 *
 * Goes through a list of `TranslatableInterface` instances and returns the first
 * non-empty translation found. Updates are passed to the primary translatable.
 */
final class TranslatableChain implements TranslatableInterface
{
    /**
     * @var list<TranslatableInterface>
     */
    private $translatables;

    private $comparator;

    public static function firstNonEmpty(...$translatables): self
    {
        return new self(function ($value) {
            return null !== $value && '' !== trim($value);
        }, ...$translatables);
    }

    public static function firstTranslation(...$translatables): self
    {
        return new self(function ($value) {
            return null !== $value;
        }, ...$translatables);
    }

    private function __construct($comparator, ...$translatables)
    {
        $this->comparator = $comparator;
        $this->translatables = $translatables;
    }

    public function translate(string $locale = null)
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

    public function setTranslation($value, string $locale = null): void
    {
        $this->translatables[0]->setTranslation($value, $locale);
    }

    public function isTranslatedInto($locale): bool
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
