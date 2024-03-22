<?php

/*
 * (c) webfactory GmbH <info@webfactory.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webfactory\Bundle\PolyglotBundle;

use Webfactory\Bundle\PolyglotBundle\Locale\DefaultLocaleProvider;

/**
 * Eine TranslationInterface-Implementierung für eine Entität, die
 * noch neu und dem EntityManager nicht bekannt ist. Diese Implementierung
 * kann von Klassen mit Translatable-Feldern im Konstruktor ohne weitere
 * Abhängigkeiten erzeugt werden.
 *
 * Wir können an dieser Stelle nicht auf die Reflection-
 * Mechanismen (TranslatableClassMetadata) zurückgreifen, weil diese z. T. auch
 * Details des EntityManagers (z. B. den Metadata-Cache) benötigen, was
 * wir alles an der Stelle in Klienten nicht zur Verfügung haben.
 *
 * Solange eine Entität aber so frisch ist, reicht es auch aus, "ihre" Übersetzungen
 * nur im Proxy mitzuführen.
 *
 * Die Klasse heisst "Translatable", damit sie in Klienten "nett" initialisiert werden
 * kann:
 *
 *  use Webfactory\Bundle\PolyglotBundle\Annotation as Polyglot;
 *  use Webfactory\Bundle\PolyglotBundle\Translatable;
 *
 *  class MyClass { ...
 *     // @ Polyglot\Translatable
 *     private $aField;
 *     public function __construct() {...
 *       $aField = new Translatable();
 */
final class Translatable implements TranslatableInterface
{
    /**
     * Maps locales to translations.
     *
     * @var array<string, mixed>
     */
    private array $translations = [];

    public function __construct(
        mixed $value = null,
        private string|DefaultLocaleProvider|null $defaultLocale = null,
    ) {
        $this->setTranslation($value);
    }

    public function setDefaultLocale(string $locale): void
    {
        $oldLocale = $this->getDefaultLocale();
        $this->defaultLocale = $locale;
        $newLocale = $this->getDefaultLocale();

        if ('' === $oldLocale && '' !== $newLocale) {
            $this->translations[$newLocale] = $this->translations[$oldLocale];
            unset($this->translations[$oldLocale]);
        }
        $this->defaultLocale = $locale;
    }

    public function translate(string $locale = null): mixed
    {
        $locale = $locale ?: $this->getDefaultLocale();

        return $this->translations[$locale] ?? null;
    }

    public function setTranslation(mixed $value, string $locale = null): void
    {
        $locale = $locale ?: $this->getDefaultLocale();

        $this->translations[$locale] = $value;
    }

    public function isTranslatedInto(string $locale): bool
    {
        return isset($this->translations[$locale]) && !empty($this->translations[$locale]);
    }

    public function __toString(): string
    {
        return (string) $this->translate();
    }

    /**
     * Copies translations from this object into the given one.
     */
    public function copy(TranslatableInterface $p): void
    {
        foreach ($this->translations as $locale => $value) {
            $p->setTranslation($value, '' === $locale ? null : $locale);
        }
    }

    private function getDefaultLocale(): string
    {
        return (string) $this->defaultLocale;
    }
}
